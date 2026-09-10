<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use RuntimeException;

final class UpdateReconciliationPlan
{
    public const SAFE_RETRY = 'SAFE_RETRY';
    public const CONFIRMED_ROLLBACK = 'CONFIRMED_ROLLBACK';
    public const CONFIRMED_COMPLETION = 'CONFIRMED_COMPLETION';
    public const RECOVERY_REQUIRED = 'RECOVERY_REQUIRED';
    public const CONFLICT = 'CONFLICT';
    public const AMBIGUOUS = 'AMBIGUOUS';

    public const RESOLUTION_RETRY = 'retry';
    public const RESOLUTION_ROLLBACK = 'rollback';
    public const RESOLUTION_COMPLETION = 'completion';

    private string $fingerprint;

    /** @param array<string,mixed> $evidence */
    public function __construct(
        private readonly string $attemptId,
        private readonly string $classification,
        private readonly ?string $resolution,
        private readonly array $evidence
    ) {
        self::assertAttemptId($attemptId);
        $allowed = [
            self::SAFE_RETRY => self::RESOLUTION_RETRY,
            self::CONFIRMED_ROLLBACK => self::RESOLUTION_ROLLBACK,
            self::CONFIRMED_COMPLETION => self::RESOLUTION_COMPLETION,
            self::RECOVERY_REQUIRED => null,
            self::CONFLICT => null,
            self::AMBIGUOUS => null,
        ];
        if (!array_key_exists($classification, $allowed) || $allowed[$classification] !== $resolution) {
            throw new RuntimeException('Clasificación o resolución de reconciliación no válida');
        }
        $this->fingerprint = hash('sha256', self::canonicalJson([
            'attempt_id' => $attemptId,
            'classification' => $classification,
            'resolution' => $resolution,
            'evidence' => $evidence,
        ]));
    }

    public function attemptId(): string
    {
        return $this->attemptId;
    }

    public function classification(): string
    {
        return $this->classification;
    }

    public function resolution(): ?string
    {
        return $this->resolution;
    }

    public function fingerprint(): string
    {
        return $this->fingerprint;
    }

    /** @return array<string,mixed> */
    public function evidence(): array
    {
        return $this->evidence;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'attempt_id' => $this->attemptId,
            'classification' => $this->classification,
            'resolution' => $this->resolution,
            'fingerprint' => $this->fingerprint,
            'evidence' => $this->evidence,
        ];
    }

    public static function canonicalJson(mixed $value): string
    {
        $normalized = self::normalize($value);
        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('No se pudo serializar la evidencia de reconciliación');
        }
        return $json;
    }

    private static function normalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            $value[$key] = self::normalize($item);
        }
        return $value;
    }

    public static function assertAttemptId(string $attemptId): void
    {
        if (preg_match('/^[a-f0-9]{32}$/', $attemptId) !== 1) {
            throw new RuntimeException('Identificador de intento no válido');
        }
    }
}
