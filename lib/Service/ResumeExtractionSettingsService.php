<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Service;

use OCA\FlzRecruitment\AppInfo\Application;
use OCA\FlzRecruitment\Exception\ConflictException;
use OCA\FlzRecruitment\Exception\ValidationException;
use OCP\IAppConfig;
use OCA\FlzRecruitment\Contract\PdfTextExtractor;

final class ResumeExtractionSettingsService {
    public const RULES = 'rules';
    public const LOCAL_MODEL = 'local_model';

    public function __construct(private IAppConfig $config, private PdfTextExtractor $pdfTextExtractor) {}

    /** @return array{method:string,revision:int,options:list<array{value:string,label:string,available:bool}>} */
    public function settings(): array {
        return [
            'method' => $this->config->getValueString(Application::APP_ID, 'resume_extraction_method', self::RULES),
            'revision' => (int)$this->config->getValueString(Application::APP_ID, 'resume_extraction_revision', '0'),
            'options' => [
                ['value' => self::RULES, 'label' => 'Lokale Stichworterkennung', 'available' => true],
                ['value' => self::LOCAL_MODEL, 'label' => 'Lokales Server-Modell', 'available' => false],
            ],
            'runtime' => [
                'pdfTextAvailable' => $this->pdfTextExtractor->available(),
                'engine' => $this->pdfTextExtractor->engineLabel(),
            ],
        ];
    }

    /** @return array{method:string,revision:int,options:list<array{value:string,label:string,available:bool}>} */
    public function save(string $method, int $expectedRevision): array {
        $current = $this->settings();
        if ($current['revision'] !== $expectedRevision) throw new ConflictException('Die Datenextraktion wurde zwischenzeitlich geändert.');
        if ($method === self::LOCAL_MODEL) throw new ValidationException('Es ist noch kein lokales Server-Modell angebunden.');
        if ($method !== self::RULES) throw new ValidationException('Das gewählte Extraktionsverfahren ist unbekannt.');
        $revision = $expectedRevision + 1;
        $this->config->setValueString(Application::APP_ID, 'resume_extraction_method', $method);
        $this->config->setValueString(Application::APP_ID, 'resume_extraction_revision', (string)$revision);
        return ['method' => $method, 'revision' => $revision, 'options' => $current['options'], 'runtime' => $current['runtime']];
    }
}
