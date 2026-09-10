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
        return $this->prepareAndApply($installRoot, $releaseTag, static fn (): string => $releaseRoot);
    }

    /**
     * @param callable():string $prepareRelease Returns the extracted release root.
     * @return array{copied:int,removed:int,preserved:int,cache_removed:int,assets:array<string,mixed>}
     */
    public function prepareAndApply(string $installRoot, ?string $releaseTag, callable $prepareRelease): array
    {
        $storage = new ReleaseUpdateStorage($installRoot);
        $storage->acquire();
        $attempt = null;
        $releaseRoot = null;
        try {
            $previous = $storage->load();
            $recovery = $this->classifyPreviousAttempt($storage, $previous, $installRoot);
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
            $result = $this->installer->prepareInstallation(
                $releaseRoot,
                $installRoot,
                $releaseTag,
                $observer,
                $attempt->attemptId()
            );
            $attempt = $attempt->advance(ReleaseUpdateState::PHASE_MIGRATIONS, true);
            $storage->write($attempt);
            if (!(($this->migrate)())) {
                throw new RuntimeException(
                    'La actualización de archivos terminó, pero fallaron las migraciones. Revise el registro.'
                );
            }
            $this->installer->validatePreparedInstallation();
            $attempt = $attempt->advance(ReleaseUpdateState::PHASE_PROMOTING_MANIFEST, true);
            $storage->write($attempt);
            $this->installer->promotePreparedManifest();
            $completed = $attempt->complete();
            $storage->write($completed);
            try {
                $this->installer->markRecoveryCompleted();
            } catch (Throwable) {
                // Completed is authoritative; evidence cleanup/annotation is best effort only.
            }
            return $result;
        } catch (Throwable $exception) {
            if ($attempt instanceof ReleaseUpdateState && $attempt->isInProgress()) {
                if ($this->canRollbackFilesystem($attempt)) {
                    try {
                        $journal = FilesystemRecoveryJournal::open(
                            new SafePath($installRoot),
                            $attempt->attemptId()
                        );
                        $journal->rollback();
                        $this->refreshDerivedEffects($installRoot);
                    } catch (Throwable $rollbackException) {
                        $attempt = $attempt->rollbackFailed(
                            $rollbackException::class,
                            $this->sanitizedMessage($rollbackException, $releaseRoot, $installRoot)
                        );
                        $storage->write($attempt);
                        throw new RuntimeException(
                            'Falló el rollback de filesystem; se requiere recuperación administrativa.',
                            0,
                            $rollbackException
                        );
                    }
                    $rolledBack = $attempt->filesystemRolledBack(
                        $exception::class,
                        $this->sanitizedMessage($exception, $releaseRoot, $installRoot)
                    );
                    $storage->write($rolledBack);
                    $attempt = $rolledBack;
                } else {
                    $attempt = $attempt->fail(
                        $exception::class,
                        $this->sanitizedMessage($exception, $releaseRoot, $installRoot)
                    );
                    $storage->write($attempt);
                }
            }
            throw $exception;
        } finally {
            $this->installer->discardPreparedManifest();
            $storage->release();
        }
    }

    /** @return array{attempt_id:string,status:string}|null */
    private function classifyPreviousAttempt(
        ReleaseUpdateStorage $storage,
        ?ReleaseUpdateState $previous,
        string $installRoot
    ): ?array {
        if ($previous === null || $previous->status() === ReleaseUpdateState::STATUS_COMPLETED) {
            return null;
        }
        if ($previous->status() === ReleaseUpdateState::STATUS_FILESYSTEM_ROLLED_BACK) {
            return ['attempt_id' => $previous->attemptId(), 'status' => $previous->status()];
        }
        if ($previous->status() === ReleaseUpdateState::STATUS_ROLLBACK_FAILED) {
            throw new RuntimeException(
                'El rollback anterior falló; se requiere recuperación administrativa.'
            );
        }
        if ($previous->isInProgress() && $this->canRollbackFilesystem($previous)) {
            try {
                $journal = FilesystemRecoveryJournal::open(new SafePath($installRoot), $previous->attemptId());
                $journal->rollback();
                $this->refreshDerivedEffects($installRoot);
            } catch (Throwable $exception) {
                $previous = $previous->rollbackFailed(
                    $exception::class,
                    $this->sanitizedMessage($exception, null, $installRoot)
                );
                $storage->write($previous);
                throw new RuntimeException(
                    'No se pudo reconciliar la actualización interrumpida; se requiere recuperación administrativa.',
                    0,
                    $exception
                );
            }
            $rolledBack = $previous->filesystemRolledBack();
            $storage->write($rolledBack);
            return ['attempt_id' => $rolledBack->attemptId(), 'status' => $rolledBack->status()];
        }
        if ($previous->isInProgress()) {
            $previous = $previous->interrupt();
            $storage->write($previous);
        }
        if ($previous->mutationsStarted()) {
            throw new RuntimeException(
                'La actualización anterior pudo alcanzar migraciones; se requiere intervención administrativa.'
            );
        }
        return ['attempt_id' => $previous->attemptId(), 'status' => $previous->status()];
    }

    private function canRollbackFilesystem(ReleaseUpdateState $state): bool
    {
        return $state->mutationsStarted() && in_array(
            $state->phase(),
            [ReleaseUpdateState::PHASE_INSTALLING_FILES, ReleaseUpdateState::PHASE_PUBLISHING_CLEANUP],
            true
        );
    }

    private function refreshDerivedEffects(string $installRoot): void
    {
        $this->installer->clearBladeCache($installRoot);
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
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
