<?php

declare(strict_types=1);

use OCA\FlzRecruitment\Exception\ValidationException;
use OCA\FlzRecruitment\Service\StatusMailBodyService;
use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertSame;
use function RecruitmentTests\assertThrows;
use function RecruitmentTests\assertTrue;

TestRunner::test('status mail HTML keeps the small editor vocabulary and creates a text alternative', static function (): void {
    $body = new StatusMailBodyService();
    $html = $body->sanitize('<p>Hallo <strong>Ari</strong><br><em>Willkommen</em></p><ul><li>Erster Punkt</li></ul><p><a href="https://example.invalid/info" onclick="evil()">Informationen</a></p>');

    assertTrue(str_contains($html, '<strong>Ari</strong>'));
    assertTrue(str_contains($html, '<ul><li>Erster Punkt</li></ul>'));
    assertTrue(str_contains($html, 'href="https://example.invalid/info"'));
    assertTrue(!str_contains($html, 'onclick'));
    assertSame("Hallo Ari\nWillkommen\n\n• Erster Punkt\n\nInformationen (https://example.invalid/info)", $body->plainText($html));
});

TestRunner::test('status mail HTML removes active content and unsafe links without retaining their payload', static function (): void {
    $body = new StatusMailBodyService();
    $html = $body->sanitize('<p>Sicher</p><script>alert(1)</script><style>body{display:none}</style><p><a href="javascript:alert(2)">Link</a></p><img src=x onerror=alert(3)>');

    foreach (['script', 'style', 'javascript:', 'onerror', '<img', 'alert(1)', 'display:none'] as $forbidden) {
        assertTrue(!str_contains(strtolower($html), strtolower($forbidden)), "Unsicherer HTML-Inhalt blieb erhalten: {$forbidden}");
    }
    assertTrue(str_contains($html, '<a>Link</a>'));
    assertThrows(static fn () => $body->sanitize('  '), ValidationException::class);
});

TestRunner::test('legacy plain mail bodies are escaped before becoming editable HTML', static function (): void {
    $body = new StatusMailBodyService();
    assertSame('<p>Hallo &lt;script&gt;alt&lt;/script&gt;<br>Zweite Zeile</p>', $body->editableHtml("Hallo <script>alt</script>\nZweite Zeile", 'plain'));
});
