<?php

namespace PluginClassName\Foundation\Console;

if (!defined('ABSPATH')) { exit; }

/**
 * WP-CLI: wp fson make:migration <name> [--table=<table>] [--column=<column>]
 *
 * Esempi:
 *  wp fson make:migration create_allergens_table
 *  wp fson make:migration add_icon_to_allergens_table
 *  wp fson make:migration fix_allergens_indexes
 */
class MakeMigration
{
    public function __invoke(array $args, array $assoc_args): void
    {
        [$name] = $args + [null];
        if (!$name) {
            \WP_CLI::error('Provide a migration name. E.g. create_allergens_table');
        }

        $namespace  = 'PluginClassName\\Database\\Migrations';
        $rootDir    = defined('PluginClassName_DIR')
            ? PluginClassName_DIR
            : plugin_dir_path(__FILE__) . '../../..' . '/'; // fallback
        $migrationsDir = $rootDir . 'database/Migrations/';
        $stubsDir      = $rootDir . 'stubs/migrations/';

        if (!is_dir($migrationsDir) && !wp_mkdir_p($migrationsDir)) {
            \WP_CLI::error("Cannot create migrations dir: {$migrationsDir}");
        }

        // timestamp stile Laravel
        $ts = current_time('timestamp', true); // UTC
        $stamp = gmdate('Y_m_d_His', $ts);

        $class = $this->studly($name);
        $file  = "{$migrationsDir}{$stamp}_{$name}.php";

        // Scegli lo stub
        $stubFile = $stubsDir . 'generic.php.stub';
        $table = $assoc_args['table'] ?? '';
        $column = $assoc_args['column'] ?? '';

        if (preg_match('/^create_(.+)_table$/i', $name, $m)) {
            $table = $m[1];
            $stubFile = $stubsDir . 'create.php.stub';
        } elseif (preg_match('/^add_(.+)_to_(.+)_table$/i', $name, $m)) {
            // add_<column>_to_<table>_table
            $column = $m[1];
            $table  = $m[2];
            $stubFile = $stubsDir . 'add.php.stub';
        } else {
            // se passati, usa --table/--column nello stub generic (potresti non usarli)
        }

        if (!file_exists($stubFile)) {
            \WP_CLI::error("Stub not found: {$stubFile}");
        }

        $stub = file_get_contents($stubFile);

        $replaced = strtr($stub, [
            '{{NAMESPACE}}' => $namespace,
            '{{CLASS}}'     => $class,
            '{{TABLE}}'     => $table,
            '{{COLUMN}}'    => $column,
            '{{TIMESTAMP}}' => $stamp,
        ]);

        if (file_put_contents($file, $replaced) === false) {
            \WP_CLI::error("Failed writing file: {$file}");
        }

        \WP_CLI::success("Migration created: {$file}");
    }

    private function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = ucwords($value);
        return str_replace(' ', '', $value);
    }
}