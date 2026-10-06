<?php

declare(strict_types=1);

namespace OCA\Recruitment\Organization;

use InvalidArgumentException;

/** Immutable, app-local projection of the public LocalBase Organization V1 contract. */
final class OrganizationSnapshot {
    public const VALID = 'VALID';
    public const MISSING = 'MISSING';
    public const DISABLED = 'DISABLED';
    public const INCOMPATIBLE = 'INCOMPATIBLE';
    public const INVALID = 'INVALID';
    public const UNAVAILABLE = 'UNAVAILABLE';

    private const STATUSES = [
        self::VALID,
        self::MISSING,
        self::DISABLED,
        self::INCOMPATIBLE,
        self::INVALID,
        self::UNAVAILABLE,
    ];

    /**
     * @param array<string, array{groupId: string, label: string}> $roles
     * @param array<string, array{groupId: string, label: string}> $areas
     */
    private function __construct(
        private string $status,
        private string $contractVersion,
        private int $definitionVersion,
        private string $checksum,
        private array $roles,
        private array $areas,
    ) {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Unknown organization snapshot status.');
        }
        if ($status !== self::VALID) {
            $this->roles = [];
            $this->areas = [];
        }
    }

    /**
     * @param array<string, array{groupId: string, label: string}> $roles
     * @param array<string, array{groupId: string, label: string}> $areas
     */
    public static function valid(
        string $contractVersion,
        int $definitionVersion,
        string $checksum,
        array $roles,
        array $areas,
    ): self {
        return new self(self::VALID, $contractVersion, $definitionVersion, $checksum, $roles, $areas);
    }

    public static function unavailable(
        string $status,
        string $contractVersion = '',
        int $definitionVersion = 0,
        string $checksum = '',
    ): self {
        if ($status === self::VALID) {
            throw new InvalidArgumentException('A valid organization snapshot requires provider mappings.');
        }
        return new self($status, $contractVersion, $definitionVersion, $checksum, [], []);
    }

    public function status(): string { return $this->status; }
    public function isValid(): bool { return $this->status === self::VALID; }
    public function contractVersion(): string { return $this->contractVersion; }
    public function definitionVersion(): int { return $this->definitionVersion; }
    public function checksum(): string { return $this->checksum; }

    /** @return array<string, array{groupId: string, label: string}> */
    public function roles(): array { return $this->roles; }

    /** @return array<string, array{groupId: string, label: string}> */
    public function areas(): array { return $this->areas; }

    public function roleGroupId(string $roleKey): ?string { return $this->roles[$roleKey]['groupId'] ?? null; }
    public function areaGroupId(string $areaKey): ?string { return $this->areas[$areaKey]['groupId'] ?? null; }

    /** @return list<string> */
    public function roleKeys(): array { return array_keys($this->roles); }

    /** @return list<string> */
    public function areaKeys(): array { return array_keys($this->areas); }

    /** @return array{status:string,contractVersion:string,definitionVersion:int,checksum:string,roles:array<string,array{groupId:string,label:string}>,areas:array<string,array{groupId:string,label:string}>} */
    public function toArray(): array {
        return [
            'status' => $this->status,
            'contractVersion' => $this->contractVersion,
            'definitionVersion' => $this->definitionVersion,
            'checksum' => $this->checksum,
            'roles' => $this->roles,
            'areas' => $this->areas,
        ];
    }
}
