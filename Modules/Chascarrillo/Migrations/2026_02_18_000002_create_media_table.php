<?php

declare(strict_types=1);

/*
 * Copyright (C) 2026 Rafael San José <rsanjose@alxarafe.com>
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class
{
    public function up(): void
    {
        if (!Capsule::schema()->hasTable('media')) {
            Capsule::schema()->create('media', function (Blueprint $table) {
                $table->id();
                $table->string('filename');
                $table->string('type'); // 'image', 'video', 'document'
                $table->string('mime_type')->nullable();
                $table->string('path'); // relative to public_html/uploads
                $table->bigInteger('size')->unsigned()->default(0);
                $table->text('alt_text')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('media');
    }
};
