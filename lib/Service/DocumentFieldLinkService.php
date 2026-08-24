<?php

declare(strict_types=1);

namespace OCA\Recruitment\Service;

use DateTimeImmutable;
use OCA\Recruitment\Contract\DocumentFieldLinkStore;
use OCA\Recruitment\Exception\ConflictException;
use OCA\Recruitment\Exception\ValidationException;

/** Verknüpft nachgewiesene PDF-Fundstellen kontrolliert mit Bewerbungsfeldern. */
final class DocumentFieldLinkService {
    public const TARGETS = [
        'previousExperience',
        'germanLanguageLevel',
        'birthDate',
        'birthPlace',
        'freeComment',
    ];
    private ApplicationFieldValueService $applicationFieldValues;

    public function __construct(
        private DocumentFieldLinkStore $store,
        ?ApplicationFieldValueService $applicationFieldValues = null,
    ) { $this->applicationFieldValues = $applicationFieldValues ?? new ApplicationFieldValueService(); }

    public function requiredCapability(string $targetField): string {
        $this->assertTarget($targetField);
        return in_array($targetField, ['birthDate', 'birthPlace'], true)
            ? RecruitmentAccessService::EDIT_HIRING_DATA
            : RecruitmentAccessService::EDIT_APPLICATIONS;
    }

    /** @return array<string,mixed> */
    public function fieldContext(int $attachmentId, string $targetField): array {
        $this->assertTarget($targetField);
        $context = $this->assignedContext($attachmentId);
        $state = $this->store->documentFieldState((int)$context['applicationId']);
        $field = $state['fields'][$targetField] ?? null;
        if (!is_array($field)) throw new ValidationException('Das Zielfeld ist nicht verfügbar.');
        return [
            'applicationId' => (int)$context['applicationId'],
            'targetField' => $targetField,
            'value' => (string)($field['value'] ?? ''),
            'version' => (int)($field['version'] ?? 0),
            'germanLevels' => $targetField === 'germanLanguageLevel' ? ApplicationFieldValueService::GERMAN_LEVELS : [],
        ];
    }

    /** @return list<array<string,mixed>> */
    public function links(int $attachmentId): array {
        $this->assignedContext($attachmentId);
        return $this->store->documentFieldLinks($attachmentId);
    }

    /**
     * @param list<array<string,mixed>> $rectangles
     * @return array<string,mixed>
     */
    public function linkSelection(
        int $attachmentId,
        string $targetField,
        string $selectedText,
        string $appliedValue,
        int $pageNumber,
        array $rectangles,
        bool $replaceExisting,
        int $expectedVersion,
        string $clientKey,
        string $actorUid,
    ): array {
        $this->assertTarget($targetField);
        $clientKey = trim($clientKey);
        if (preg_match('/^[A-Za-z0-9._:-]{8,64}$/', $clientKey) !== 1) {
            throw new ValidationException('Die Feldverknüpfung besitzt keine gültige Kennung.');
        }
        $existingLink = $this->store->findDocumentFieldLinkByClientKey($attachmentId, $clientKey);
        if ($existingLink !== null) return $existingLink;

        $context = $this->assignedContext($attachmentId);
        $selectedText = trim($selectedText);
        if (strlen($selectedText) > 4000 || $pageNumber < 1 || $pageNumber > 2000) {
            throw new ValidationException('Die PDF-Fundstelle ist ungültig.');
        }
        $rectangles = $this->validateRectangles($rectangles);
        if ($selectedText === '' && $rectangles === []) {
            throw new ValidationException('Die PDF-Fundstelle enthält weder Text noch eine Markierung.');
        }

        $state = $this->store->documentFieldState((int)$context['applicationId']);
        $field = $state['fields'][$targetField] ?? null;
        if (!is_array($field)) throw new ValidationException('Das Zielfeld ist nicht verfügbar.');
        if ((int)($field['version'] ?? -1) !== $expectedVersion) {
            throw new ConflictException('Das Zielfeld wurde zwischenzeitlich geändert.');
        }
        $currentValue = (string)($field['value'] ?? '');
        $appliedValue = $this->normalizeValue($targetField, $appliedValue);
        if ($targetField === 'freeComment') {
            $resultValue = $currentValue === '' ? $appliedValue : $currentValue . "\n" . $appliedValue;
            if (strlen($resultValue) > 20000) throw new ValidationException('Der freie Kommentar ist zu lang.');
        } else {
            if ($currentValue !== '' && $currentValue !== $appliedValue && !$replaceExisting) {
                throw new ConflictException('Das Zielfeld enthält bereits einen anderen Wert.');
            }
            $resultValue = $appliedValue;
        }

        $id = $this->store->createDocumentFieldLinkAndApply([
            'attachmentId' => $attachmentId,
            'applicationId' => (int)$context['applicationId'],
            'targetField' => $targetField,
            'selectedText' => $selectedText,
            'appliedValue' => $appliedValue,
            'pageNumber' => $pageNumber,
            'rectangles' => $rectangles,
            'replaceExisting' => $replaceExisting,
            'actorUid' => $actorUid,
            'clientKey' => $clientKey,
        ], $resultValue, $expectedVersion);
        return $this->store->documentFieldLink($id);
    }

    /** @return array<string,mixed> */
    private function assignedContext(int $attachmentId): array {
        $context = $this->store->attachmentContext($attachmentId);
        if (($context['applicationId'] ?? null) === null) {
            throw new ValidationException('Das Dokument muss zuerst einer Bewerbung zugeordnet werden.');
        }
        return $context;
    }

    private function assertTarget(string $targetField): void {
        if (!in_array($targetField, self::TARGETS, true)) {
            throw new ValidationException('Das Zielfeld der PDF-Fundstelle ist ungültig.');
        }
    }

    /** @param list<array<string,mixed>> $rectangles
     *  @return list<array{x:float,y:float,width:float,height:float}>
     */
    private function validateRectangles(array $rectangles): array {
        if (count($rectangles) > 50) throw new ValidationException('Die PDF-Fundstelle enthält zu viele Teilmarkierungen.');
        $normalized = [];
        foreach ($rectangles as $rectangle) {
            if (!is_array($rectangle)) throw new ValidationException('Eine PDF-Markierung ist ungültig.');
            foreach (['x', 'y', 'width', 'height'] as $key) {
                if (!isset($rectangle[$key]) || !is_numeric($rectangle[$key])) {
                    throw new ValidationException('Eine PDF-Markierung ist unvollständig.');
                }
            }
            $x = (float)$rectangle['x'];
            $y = (float)$rectangle['y'];
            $width = (float)$rectangle['width'];
            $height = (float)$rectangle['height'];
            if (!is_finite($x) || !is_finite($y) || !is_finite($width) || !is_finite($height)
                || $x < 0 || $y < 0 || $width <= 0 || $height <= 0
                || $x + $width > 1.001 || $y + $height > 1.001) {
                throw new ValidationException('Eine PDF-Markierung liegt außerhalb der Seite.');
            }
            $normalized[] = ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height];
        }
        return $normalized;
    }

    private function normalizeValue(string $targetField, string $value): string {
        if (in_array($targetField, ApplicationFieldValueService::FIELDS, true)) {
            return $this->applicationFieldValues->normalize($targetField, $value);
        }
        $value = trim($value);
        $maximum = in_array($targetField, ['previousExperience', 'freeComment'], true) ? 8000 : 255;
        if ($value === '' || strlen($value) > $maximum) throw new ValidationException('Der zu übernehmende Wert ist ungültig.');
        if ($targetField === 'birthDate') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date === false || $date->format('Y-m-d') !== $value
                || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                throw new ValidationException('Das Geburtsdatum ist ungültig; erwartet wird JJJJ-MM-TT.');
            }
        }
        return $value;
    }
}
