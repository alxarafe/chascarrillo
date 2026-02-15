<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return new class {
    public function up()
    {
        if (!Capsule::schema()->hasTable('posts')) {
            Capsule::schema()->create('posts', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->text('content')->nullable();
                $table->boolean('is_published')->default(false);
                $table->datetime('published_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Capsule::schema()->dropIfExists('posts');
    }
};
