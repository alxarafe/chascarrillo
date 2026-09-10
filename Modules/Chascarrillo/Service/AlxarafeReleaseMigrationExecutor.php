<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use Alxarafe\Infrastructure\Persistence\Config;
use Alxarafe\Infrastructure\Persistence\Database;
use Illuminate\Database\Capsule\Manager as Capsule;
use Modules\Admin\Model\Migration as MigrationRecord;
use RuntimeException;

/** Executes the migration format used by the installed Alxarafe version, one file at a time. */
final class AlxarafeReleaseMigrationExecutor implements ReleaseMigrationExecutor
{
    /** @var array<string,array{path:string,hash:string}> */
    private array $planned = [];

    private ?int $batch = null;

    public function pending(string $releaseRoot): array
    {
        $this->planned = $this->discover($releaseRoot);
        if ($this->planned === []) {
            return [];
        }
        $this->connect();
        $applied = array_fill_keys($this->applied(), true);
        $pending = [];
        foreach ($this->planned as $migration => $data) {
            if (!isset($applied[$migration])) {
                $pending[] = $migration;
            }
        }
        return $pending;
    }

    public function prepare(string $installRoot, array $migrations): void
    {
        $paths = new SafePath($installRoot);
        foreach ($migrations as $migration) {
            if (!isset($this->planned[$migration])) {
                throw new RuntimeException('El plan de migraciones cambió después del preflight');
            }
            $relative = $this->planned[$migration]['path'];
            if ($paths->hash($relative) !== $this->planned[$migration]['hash']) {
                throw new RuntimeException("La migración {$migration} cambió después del preflight");
            }
        }
    }

    public function execute(string $migration, string $installRoot): void
    {
        if (!isset($this->planned[$migration])) {
            throw new RuntimeException('La migración solicitada no pertenece al plan verificado');
        }
        $paths = new SafePath($installRoot);
        $relative = $this->planned[$migration]['path'];
        if ($paths->hash($relative) !== $this->planned[$migration]['hash']) {
            throw new RuntimeException("La migración {$migration} cambió después del preflight");
        }
        if ($this->batch === null) {
            $this->batch = MigrationRecord::getLastBatch() + 1;
        }
        $instance = require $paths->requireFile($relative);
        if (!is_object($instance) || !method_exists($instance, 'up')) {
            throw new RuntimeException("La migración {$migration} no expone up()");
        }
        $instance->up();
        MigrationRecord::create([
            'migration' => $migration,
            'batch' => $this->batch,
        ]);
    }

    public function assertApplied(array $migrations): void
    {
        if ($migrations === []) {
            return;
        }
        $applied = array_fill_keys($this->applied(), true);
        foreach ($migrations as $migration) {
            if (!isset($applied[$migration])) {
                throw new RuntimeException("La migración {$migration} no quedó registrada");
            }
        }
    }

    private function connect(): void
    {
        $config = Config::getConfig();
        if ($config === null || !isset($config->db)) {
            throw new RuntimeException('No hay una conexión de base de datos configurada');
        }
        Database::createConnection($config->db);
    }

    /** @return list<string> */
    private function applied(): array
    {
        if (!Capsule::schema()->hasTable('migrations')) {
            return [];
        }
        $values = MigrationRecord::query()->pluck('migration')->all();
        return array_values(array_filter($values, 'is_string'));
    }

    /** @return array<string,array{path:string,hash:string}> */
    private function discover(string $releaseRoot): array
    {
        $paths = new SafePath($releaseRoot);
        $roots = [
            'Modules',
            'vendor/alxarafe/alxarafe/src/Modules',
        ];
        $found = [];
        foreach ($roots as $root) {
            if ($paths->nodeType($root) === SafePath::NODE_MISSING) {
                continue;
            }
            $paths->requireDirectory($root);
            foreach ($paths->entries($root) as $module) {
                if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $module) !== 1) {
                    continue;
                }
                $directory = "{$root}/{$module}/Migrations";
                if ($paths->nodeType($directory) === SafePath::NODE_MISSING) {
                    continue;
                }
                $paths->requireDirectory($directory);
                foreach ($paths->entries($directory) as $filename) {
                    if (preg_match('/^[A-Za-z0-9_]+\.php$/', $filename) !== 1) {
                        continue;
                    }
                    $name = substr($filename, 0, -4) . '@' . $module;
                    if (isset($found[$name])) {
                        continue;
                    }
                    $relative = "{$directory}/{$filename}";
                    $paths->requireFile($relative);
                    $found[$name] = [
                        'path' => $relative,
                        'hash' => $paths->hash($relative),
                    ];
                }
            }
        }
        ksort($found, SORT_STRING);
        return $found;
    }
}
