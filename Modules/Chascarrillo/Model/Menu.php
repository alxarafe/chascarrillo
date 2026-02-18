<?php

declare(strict_types=1);

/*
 * Copyright (C) 2024-2026 Rafael San José <rsanjose@alxarafe.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Modules\Chascarrillo\Model;

use Alxarafe\Base\Model\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Menu extends Model
{
    protected $table = 'menus';

    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * @return HasMany
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     */
    public function items(): HasMany
    {
        /** @var \Illuminate\Database\Eloquent\Builder $query */
        $query = $this->hasMany(MenuItem::class);
        /** @phpstan-ignore-next-line */
        return $query->whereNull('parent_id')->orderBy('order');
    }

    public static function getBySlug(string $slug): ?self
    {
        /** @var \Illuminate\Database\Eloquent\Builder $query */
        $query = self::where('slug', $slug);
        return $query->first();
    }
}
