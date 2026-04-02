<?php

declare(strict_types=1);

/*
 * Copyright (C) 2024-2026 Rafael San José <rsanjose@alxarafe.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace Modules\Chascarrillo\Domain\Port\Driven;

use Modules\Chascarrillo\Domain\Model\Post;

interface PostRepositoryInterface
{
    public function findById(int $id): ?Post;

    public function findBySlug(string $slug): ?Post;

    public function save(Post $post): void;

    public function delete(int $id): void;

    /**
     * @return Post[]
     */
    public function findAllPublished(): array;

    /**
     * @return Post[]
     */
    public function findByFilters(array $filters = []): array;
}
