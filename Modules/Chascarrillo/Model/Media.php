<?php

declare(strict_types=1);

/*
 * Copyright (C) 2026 Rafael San José <rsanjose@alxarafe.com>
 */

namespace Modules\Chascarrillo\Model;

use Alxarafe\Base\Model\Model;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'filename',
        'type',
        'mime_type',
        'path',
        'size',
        'alt_text',
        'description',
    ];

    public function getUrl(): string
    {
        return '/uploads/' . ltrim($this->path, '/');
    }
}
