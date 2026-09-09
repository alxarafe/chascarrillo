<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use JsonException;
use RuntimeException;

/** Safe atomic state storage plus the process-lifetime local update lock. */
final class ReleaseUpdateStorage
{
    public const STATE_PATH = 'var/update/state.json';
    public const LOCK_PATH = 'var/update/update.lock';

    private SafePath $paths;

    /** @var resource|null */
    private $lockHandle = null;

    public function __construct(string $installRoot)
    {
        $this->paths = new SafePath($installRoot);
    }

    public function __destruct()
    {
        $this->release();
    }

    public function acquire(): void
    {
        if (is_resource($this->lockHandle)) {
            throw new RuntimeException('El bloqueo de actualización ya fue adquirido por este almacén');
        }
        $directory = $this->paths->ensureDirectory('var/update');
        $type = $this->paths->nodeType(self::LOCK_PATH);
        if (!in_array($type, [SafePath::NODE_MISSING, SafePath::NODE_FILE], true)) {
            throw new RuntimeException('El lock de actualización no es un archivo regular seguro');
        }
        $filename = $directory . '/update.lock';
        if ($type === SafePath::NODE_MISSING) {
            $handle = @fopen($filename, 'x+');
            if ($handle === false) {
                if ($this->paths->nodeType(self::LOCK_PATH) !== SafePath::NODE_FILE) {
                    throw new RuntimeException('No se pudo crear el lock de actualización de forma segura');
                }
                $handle = @fopen($filename, 'r+');
            }
        } else {
            $handle = @fopen($filename, 'r+');
        }
        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el lock de actualización');
        }
        try {
            $this->assertOpenedLock($handle, $filename);
            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('Ya hay una actualización en curso');
            }
            $this->assertOpenedLock($handle, $filename);
            $this->lockHandle = $handle;
        } catch (RuntimeException $exception) {
            fclose($handle);
            throw $exception;
        }
    }

    public function release(): void
    {
        if (!is_resource($this->lockHandle)) {
            return;
        }
        $handle = $this->lockHandle;
        $this->lockHandle = null;
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    public function load(): ?ReleaseUpdateState
    {
        $type = $this->paths->nodeType(self::STATE_PATH);
        if ($type === SafePath::NODE_MISSING) {
            return null;
        }
        if ($type !== SafePath::NODE_FILE) {
            throw new RuntimeException('El estado de actualización no es un archivo regular seguro');
        }
        try {
            $data = json_decode($this->paths->read(self::STATE_PATH), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('El JSON del estado de actualización está corrupto', 0, $exception);
        }
        if (!is_array($data)) {
            throw new RuntimeException('El estado de actualización no contiene un objeto JSON');
        }
        /** @var array<string,mixed> $data */
        return ReleaseUpdateState::fromArray($data);
    }

    public function write(ReleaseUpdateState $state): void
    {
        $type = $this->paths->nodeType(self::STATE_PATH);
        if (!in_array($type, [SafePath::NODE_MISSING, SafePath::NODE_FILE], true)) {
            throw new RuntimeException('El estado de actualización no es un archivo regular seguro');
        }
        $json = json_encode($state->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('No se pudo serializar el estado de actualización');
        }
        $this->paths->atomicWrite(self::STATE_PATH, $json . "\n");
        try {
            $verified = json_decode($this->paths->read(self::STATE_PATH), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('No se pudo verificar el estado escrito', 0, $exception);
        }
        if (!is_array($verified)) {
            throw new RuntimeException('No se pudo verificar el estado de actualización escrito');
        }
        /** @var array<string,mixed> $verified */
        ReleaseUpdateState::fromArray($verified);
    }

    /** @param resource $handle */
    private function assertOpenedLock($handle, string $filename): void
    {
        $opened = fstat($handle);
        $path = @lstat($filename);
        if ($opened === false || $path === false) {
            throw new RuntimeException('El lock de actualización cambió o no es un archivo regular seguro');
        }
        if (
            ($opened['mode'] & 0170000) !== 0100000
            || ($path['mode'] & 0170000) !== 0100000
            || $opened['dev'] !== $path['dev']
            || $opened['ino'] !== $path['ino']
        ) {
            throw new RuntimeException('El lock de actualización cambió o no es un archivo regular seguro');
        }
        $this->paths->requireFile(self::LOCK_PATH);
    }
}
