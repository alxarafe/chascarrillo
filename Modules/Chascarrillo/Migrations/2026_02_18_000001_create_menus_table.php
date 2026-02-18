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

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return new class {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Capsule::schema()->hasTable('menus')) {
            Capsule::schema()->create('menus', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->timestamps();
            });
        }

        if (!Capsule::schema()->hasTable('menu_items')) {
            Capsule::schema()->create('menu_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('menu_id')->constrained('menus')->onDelete('cascade');
                $table->foreignId('parent_id')->nullable()->constrained('menu_items')->onDelete('cascade');
                $table->string('label');
                $table->string('url')->nullable();
                $table->string('icon')->nullable();
                $table->integer('order')->default(0);
                $table->string('target')->default('_self');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Capsule::schema()->dropIfExists('menu_items');
        Capsule::schema()->dropIfExists('menus');
    }
};
