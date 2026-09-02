<?php

declare(strict_types=1);

/*
 * Copyright (C) 2026 Rafael San José <rsanjose@alxarafe.com>
 */

namespace Modules\Chascarrillo\Controller;

use Alxarafe\Infrastructure\Http\Controller\ResourceController;
use Modules\Chascarrillo\Model\Media;
use Alxarafe\Infrastructure\Attribute\Menu;
use Alxarafe\ResourceController\Component\Fields\Text;
use Alxarafe\ResourceController\Component\Fields\Textarea;
use Alxarafe\ResourceController\Component\Fields\Select;
use Alxarafe\Infrastructure\Lib\Messages;
use Alxarafe\Infrastructure\Lib\Functions;

#[Menu(
    menu: 'main_menu',
    label: 'Multimedia',
    icon: 'fas fa-photo-video',
    order: 48,
    permission: 'Chascarrillo.Media.doIndex'
)]
class MediaController extends ResourceController
{
    public static function getModuleName(): string
    {
        return 'Chascarrillo';
    }

    
    public static function getControllerName(): string
    {
        return 'Media';
    }

    /**
     * @return void
     */
    
    protected function beforeList(): void
    {
        $this->setDefaultTemplate('media/index');
        $this->addVariable('media', Media::orderBy('created_at', 'DESC')->get());
    }

    
    protected function getModelClassName(): string
    {
        return Media::class;
    }

    
    protected function setup(): void
    {
        parent::setup();
        $this->addListButton(
            'sync_media',
            'Sincronizar Archivos',
            'fas fa-sync',
            'info',
            'right',
            'url',
            '/index.php?module=Chascarrillo&controller=Media&method=sync'
        );
    }

    
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
        $count = \Modules\Chascarrillo\Service\SyncService::syncImportFiles();

        Messages::addMessage("Sincronización multimedia completada: " . $count . " archivos procesados.");
        Functions::httpRedirect(static::url());
        return true;
    }
}
