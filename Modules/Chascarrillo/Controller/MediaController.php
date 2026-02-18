<?php

declare(strict_types=1);

/*
 * Copyright (C) 2026 Rafael San José <rsanjose@alxarafe.com>
 */

namespace Modules\Chascarrillo\Controller;

use Alxarafe\Base\Controller\ResourceController;
use Modules\Chascarrillo\Model\Media;
use Alxarafe\Attribute\Menu;
use Alxarafe\Component\Fields\Text;
use Alxarafe\Component\Fields\Textarea;
use Alxarafe\Component\Fields\Select;
use Alxarafe\Lib\Messages;
use Alxarafe\Lib\Functions;

#[Menu(
    menu: 'main_menu',
    label: 'Multimedia',
    icon: 'fas fa-photo-video',
    order: 45,
    permission: 'Chascarrillo.Media.doIndex'
)]
class MediaController extends ResourceController
{
    #[\Override]
    public static function getModuleName(): string
    {
        return 'Chascarrillo';
    }

    #[\Override]
    public static function getControllerName(): string
    {
        return 'Media';
    }

    #[\Override]
    protected function getModelClass(): string
    {
        return Media::class;
    }

    #[\Override]
    protected function setup()
    {
        parent::setup();
        $this->addListButton(
            'sync_media',
            'Sincronizar Archivos',
            'fas fa-sync',
            'info',
            'right',
            'url',
            'index.php?module=Chascarrillo&controller=Media&method=sync'
        );
    }

    #[\Override]
    protected function getListColumns(): array
    {
        return [
            'preview' => [
                'label' => 'Vista Previa',
                'callback' => function ($row) {
                    if ($row->type === 'image') {
                        return '<img src="' . $row->getUrl() . '" style="height: 50px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
                    }
                    return '<i class="fas fa-file-video fa-2x text-muted"></i>';
                }
            ],
            'filename' => 'Nombre',
            'type' => [
                'label' => 'Tipo',
                'callback' => fn($row) => ucfirst($row->type)
            ],
            'url' => [
                'label' => 'URL',
                'callback' => fn($row) => '<code>' . $row->getUrl() . '</code>'
            ],
            'created_at' => 'Fecha'
        ];
    }

    #[\Override]
    protected function getEditFields(): array
    {
        return [
            'id' => new Text('id', 'ID', ['readonly' => true]),
            'filename' => new Text('filename', 'Nombre del archivo', ['readonly' => true]),
            'type' => new Select('type', 'Tipo', ['image' => 'Imagen', 'video' => 'Vídeo']),
            'alt_text' => new Text('alt_text', 'Texto Alternativo (SEO)'),
            'description' => new Textarea('description', 'Descripción', ['rows' => 3]),
            'path' => new Text('path', 'Ruta Relativa', ['readonly' => true]),
            'size' => new Text('size', 'Tamaño (Bytes)', ['readonly' => true]),
        ];
    }

    public function doSync(): bool
    {
        $basePath = defined('BASE_PATH') ? constant('BASE_PATH') : __DIR__ . '/../../../public_html';
        $contentBase = dirname($basePath) . '/Content';

        $results = [
            'images' => $this->syncAssetDir($contentBase . '/images', 'image', $basePath . '/uploads/images'),
            'videos' => $this->syncAssetDir($contentBase . '/videos', 'video', $basePath . '/uploads/videos'),
        ];

        Messages::addMessage("Sincronización multimedia completada: " . ($results['images'] + $results['videos']) . " archivos procesados.");
        Functions::httpRedirect(static::url());
        return true;
    }

    private function syncAssetDir(string $sourceDir, string $type, string $targetDir): int
    {
        if (!is_dir($sourceDir)) {
            return 0;
        }

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $files = glob($sourceDir . '/*.*');
        $count = 0;
        foreach ($files as $file) {
            $filename = basename($file);
            $targetPath = $targetDir . '/' . $filename;

            // Sync to disk
            if (!file_exists($targetPath) || filemtime($file) > filemtime($targetPath)) {
                copy($file, $targetPath);
            }

            // Sync to Database
            $relativePath = $type . 's/' . $filename;
            $media = Media::where('path', $relativePath)->first();
            if (!$media) {
                $media = new Media();
                $media->path = $relativePath;
            }

            $media->filename = $filename;
            $media->type = $type;
            $media->size = filesize($file);
            $media->mime_type = mime_content_type($file) ?: null;
            $media->save();
            $count++;
        }
        return $count;
    }
}
