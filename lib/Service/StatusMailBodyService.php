<?php

declare(strict_types=1);

namespace OCA\Recruitment\Service;

use DOMDocument;
use DOMElement;
use DOMNode;
use OCA\Recruitment\Exception\ValidationException;

/** Bereinigt den bewusst kleinen HTML-Wortschatz für Statusmails. */
final class StatusMailBodyService {
    private const ALLOWED_ELEMENTS = ['p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'a'];
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'input', 'button', 'textarea', 'select', 'option', 'img', 'video', 'audio'];

    public function sanitize(string $html): string {
        $html = trim($html);
        if ($html === '' || strlen($html) > 200000) {
            throw new ValidationException('Der HTML-Nachrichtentext ist leer oder zu lang.');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="adrecruitment-mail-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) throw new ValidationException('Der HTML-Nachrichtentext konnte nicht verarbeitet werden.');

        $root = $document->getElementById('adrecruitment-mail-root');
        if (!$root instanceof DOMElement) throw new ValidationException('Der HTML-Nachrichtentext konnte nicht verarbeitet werden.');
        $this->cleanChildren($root);

        $result = '';
        foreach ($root->childNodes as $child) $result .= $document->saveHTML($child);
        $result = trim($result);
        if ($this->plainText($result) === '') throw new ValidationException('Der HTML-Nachrichtentext darf nicht leer sein.');
        return $result;
    }

    public function editableHtml(string $body, string $format): string {
        if ($format === 'html') return $this->sanitize($body);
        if ($format !== 'plain') throw new ValidationException('Das Format des Nachrichtentexts ist ungültig.');
        $escaped = htmlspecialchars(trim(str_replace(["\r\n", "\r"], "\n", $body)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($escaped === '') throw new ValidationException('Der Nachrichtentext darf nicht leer sein.');
        return '<p>' . str_replace("\n", '<br>', $escaped) . '</p>';
    }

    public function plainText(string $html): string {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="adrecruitment-mail-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $document->getElementById('adrecruitment-mail-root');
        if (!$root instanceof DOMElement) return '';
        $text = $this->nodeText($root);
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        return trim($text);
    }

    private function cleanChildren(DOMNode $parent): void {
        for ($node = $parent->firstChild; $node !== null;) {
            $next = $node->nextSibling;
            if ($node instanceof DOMElement) {
                $tag = strtolower($node->tagName);
                if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                    $parent->removeChild($node);
                    $node = $next;
                    continue;
                }
                $this->cleanChildren($node);
                if (!in_array($tag, self::ALLOWED_ELEMENTS, true)) {
                    while ($node->firstChild !== null) $parent->insertBefore($node->firstChild, $node);
                    $parent->removeChild($node);
                    $node = $next;
                    continue;
                }
                $href = $tag === 'a' ? trim($node->getAttribute('href')) : '';
                while ($node->attributes->length > 0) $node->removeAttributeNode($node->attributes->item(0));
                if ($tag === 'a' && $this->safeUrl($href)) $node->setAttribute('href', $href);
            }
            $node = $next;
        }
    }

    private function safeUrl(string $url): bool {
        if ($url === '' || strlen($url) > 2000) return false;
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['https', 'http', 'mailto'], true);
    }

    private function nodeText(DOMNode $node): string {
        if ($node->nodeType === XML_TEXT_NODE) return $node->nodeValue ?? '';
        if (!$node instanceof DOMElement && !$node->hasChildNodes()) return '';
        $tag = $node instanceof DOMElement ? strtolower($node->tagName) : '';
        if ($tag === 'br') return "\n";
        $text = '';
        foreach ($node->childNodes as $child) $text .= $this->nodeText($child);
        if ($tag === 'a' && $node instanceof DOMElement && $node->hasAttribute('href')) {
            $href = $node->getAttribute('href');
            if ($href !== '' && trim($text) !== $href) $text .= ' (' . $href . ')';
        }
        if ($tag === 'li') return '• ' . trim($text) . "\n";
        if (in_array($tag, ['p', 'ul', 'ol'], true)) return trim($text) . "\n\n";
        return $text;
    }
}
