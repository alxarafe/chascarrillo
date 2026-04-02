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

namespace Modules\Chascarrillo\Infrastructure\Adapter\Persistence;

use Alxarafe\Domain\Port\Driven\PersistencePort;
use Modules\Chascarrillo\Domain\Model\Post;
use Modules\Chascarrillo\Domain\Port\Driven\PostRepositoryInterface;

class PdoPostRepository implements PostRepositoryInterface
{
    private const TABLE = 'posts';

    public function __construct(private PersistencePort $db)
    {
    }

    public function findById(int $id): ?Post
    {
        $row = $this->db->findById(self::TABLE, $id);
        return $row ? Post::fromArray($row) : null;
    }

    public function findBySlug(string $slug): ?Post
    {
        $rows = $this->db->findBy(self::TABLE, ['slug' => $slug]);
        return !empty($rows) ? Post::fromArray($rows[0]) : null;
    }

    public function save(Post $post): void
    {
        $data = $post->toArray();
        if ($post->getId() === null) {
            unset($data['id']);
            $id = $this->db->insert(self::TABLE, $data);
            $post->setId((int) $id);
        } else {
            $this->db->update(self::TABLE, $post->getId(), $data);
        }
    }

    public function delete(int $id): void
    {
        $this->db->delete(self::TABLE, $id);
    }

    /**
     * @return Post[]
     */
    public function findAllPublished(): array
    {
        $rows = $this->db->findBy(self::TABLE, ['is_published' => 1, 'type' => 'post']);
        return array_map(fn($row) => Post::fromArray($row), $rows);
    }

    /**
     * @return Post[]
     */
    public function findByFilters(array $filters = []): array
    {
        // Simple mapping, can be expanded for complex queries via PersistencePort
        $rows = $this->db->findBy(self::TABLE, $filters);
        return array_map(fn($row) => Post::fromArray($row), $rows);
    }
}
