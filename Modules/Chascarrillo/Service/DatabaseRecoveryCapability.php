<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use DateTimeImmutable;
use InvalidArgumentException;

/** Sanitized evidence returned by a trusted external recovery provider. */
final class DatabaseRecoveryCapability
{
    public const LEVEL_UNAVAILABLE = 'unavailable';
    public const LEVEL_VALIDATED = 'validated';
    public const LEVEL_RESTORE_TESTED = 'restore_tested';

    private function __construct(
        private readonly string $strategy,
        private readonly string $level,
        private readonly ?string $provider,
        private readonly ?string $reference,
        private readonly ?string $backupCreatedAt,
        private readonly ?string $verifiedAt,
        private readonly bool $restoreTested
    ) {
    }

    public static function unavailable(string $strategy = 'none'): self
    {
        self::assertSlug($strategy, 'strategy');
        return new self($strategy, self::LEVEL_UNAVAILABLE, null, null, null, null, false);
    }

    public static function externalVerified(
        string $provider,
        string $reference,
        string $backupCreatedAt,
        string $verifiedAt,
        bool $restoreTested
    ): self {
        self::assertSlug($provider, 'provider');
        self::assertReference($reference);
        self::assertTimestamp($backupCreatedAt, 'backup_created_at');
        self::assertTimestamp($verifiedAt, 'verified_at');
        if ($verifiedAt < $backupCreatedAt) {
            throw new InvalidArgumentException('verified_at no puede preceder al backup');
        }
        return new self(
            'external',
            $restoreTested ? self::LEVEL_RESTORE_TESTED : self::LEVEL_VALIDATED,
            $provider,
            $reference,
            $backupCreatedAt,
            $verifiedAt,
            $restoreTested
        );
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        $fields = array_keys($data);
        sort($fields, SORT_STRING);
        if (
            $fields !== [
            'backup_created_at',
            'level',
            'provider',
            'reference',
            'restore_tested',
            'strategy',
            'verified_at',
            ]
        ) {
            throw new InvalidArgumentException('Esquema de recovery no válido');
        }
        if (!is_string($data['strategy']) || !is_string($data['level']) || !is_bool($data['restore_tested'])) {
            throw new InvalidArgumentException('Tipos de recovery no válidos');
        }
        if ($data['level'] === self::LEVEL_UNAVAILABLE) {
            if (
                $data['provider'] !== null
                || $data['reference'] !== null
                || $data['backup_created_at'] !== null
                || $data['verified_at'] !== null
                || $data['restore_tested']
            ) {
                throw new InvalidArgumentException('Recovery unavailable incoherente');
            }
            return self::unavailable($data['strategy']);
        }
        foreach (['provider', 'reference', 'backup_created_at', 'verified_at'] as $field) {
            if (!is_string($data[$field])) {
                throw new InvalidArgumentException("Campo de recovery no válido: {$field}");
            }
        }
        if ($data['strategy'] !== 'external') {
            throw new InvalidArgumentException('Estrategia de recovery no válida');
        }
        $capability = self::externalVerified(
            $data['provider'],
            $data['reference'],
            $data['backup_created_at'],
            $data['verified_at'],
            $data['restore_tested']
        );
        if ($capability->level() !== $data['level']) {
            throw new InvalidArgumentException('Nivel de recovery incoherente');
        }
        return $capability;
    }

    public function allowsMigrations(): bool
    {
        return in_array($this->level, [self::LEVEL_VALIDATED, self::LEVEL_RESTORE_TESTED], true);
    }

    public function level(): string
    {
        return $this->level;
    }

    /** @return array<string,bool|string|null> */
    public function toArray(): array
    {
        return [
            'strategy' => $this->strategy,
            'level' => $this->level,
            'provider' => $this->provider,
            'reference' => $this->reference,
            'backup_created_at' => $this->backupCreatedAt,
            'verified_at' => $this->verifiedAt,
            'restore_tested' => $this->restoreTested,
        ];
    }

    private static function assertSlug(string $value, string $field): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/', $value) !== 1) {
            throw new InvalidArgumentException("{$field} no válido");
        }
    }

    private static function assertReference(string $reference): void
    {
        if (
            preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\\/-]{0,199}$/', $reference) !== 1
            || str_contains(strtolower($reference), 'password')
        ) {
            throw new InvalidArgumentException('Referencia de recuperación no válida');
        }
    }

    private static function assertTimestamp(string $timestamp, string $field): void
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:s\\Z', $timestamp);
        if ($parsed === false || $parsed->format('Y-m-d\\TH:i:s\\Z') !== $timestamp) {
            throw new InvalidArgumentException("{$field} no válido");
        }
    }
}
