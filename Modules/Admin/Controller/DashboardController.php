<?php

declare(strict_types=1);

/*
 * Copyright (C) 2024-2026 Rafael San José <rsanjose@alxarafe.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
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

namespace Modules\Admin\Controller;

use Alxarafe\Base\Controller\Controller;
use Alxarafe\Attribute\Menu;
use Modules\Chascarrillo\Model\Post;
use Modules\Chascarrillo\Model\Tag;

#[Menu(
    menu: 'main_menu',
    label: 'Dashboard',
    icon: 'fas fa-tachometer-alt',
    order: 0,
    permission: 'Admin.Dashboard.doIndex'
)]
class DashboardController extends Controller
{
    public function doIndex(): bool
    {
        $this->addVariable('title', 'Panel de Control');

        $stats = [
            'posts' => Post::where('type', 'post')->count(),
            'pages' => Post::where('type', 'page')->count(),
            'published_posts' => Post::where('type', 'post')->where('is_published', true)->count(),
            'tags' => Tag::where('type', 'tag')->count(),
        ];

        $recentPosts = Post::where('type', 'post')->orderBy('created_at', 'DESC')->limit(5)->get();

        $this->addVariable('stats', $stats);
        $this->addVariable('recent_posts', $recentPosts);
        $this->setDefaultTemplate('page/dashboard');

        return true;
    }

    #[\Override]
    public static function getModuleName(): string
    {
        return 'Admin';
    }

    #[\Override]
    public static function getControllerName(): string
    {
        return 'Dashboard';
    }
}
