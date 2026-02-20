<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Alxarafe\Base\Database;

return new class extends Migration
{
    public function up(): void
    {
        if (Database::schema()->hasTable('posts') && !Database::schema()->hasColumn('posts', 'menu_label')) {
            Database::schema()->table('posts', function (Blueprint $table) {
                $table->string('menu_label')->nullable()->after('in_menu');
            });
        }
    }

    public function down(): void
    {
        if (Database::schema()->hasTable('posts') && Database::schema()->hasColumn('posts', 'menu_label')) {
            Database::schema()->table('posts', function (Blueprint $table) {
                $table->dropColumn('menu_label');
            });
        }
    }
};
