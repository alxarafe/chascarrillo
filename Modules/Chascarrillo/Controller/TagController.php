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

namespace Modules\Chascarrillo\Controller;

use Alxarafe\Base\Controller\ResourceController;
use Modules\Chascarrillo\Model\Tag;
use Alxarafe\Attribute\Menu;

#[Menu(
    menu: 'main_menu',
    label: 'Tags y Categorías',
    icon: 'fas fa-tags',
    order: 42,
    permission: 'Chascarrillo.Tag.doIndex'
)]
class TagController extends ResourceController
{
    #[\Override]
    public static function getModuleName(): string
    {
        return 'Chascarrillo';
    }

    #[\Override]
    public static function getControllerName(): string
    {
        return 'Tag';
    }

    #[\Override]
    protected function getModelClass(): string
    {
        return Tag::class;
    }

    #[\Override]
    protected function getListColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'type' => [
                'type' => 'text',
                'label' => 'Tipo'
            ],
        ];
    }

    #[\Override]
    protected function getEditFields(): array
    {
        return [
            'id' => new \Alxarafe\Component\Fields\Text('id', 'ID', ['readonly' => true]),
            'name' => new \Alxarafe\Component\Fields\Text('name', 'Nombre'),
            'slug' => new \Alxarafe\Component\Fields\Text('slug', 'Slug'),
            'type' => new \Alxarafe\Component\Fields\Select('type', 'Tipo', [
                'tag' => 'Tag',
                'category' => 'Categoría'
            ]),
        ];
    }
}
