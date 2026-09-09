<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use RuntimeException;

final class ReleaseFileOperation
{
    public const COPY = 'copy';
    public const REMOVE = 'remove';

    public function __construct(
        public readonly string $action,
        public readonly string $path,
        public readonly string $expectedNodeType,
        public readonly ?string $expectedHash,
        public readonly ?string $newHash
    ) {
    }

    public function assertPrecondition(SafePath $paths): void
    {
        $actualType = $paths->nodeType($this->path);
        if ($actualType !== $this->expectedNodeType) {
            throw new RuntimeException("El destino cambió durante la actualización: {$this->path}");
        }
        if (
            $actualType === SafePath::NODE_FILE
            && ($this->expectedHash === null || $paths->hash($this->path) !== $this->expectedHash)
        ) {
            throw new RuntimeException("El destino cambió durante la actualización: {$this->path}");
        }
    }
}
