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
use Modules\Chascarrillo\Model\Menu;
use Alxarafe\Attribute\Menu as MenuAttr;

#[MenuAttr(
    menu: 'main_menu',
    label: 'Menús del Sitio',
    icon: 'fas fa-bars',
    order: 47,
    permission: 'Chascarrillo.Menu.doIndex'
)]
class MenuController extends ResourceController
{
    #[\Override]
    public static function getModuleName(): string
    {
        return 'Chascarrillo';
    }

    #[\Override]
    public static function getControllerName(): string
    {
        return 'Menu';
    }

    #[\Override]
    protected function getModelClass(): string
    {
        return Menu::class;
    }

    #[\Override]
    protected function getListColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
        ];
    }

    #[\Override]
    protected function getEditFields(): array
    {
        return [
            'id' => new \Alxarafe\Component\Fields\Text('id', 'ID', ['readonly' => true]),
            'name' => new \Alxarafe\Component\Fields\Text('name', 'Nombre'),
            'slug' => new \Alxarafe\Component\Fields\Text('slug', 'Slug'),
        ];
    }

    #[\Override]
    protected function beforeEdit()
    {
        $this->setDefaultTemplate('menu/edit');

        if ($this->recordId && $this->recordId !== 'new') {
            $menu = Menu::find($this->recordId);
            if ($menu instanceof Menu) {
                $data = $menu->toArray();
                $data['items'] = $menu->items()->get()->toArray();
                $this->addVariable('data', $data);
            }
        }
    }

    #[\Override]
    protected function saveRecord()
    {
        $id = $_POST['id'] ?? null;
        $data = $_POST['data'] ?? [];
        $items = $data['items'] ?? [];
        unset($data['items']);

        if ($id === 'new') {
            $menu = new Menu();
        } else {
            $menu = Menu::find($id);
        }

        if (!$menu) {
            $this->jsonResponse(['status' => 'error', 'message' => 'Menú no encontrado']);
            return;
        }

        $menu->fill($data);
        if ($menu instanceof Menu && $menu->save()) {
            // Sync items
            $existingIds = [];
            foreach ($items as $itemData) {
                $itemId = $itemData['id'] ?? null;
                if ($itemId) {
                    $item = \Modules\Chascarrillo\Model\MenuItem::find($itemId);
                } else {
                    $item = new \Modules\Chascarrillo\Model\MenuItem();
                    $item->menu_id = (int)$menu->id;
                }

                if ($item instanceof \Modules\Chascarrillo\Model\MenuItem) {
                    $item->fill($itemData);
                    $item->is_active = true;
                    $item->save();
                    $existingIds[] = $item->id;
                }
            }

            // Remove items not in the list
            \Modules\Chascarrillo\Model\MenuItem::where('menu_id', $menu->id)
                ->whereNotIn('id', $existingIds)
                ->delete();

            $this->jsonResponse(['status' => 'success', 'message' => 'Menú guardado correctamente']);
        } else {
            $this->jsonResponse(['status' => 'error', 'message' => 'Error al guardar el menú']);
        }
    }
}
