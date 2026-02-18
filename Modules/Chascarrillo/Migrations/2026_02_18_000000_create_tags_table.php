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
        if (!Capsule::schema()->hasTable('tags')) {
            Capsule::schema()->create('tags', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('slug')->unique();
                $table->string('type')->default('tag'); // For separating categories and tags if needed
                $table->timestamps();
            });
        }

        if (!Capsule::schema()->hasTable('post_tag')) {
            Capsule::schema()->create('post_tag', function (Blueprint $table) {
                $table->id();
                $table->foreignId('post_id')->constrained('posts')->onDelete('cascade');
                $table->foreignId('tag_id')->constrained('tags')->onDelete('cascade');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Capsule::schema()->dropIfExists('post_tag');
        Capsule::schema()->dropIfExists('tags');
    }
};
