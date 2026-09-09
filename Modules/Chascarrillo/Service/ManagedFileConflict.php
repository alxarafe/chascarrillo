<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

final class ManagedFileConflict
{
    public const MODIFIED_MANAGED = 'managed_file_modified';
    public const MODIFIED_OBSOLETE = 'obsolete_managed_file_modified';
    public const UNMANAGED_COLLISION = 'unmanaged_file_collision';
    public const UNEXPECTED_NODE = 'unexpected_node_type';
    public const SYMBOLIC_LINK = 'symbolic_link';
    public const UNSAFE_CACHE_NODE = 'unsafe_blade_cache_node';

    public function __construct(
        public readonly string $path,
        public readonly string $classification,
        public readonly ?string $previousHash,
        public readonly ?string $actualHash,
        public readonly ?string $newHash
    ) {
    }

    public function describe(): string
    {
        $classification = $this->classification === self::SYMBOLIC_LINK
            ? 'symbolic_link (enlace simbólico)'
            : $this->classification;
        return sprintf(
            '%s [%s] anterior=%s actual=%s nuevo=%s',
            $this->path,
            $classification,
            $this->previousHash ?? '-',
            $this->actualHash ?? '-',
            $this->newHash ?? '-'
        );
    }
}
