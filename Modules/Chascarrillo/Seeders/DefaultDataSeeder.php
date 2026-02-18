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

namespace Modules\Chascarrillo\Seeders;

use Modules\Chascarrillo\Model\Tag;
use Modules\Chascarrillo\Model\Menu;
use Modules\Chascarrillo\Model\MenuItem;

class DefaultDataSeeder
{
    public function __construct()
    {
        $this->seed();
    }

    public function seed(): void
    {
        // categories
        Tag::updateOrCreate(['slug' => 'aplicaciones'], ['name' => 'Aplicaciones', 'type' => 'category']);
        Tag::updateOrCreate(['slug' => 'trabajos-anteriores'], ['name' => 'Trabajos Anteriores', 'type' => 'category']);

        // tags
        Tag::updateOrCreate(['slug' => 'microframework'], ['name' => 'Microframework', 'type' => 'tag']);
        Tag::updateOrCreate(['slug' => 'php'], ['name' => 'PHP', 'type' => 'tag']);
        Tag::updateOrCreate(['slug' => 'laravel'], ['name' => 'Laravel', 'type' => 'tag']);

        // Main Menu
        $mainMenu = Menu::updateOrCreate(['slug' => 'header-menu'], ['name' => 'Menú Cabecera']);

        if ($mainMenu instanceof Menu) {
            MenuItem::updateOrCreate(['menu_id' => $mainMenu->id, 'label' => 'Inicio'], [
                'url' => 'index.php',
                'order' => 1,
            ]);

            MenuItem::updateOrCreate(['menu_id' => $mainMenu->id, 'label' => 'Blog'], [
                'url' => 'index.php?module=Chascarrillo&controller=Blog',
                'order' => 2,
            ]);
        }

        echo "Seeded default Tags, Categories and Menus\n";
    }
}
