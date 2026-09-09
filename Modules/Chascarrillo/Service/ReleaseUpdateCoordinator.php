<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use Alxarafe\Infrastructure\Persistence\Config;
use Closure;
use RuntimeException;
use Throwable;

final class ReleaseUpdateCoordinator
{
    /** @var Closure():bool */
    private readonly Closure $migrate;

    /** @param (callable():bool)|null $migrate */
    public function __construct(
        private readonly ReleaseInstaller $installer = new ReleaseInstaller(),
        ?callable $migrate = null
    ) {
        $this->migrate = $migrate === null
            ? static fn (): bool => Config::doRunMigrations()
            : Closure::fromCallable($migrate);
    }

    /** @return array{copied:int,removed:int,preserved:int,cache_removed:int,assets:array<string,mixed>} */
    public function apply(string $releaseRoot, string $installRoot, ?string $releaseTag = null): array
    {
        return $this->prepareAndApply(
            $installRoot,
            $releaseTag,
            static fn (): string => $releaseRoot
        );
    }

    /**
     * @param callable():string $prepareRelease Returns the extracted release root.
     * @return array{copied:int,removed:int,preserved:int,cache_removed:int,assets:array<string,mixed>}
     */
    public function prepareAndApply(
        string $installRoot,
        ?string $releaseTag,
        callable $prepareRelease
    ): array {
        $storage = new ReleaseUpdateStorage($installRoot);
        $storage->acquire();
        $attempt = null;
        $releaseRoot = null;
        try {
            $previous = $storage->load();
            $recovery = $this->classifyPreviousAttempt($storage, $previous);
            $attempt = ReleaseUpdateState::start(
                ApplicationVersion::canonical(),
                $this->targetVersion($releaseTag),
                $recovery['attempt_id'] ?? null,
                $recovery['status'] ?? null
            );
            $storage->write($attempt);
            $releaseRoot = $prepareRelease();

            $observer = function (string $phase, bool $mutationsStarted) use ($storage, &$attempt): void {
                $attempt = $attempt->advance($phase, $mutationsStarted);
                $storage->write($attempt);
            };
            $result = $this->installer->install($releaseRoot, $installRoot, $releaseTag, $observer);
            $attempt = $attempt->advance(ReleaseUpdateState::PHASE_MIGRATIONS, true);
            $storage->write($attempt);
            if (!(($this->migrate)())) {
                throw new RuntimeException(
                    'La actualización de archivos terminó, pero fallaron las migraciones. Revise el registro.'
                );
            }
            $completed = $attempt->complete();
            $storage->write($completed);
            return $result;
        } catch (Throwable $exception) {
            if ($attempt instanceof ReleaseUpdateState && $attempt->isInProgress()) {
                $attempt = $attempt->fail(
                    $exception::class,
                    $this->sanitizedMessage($exception, $releaseRoot, $installRoot)
                );
                $storage->write($attempt);
            }
            throw $exception;
        } finally {
            $storage->release();
        }
    }

    /** @return array{attempt_id:string,status:string}|null */
    private function classifyPreviousAttempt(
        ReleaseUpdateStorage $storage,
        ?ReleaseUpdateState $previous
    ): ?array {
        if ($previous === null || $previous->status() === ReleaseUpdateState::STATUS_COMPLETED) {
            return null;
        }
        if ($previous->isInProgress()) {
            $previous = $previous->interrupt();
            $storage->write($previous);
        }
        if ($previous->mutationsStarted()) {
            throw new RuntimeException(
                'La actualización anterior pudo modificar la instalación; se requiere intervención administrativa.'
            );
        }
        return [
            'attempt_id' => $previous->attemptId(),
            'status' => $previous->status(),
        ];
    }

    private function targetVersion(?string $releaseTag): string
    {
        if ($releaseTag === null || $releaseTag === '') {
            return ApplicationVersion::canonical();
        }
        $version = str_starts_with($releaseTag, 'v') ? substr($releaseTag, 1) : $releaseTag;
        try {
            ApplicationVersion::assertValid($version, 'La versión objetivo');
            return $version;
        } catch (RuntimeException) {
            return ApplicationVersion::canonical();
        }
    }

    private function sanitizedMessage(Throwable $exception, ?string $releaseRoot, string $installRoot): string
    {
        $search = [rtrim($installRoot, '/')];
        $replace = ['[install-root]'];
        if ($releaseRoot !== null && $releaseRoot !== '') {
            $search[] = rtrim($releaseRoot, '/');
            $replace[] = '[release-root]';
        }
        $message = str_replace($search, $replace, $exception->getMessage());
        $message = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $message) ?? 'Error de actualización';
        $message = trim(preg_replace('/\s+/', ' ', $message) ?? $message);
        if ($message === '') {
            $message = 'Error de actualización sin detalle';
        }
        return substr($message, 0, 500);
    }
}
