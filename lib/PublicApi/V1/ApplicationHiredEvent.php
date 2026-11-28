<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\PublicApi\V1;

use DateTimeImmutable;
use InvalidArgumentException;
use OCP\EventDispatcher\Event;

/**
 * ZM-07 Minimal-Vertrag: Wird nach erfolgreichem Statusübergang auf „hired"
 * einmalig dispatcht. Datenumfang ist auf Vorgangs- und Organisationskennungen
 * beschränkt (keine Namen, keine Kontaktdaten). Ohne Listener ist das Event
 * ein gültiger No-Op; Consumer dürfen den Provider nicht voraussetzen.
 */
final class ApplicationHiredEvent extends Event {
    public function __construct(
        private int $applicationId,
        private string $fromStatus,
        private string $areaKey,
        private string $actorUid,
        private DateTimeImmutable $occurredAt,
    ) {
        parent::__construct();
        if ($this->applicationId <= 0) {
            throw new InvalidArgumentException('Ungültige Bewerbungs-ID.');
        }
        if ($this->fromStatus === '' || $this->fromStatus === 'hired') {
            throw new InvalidArgumentException('Ungültiger Ausgangsstatus.');
        }
        if (preg_match('/^[A-Za-z0-9._:-]{1,64}$/', $this->areaKey) !== 1) {
            throw new InvalidArgumentException('Ungültiger Bereichsschlüssel.');
        }
        if (preg_match('/^[A-Za-z0-9._@-]{1,64}$/', $this->actorUid) !== 1) {
            throw new InvalidArgumentException('Ungültige Actor-UID.');
        }
    }

    public function applicationId(): int { return $this->applicationId; }
    public function fromStatus(): string { return $this->fromStatus; }
    public function areaKey(): string { return $this->areaKey; }
    public function actorUid(): string { return $this->actorUid; }
    public function occurredAt(): DateTimeImmutable { return $this->occurredAt; }
}
