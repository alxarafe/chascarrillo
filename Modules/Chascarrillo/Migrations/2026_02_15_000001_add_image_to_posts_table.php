<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return new class {
    public function up()
    {
        if (Capsule::schema()->hasTable('posts')) {
            Capsule::schema()->table('posts', function (Blueprint $table) {
                if (!Capsule::schema()->hasColumn('posts', 'featured_image')) {
                    $table->string('featured_image')->nullable()->after('meta_keywords');
                }
            });
        }
    }

    public function down()
    {
        if (Capsule::schema()->hasTable('posts')) {
            Capsule::schema()->table('posts', function (Blueprint $table) {
                $table->dropColumn('featured_image');
            });
        }
    }
};
