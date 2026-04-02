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

namespace Modules\Chascarrillo\Domain\Model;

use DateTimeImmutable;

class Post
{
    private ?int $id = null;
    private string $title;
    private string $slug;
    private string $type;
    private string $content;
    private bool $isPublished;
    private ?DateTimeImmutable $publishedAt;
    private int $status;
    private ?string $featuredImage;
    private ?string $metaTitle;
    private ?string $metaDescription;
    private ?DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $updatedAt;

    public function __construct(
        string $title,
        string $slug,
        string $content = '',
        string $type = 'post',
        bool $isPublished = false,
        int $status = 0
    ) {
        $this->title = $title;
        $this->slug = $slug;
        $this->content = $content;
        $this->type = $type;
        $this->isPublished = $isPublished;
        $this->status = $status;
        $this->publishedAt = null;
        $this->featuredImage = null;
        $this->metaTitle = null;
        $this->metaDescription = null;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public static function fromArray(array $data): self
    {
        $post = new self(
            $data['title'],
            $data['slug'],
            $data['content'] ?? '',
            $data['type'] ?? 'post',
            (bool) ($data['is_published'] ?? false),
            (int) ($data['status'] ?? 0)
        );

        $post->id = isset($data['id']) ? (int) $data['id'] : null;
        if (!empty($data['published_at'])) {
            $post->publishedAt = new DateTimeImmutable($data['published_at']);
        }
        if (!empty($data['created_at'])) {
            $post->createdAt = new DateTimeImmutable($data['created_at']);
        }
        if (!empty($data['updated_at'])) {
            $post->updatedAt = new DateTimeImmutable($data['updated_at']);
        }

        $post->featuredImage = $data['featured_image'] ?? null;
        $post->metaTitle = $data['meta_title'] ?? null;
        $post->metaDescription = $data['meta_description'] ?? null;

        return $post;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'type' => $this->type,
            'content' => $this->content,
            'is_published' => $this->isPublished,
            'status' => $this->status,
            'published_at' => $this->publishedAt?->format('Y-m-d H:i:s'),
            'featured_image' => $this->featuredImage,
            'meta_title' => $this->metaTitle,
            'meta_description' => $this->metaDescription,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getSlug(): string { return $this->slug; }
    public function getType(): string { return $this->type; }
    public function getContent(): string { return $this->content; }
    public function isPublished(): bool { return $this->isPublished; }
    public function getStatus(): int { return $this->status; }
    public function getPublishedAt(): ?DateTimeImmutable { return $this->publishedAt; }
    public function getFeaturedImage(): ?string { return $this->featuredImage; }
    
    // Setters / Actions
    public function setId(int $id): void { $this->id = $id; }
    
    public function updateContent(string $title, string $slug, string $content): void
    {
        $this->title = $title;
        $this->slug = $slug;
        $this->content = $content;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function publish(): void
    {
        $this->isPublished = true;
        if (!$this->publishedAt) {
            $this->publishedAt = new DateTimeImmutable();
        }
        $this->status = 2; // Published
        $this->updatedAt = new DateTimeImmutable();
    }

    public function publishAt(DateTimeImmutable $date): void
    {
        $this->isPublished = true;
        $this->publishedAt = $date;
        $this->status = 1; // Validado / Scheduled
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getRenderedContent(): string
    {
        return \Alxarafe\Infrastructure\Service\MarkdownService::render($this->content ?? '');
    }

    public function getExcerpt(?int $limit = null): string
    {
        if ($limit === null) {
            $config = \Alxarafe\Infrastructure\Persistence\Config::getConfig();
            $limit = (int)($config->blog->excerpt_length ?? 140);
        }
        $text = strip_tags($this->content ?? '');
        return \Str::limit($text, $limit);
    }

    public function __get(string $name): mixed
    {
        // Handle snake_case to camelCase conversion
        $camelCase = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $name))));
        $method = 'get' . ucfirst($camelCase);
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        $methodIs = 'is' . ucfirst($camelCase);
        if (method_exists($this, $methodIs)) {
            return $this->$methodIs();
        }
        
        // Direct access fallback if getter doesn't exist but property does
        if (property_exists($this, $camelCase)) {
             return $this->$camelCase;
        }

        return null;
    }
}
