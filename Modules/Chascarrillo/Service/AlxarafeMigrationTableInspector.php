<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use Alxarafe\Infrastructure\Persistence\Config;
use Alxarafe\Infrastructure\Persistence\Database;
use Illuminate\Database\Capsule\Manager as Capsule;
use Modules\Admin\Model\Migration as MigrationRecord;
use RuntimeException;

final class AlxarafeMigrationTableInspector
{
    /** @return array{table_exists:bool,migrations:list<string>} */
    public function snapshot(): array
    {
        $config = Config::getConfig();
        if ($config === null || !isset($config->db)) {
            throw new RuntimeException('No hay una conexión de base de datos configurada');
        }
        Database::createConnection($config->db);
        if (!Capsule::schema()->hasTable('migrations')) {
            return ['table_exists' => false, 'migrations' => []];
        }
        $values = MigrationRecord::query()->pluck('migration')->all();
        $migrations = array_values(array_filter($values, 'is_string'));
        sort($migrations, SORT_STRING);
        return ['table_exists' => true, 'migrations' => $migrations];
    }
}
