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

namespace Modules\Chascarrillo\Application\Bus\Command;

use Alxarafe\Application\Bus\Command;

readonly class CreatePostCommand implements Command
{
    public function __construct(
        public string $title,
        public string $slug,
        public string $content,
        public string $type = 'post',
        public bool $isPublished = false,
        public int $status = 0
    ) {
    }
}
