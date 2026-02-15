<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return new class {
    public function up(): void
    {
        if (Capsule::schema()->hasTable('posts')) {
            Capsule::schema()->table('posts', function (Blueprint $table) {
                $table->boolean('in_menu')->default(false)->after('type');
                $table->integer('menu_order')->default(0)->after('in_menu');
            });
        }
    }

    public function down(): void
    {
        if (Capsule::schema()->hasTable('posts')) {
            Capsule::schema()->table('posts', function (Blueprint $table) {
                $table->dropColumn(['in_menu', 'menu_order']);
            });
        }
    }
};
