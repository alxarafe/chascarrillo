<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Controller;

use Alxarafe\Base\Controller\Controller;
use Alxarafe\Attribute\Menu;
use Alxarafe\Lib\Functions;
use Alxarafe\Lib\Messages;
use Modules\Chascarrillo\Service\BackupService;

#[Menu(
    menu: 'main_menu',
    label: 'Backups',
    icon: 'fas fa-download',
    order: 110,
    permission: 'Chascarrillo.Backup.doIndex'
)]
#[Menu(
    menu: 'header_user',
    label: 'Importar/Exportar',
    icon: 'fas fa-download',
    order: 60,
    permission: 'Chascarrillo.Backup.doIndex'
)]
class BackupController extends Controller
{
    public function doIndex(): bool
    {
        $this->setDefaultTemplate('page/chascarrillo/backup/index');
        return true;
    }

    public function doExport(): void
    {
        try {
            $zipPath = BackupService::exportToZip();
            $filename = basename($zipPath);

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($zipPath));
            readfile($zipPath);
            unlink($zipPath);
            exit;
        } catch (\Throwable $t) {
            Messages::addError("Error al exportar: " . $t->getMessage());
            Functions::httpRedirect(static::url());
        }
    }

    public function doImport(): bool
    {
        if (!empty($_FILES['backup_zip']['tmp_name'])) {
            try {
                BackupService::importFromZip($_FILES['backup_zip']['tmp_name']);
                Messages::addMessage("Importación completada con éxito. Se recomienda sincronizar la base de datos ahora.");
            } catch (\Throwable $t) {
                Messages::addError("Error al importar: " . $t->getMessage());
            }
        } else {
            Messages::addError("Por favor, selecciona un archivo ZIP.");
        }

        Functions::httpRedirect(static::url());
        return true;
    }

    public function doResetFromContent(): bool
    {
        try {
            $results = BackupService::resetDbFromContent();
            Messages::addMessage("Base de datos reiniciada e importada desde Content. Procesados: " . ($results['posts']['processed'] + $results['pages']['processed']) . " posts/páginas.");
        } catch (\Throwable $t) {
            Messages::addError("Error al reiniciar desde Content: " . $t->getMessage());
        }

        Functions::httpRedirect(static::url());
        return true;
    }

    public function doRebuildContent(): bool
    {
        try {
            BackupService::rebuildContentFromDb();
            Messages::addMessage("Directorio Content reconstruido desde la base de datos.");
        } catch (\Throwable $t) {
            Messages::addError("Error al reconstruir Content: " . $t->getMessage());
        }

        Functions::httpRedirect('index.php?module=Chascarrillo&controller=Backup');
        return true;
    }
}
