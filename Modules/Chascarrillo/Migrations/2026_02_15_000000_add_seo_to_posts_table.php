<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return new class {
    public function up()
    {
        if (Capsule::schema()->hasTable('posts')) {
            Capsule::schema()->table('posts', function (Blueprint $table) {
                if (!Capsule::schema()->hasColumn('posts', 'meta_title')) {
                    $table->string('meta_title')->nullable()->after('slug');
                }
                if (!Capsule::schema()->hasColumn('posts', 'meta_description')) {
                    $table->string('meta_description')->nullable()->after('meta_title');
                }
                if (!Capsule::schema()->hasColumn('posts', 'meta_keywords')) {
                    $table->string('meta_keywords')->nullable()->after('meta_description');
                }
            });
        }
    }

    public function down()
    {
        if (Capsule::schema()->hasTable('posts')) {
            Capsule::schema()->table('posts', function (Blueprint $table) {
                $table->dropColumn(['meta_title', 'meta_description', 'meta_keywords']);
            });
        }
    }
};
