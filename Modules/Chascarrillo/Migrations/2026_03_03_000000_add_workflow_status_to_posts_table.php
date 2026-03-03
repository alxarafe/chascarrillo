<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return new class {
    public function up()
    {
        if (Capsule::schema()->hasTable('posts')) {
            Capsule::schema()->table('posts', function (Blueprint $table) {
                if (!Capsule::schema()->hasColumn('posts', 'status')) {
                    $table->integer('status')->default(0)->after('is_published');
                }
            });
        }
    }

    public function down()
    {
        if (Capsule::schema()->hasTable('posts')) {
            Capsule::schema()->table('posts', function (Blueprint $table) {
                if (Capsule::schema()->hasColumn('posts', 'status')) {
                    $table->dropColumn('status');
                }
            });
        }
    }
};
