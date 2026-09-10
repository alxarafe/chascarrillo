<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class UpdateReconciliationCommand
{
    private const EXIT_OK = 0;
    private const EXIT_RECOVERY_REQUIRED = 20;
    private const EXIT_CONFLICT = 21;
    private const EXIT_AMBIGUOUS = 22;
    private const EXIT_CHANGED = 30;
    private const EXIT_LOCKED = 31;
    private const EXIT_USAGE = 64;
    private const EXIT_SAFETY = 65;

    public function __construct(private readonly string $installRoot)
    {
    }

    /** @param list<string> $arguments */
    public function run(array $arguments): int
    {
        if ($arguments === [] || in_array($arguments[0], ['-h', '--help', 'help'], true)) {
            $this->printUsage();
            return self::EXIT_OK;
        }

        try {
            $operation = array_shift($arguments);
            $options = $this->parseOptions($arguments);
            $json = ($options['json'] ?? false) === true;
            if ($operation === 'inspect') {
                return $this->inspect($options, $json);
            }
            if ($operation !== 'apply') {
                throw new InvalidArgumentException('Operación desconocida');
            }
            return $this->apply($options, $json);
        } catch (InvalidArgumentException $exception) {
            fwrite(STDERR, 'error: ' . $exception->getMessage() . PHP_EOL);
            $this->printUsage(STDERR);
            return self::EXIT_USAGE;
        } catch (Throwable $exception) {
            $message = $this->sanitizeMessage($exception);
            fwrite(STDERR, 'error: ' . $message . PHP_EOL);
            if (str_contains(strtolower($message), 'huella')) {
                return self::EXIT_CHANGED;
            }
            if (str_contains(strtolower($message), 'curso')) {
                return self::EXIT_LOCKED;
            }
            return self::EXIT_SAFETY;
        }
    }

    /** @param array<string,string|bool> $options */
    private function inspect(array $options, bool $json): int
    {
        foreach (['resolution', 'expect'] as $forbidden) {
            if (isset($options[$forbidden])) {
                throw new InvalidArgumentException("inspect no admite --{$forbidden}");
            }
        }
        $attempt = $options['attempt'] ?? null;
        if ($attempt !== null && !is_string($attempt)) {
            throw new InvalidArgumentException('--attempt no es válido');
        }
        $plan = (new UpdateReconciliationInspector($this->installRoot))->inspect($attempt);
        $this->render($plan->toArray(), $json);
        return $this->classificationExit($plan->classification());
    }

    /** @param array<string,string|bool> $options */
    private function apply(array $options, bool $json): int
    {
        foreach (['attempt', 'resolution', 'expect'] as $required) {
            if (!isset($options[$required]) || !is_string($options[$required])) {
                throw new InvalidArgumentException("apply requiere --{$required}");
            }
        }
        $record = (new UpdateReconciliationService($this->installRoot))->apply(
            $options['attempt'],
            $options['resolution'],
            $options['expect']
        );
        $this->render([
            'attempt_id' => $record['attempt_id'],
            'classification' => $record['classification'],
            'resolution' => $record['resolution'],
            'fingerprint' => $record['fingerprint'],
        ], $json);
        return self::EXIT_OK;
    }

    /** @param list<string> $arguments @return array<string,string|bool> */
    private function parseOptions(array $arguments): array
    {
        $options = [];
        foreach ($arguments as $argument) {
            if ($argument === '--json') {
                if (isset($options['json'])) {
                    throw new InvalidArgumentException('Opción repetida: --json');
                }
                $options['json'] = true;
                continue;
            }
            if (preg_match('/^--([a-z]+)=(.+)$/', $argument, $matches) !== 1) {
                throw new InvalidArgumentException('Opción no válida');
            }
            $name = $matches[1];
            if (!in_array($name, ['attempt', 'resolution', 'expect'], true) || isset($options[$name])) {
                throw new InvalidArgumentException('Opción desconocida o repetida');
            }
            $options[$name] = $matches[2];
        }
        return $options;
    }

    /** @param array<string,mixed> $result */
    private function render(array $result, bool $json): void
    {
        if ($json) {
            $encoded = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                throw new RuntimeException('No se pudo serializar la salida');
            }
            fwrite(STDOUT, $encoded . PHP_EOL);
            return;
        }
        fwrite(STDOUT, 'Intento: ' . $result['attempt_id'] . PHP_EOL);
        fwrite(STDOUT, 'Clasificación: ' . $result['classification'] . PHP_EOL);
        fwrite(STDOUT, 'Resolución autorizada: ' . ($result['resolution'] ?? 'ninguna') . PHP_EOL);
        fwrite(STDOUT, 'Huella: ' . $result['fingerprint'] . PHP_EOL);
    }

    /** @param resource $stream */
    private function printUsage($stream = STDOUT): void
    {
        fwrite(
            $stream,
            "Uso:\n"
            . "  php scripts/reconcile_update.php inspect [--attempt=<id>] [--json]\n"
            . "  php scripts/reconcile_update.php apply --attempt=<id> "
            . "--resolution=<retry|rollback|completion> --expect=<sha256> [--json]\n"
        );
    }

    private function sanitizeMessage(Throwable $exception): string
    {
        $message = str_replace(rtrim($this->installRoot, '/'), '[install-root]', $exception->getMessage());
        $message = preg_replace(
            '/\b(password|passwd|secret|token|dsn)\s*[:=]\s*[^\s]+/i',
            '$1=[redacted]',
            $message
        ) ?? 'Operación rechazada de forma segura';
        $message = preg_replace('#/(?:[^/\s]+/)+[^/\s]+#', '[path]', $message)
            ?? 'Operación rechazada de forma segura';
        $message = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $message)
            ?? 'Operación rechazada de forma segura';
        $message = trim(preg_replace('/\s+/', ' ', $message) ?? $message);
        return $message === '' ? 'Operación rechazada de forma segura' : substr($message, 0, 300);
    }

    private function classificationExit(string $classification): int
    {
        return match ($classification) {
            UpdateReconciliationPlan::RECOVERY_REQUIRED => self::EXIT_RECOVERY_REQUIRED,
            UpdateReconciliationPlan::CONFLICT => self::EXIT_CONFLICT,
            UpdateReconciliationPlan::AMBIGUOUS => self::EXIT_AMBIGUOUS,
            default => self::EXIT_OK,
        };
    }
}
