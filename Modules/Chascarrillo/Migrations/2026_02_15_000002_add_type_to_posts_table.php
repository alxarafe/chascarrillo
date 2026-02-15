<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return new class {
    public function up(): void
    {
        if (Capsule::schema()->hasTable('posts')) {
            Capsule::schema()->table('posts', function (Blueprint $table) {
                $table->string('type', 20)->default('post')->after('slug');
                $table->index('type');
            });
        }
    }

    public function down(): void
    {
        if (Capsule::schema()->hasTable('posts')) {
            Capsule::schema()->table('posts', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
};
