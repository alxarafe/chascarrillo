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

namespace Modules\Chascarrillo\Application\Bus\Handler;

use Alxarafe\Application\Bus\CommandHandler;
use Alxarafe\Application\Bus\Command;
use Modules\Chascarrillo\Application\Bus\Command\CreatePostCommand;
use Modules\Chascarrillo\Domain\Model\Post;
use Modules\Chascarrillo\Domain\Port\Driven\PostRepositoryInterface;

class CreatePostHandler implements CommandHandler
{
    public function __construct(private PostRepositoryInterface $postRepository)
    {
    }

    /**
     * @param Command $command
     */
    public function handle(Command $command): mixed
    {
        /** @var CreatePostCommand $command */
        $post = new Post(
            $command->title,
            $command->slug,
            $command->content,
            $command->type,
            $command->isPublished,
            $command->status
        );

        $this->postRepository->save($post);

        return $post->getId();
    }
}
