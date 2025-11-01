<?php

namespace PluginClassName\Foundation;

use PluginClassName\Foundation\Migration\Migration;

if (!defined('ABSPATH')) { exit; }

class Migrator
{
    protected string $path;
    protected string $namespace;

    // Option keys
    protected const LEDGER_OPTION = 'rrt_fson_migrations_ledger';
    protected const LOCK_OPTION   = 'rrt_fson_migrations_lock';

    public function __construct(
        string $pathToMigrations,
        string $migrationsNamespace = 'PluginClassName\\Database\\Migrations'
    ) {
        $this->path      = rtrim($pathToMigrations, '/');
        $this->namespace = rtrim($migrationsNamespace, '\\');
    }

    /** Esegue tutte le migration pendenti (in ordine) */
    public function runPending(): array
    {
        // if (!$this->acquireLock()) { return []; }

        try {
            // [ filePath => Migration instance ]
            $allMap = $this->discover();

            // Ledger contiene gli ID = basename(file, '.php')
            $ranIds = $this->ledgerAll();

            // Filtra solo i file non ancora eseguiti
            $pending = [];
            foreach ($allMap as $file => $instance) {
                $id = pathinfo($file, PATHINFO_FILENAME);
                if (!in_array($id, $ranIds, true)) {
                    $pending[$file] = $instance;
                }
            }

            $executed = [];
            foreach ($pending as $file => $migration) {
                if (!$migration instanceof Migration) {
                    throw new \RuntimeException("Invalid migration object returned by {$file}");
                }

                $migration->up();

                $id = pathinfo($file, PATHINFO_FILENAME);
                $this->ledgerAdd($id);
                $executed[] = $id;
            }

            return $executed;
        } catch (\Throwable $e) {
            $this->releaseLock();
            error_log($e->getMessage());
            return [];
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Rollback degli ultimi N file eseguiti (in ordine inverso).
     * Selezioniamo gli ultimi N ID dal ledger seguendo L’ORDINE DEI FILE presenti su disco.
     */
    public function rollback(int $steps = 1): array
    {
        // Ordine canonico = ordine file system (sort alfabetico)
        $files = $this->listFiles();
        $ran   = $this->ledgerAll(); // lista di ID (basename senza .php)

        // Mappa file -> id e tieni SOLO quelli presenti nel ledger, nell’ordine dei file
        $ranFilesOrdered = [];
        foreach ($files as $file) {
            $id = pathinfo($file, PATHINFO_FILENAME);
            if (in_array($id, $ran, true)) {
                $ranFilesOrdered[] = $file;
            }
        }

        // Prendi gli ultimi N ed esegui down() in ordine inverso
        $toRollback = array_reverse(array_slice($ranFilesOrdered, -$steps));

        $rolled = [];
        foreach ($toRollback as $file) {
            $instance = $this->requireMigration($file);
            if (!$instance instanceof Migration) {
                throw new \RuntimeException("Invalid migration object returned by {$file}");
            }

            $instance->down();

            $id = pathinfo($file, PATHINFO_FILENAME);
            $this->ledgerRemove($id);
            $rolled[] = $id;
        }

        return $rolled;
    }

    /* ---------- discover + helpers ---------- */

    /**
     * Restituisce la mappa [ filePath => Migration instance ]
     * Supporta:
     *  - file che fanno `return new class extends Migration { ... }`
     *  - retrocompat: file che dichiarano una classe nominale (senza return)
     */
    protected function discover(): array
    {
        $files = $this->listFiles();
        $map = [];

        foreach ($files as $file) {
            $instance = $this->requireMigration($file);

            // Se il file non ha fatto `return`, provo retrocompat (classe nominale)
            if (!$instance) {
                $base  = basename($file, '.php');

                // Provo a rimuovere i prefissi timestamp/contatori e converto in StudlyCase
                $parts = explode('_', $base);
                // tipicamente i primi 4 sono timestamp, adatta se il tuo naming differisce
                $name  = implode('_', array_slice($parts, 4)) ?: $base;
                $class = $this->namespace . '\\' . $this->studly($name);

                if (class_exists($class)) {
                    $instance = new $class();
                }
            }

            if (!$instance instanceof Migration) {
                // Se non è una migration valida, la salto (o lancia eccezione se preferisci)
                continue;
            }

            $map[$file] = $instance;
        }

        return $map;
    }

    protected function listFiles(): array
    {
        $files = glob($this->path . '/*.php') ?: [];
        sort($files);
        return $files;
    }

    protected function requireMigration(string $file): ?object
    {
        /** @var mixed $ret */
        $ret = require $file; // se il file fa `return new class ...`, $ret È l’istanza
        return is_object($ret) ? $ret : null;
    }

    protected function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    /* ---------- ledger (wp_options) ---------- */

    /** @return string[] lista di ID (basename file senza .php) */
    protected function ledgerAll(): array
    {
        $raw  = get_option(self::LEDGER_OPTION, '[]');
        $list = json_decode(is_string($raw) ? $raw : '[]', true) ?: [];
        return array_values(array_unique(array_filter($list, 'is_string')));
    }

    /** $id è il basename del file senza .php */
    protected function ledgerAdd(string $id): void
    {
        $list = $this->ledgerAll();
        if (!in_array($id, $list, true)) {
            $list[] = $id;
            update_option(self::LEDGER_OPTION, wp_json_encode($list), false);
        }
    }

    protected function ledgerRemove(string $id): void
    {
        $list = array_values(array_diff($this->ledgerAll(), [$id]));
        update_option(self::LEDGER_OPTION, wp_json_encode($list), false);
    }

    /* ---------- lock (wp_options) ---------- */

    protected function acquireLock(int $ttl = 120): bool
    {
        return add_option(self::LOCK_OPTION, time() + $ttl, '', 'no');
    }

    protected function releaseLock(): void
    {
        delete_option(self::LOCK_OPTION);
    }
}