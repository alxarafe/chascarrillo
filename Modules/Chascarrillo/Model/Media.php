<?php

declare(strict_types=1);

/*
 * Copyright (C) 2026 Rafael San José <rsanjose@alxarafe.com>
 */

namespace Modules\Chascarrillo\Model;

use Alxarafe\Base\Model\Model;

/**
 * Class Media
 * 
 * @property int $id
 * @property string $filename
 * @property string $type
 * @property string|null $mime_type
 * @property string $path
 * @property int $size
 * @property string|null $alt_text
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
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
