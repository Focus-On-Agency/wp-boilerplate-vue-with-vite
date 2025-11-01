<?php

namespace PluginClassName\Foundation\Migration;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base Migration con firma up()/down() in stile Laravel.
 */
abstract class Migration
{
    protected static string $table = '';

    abstract public function up(): void;

    abstract public function down(): void;

    public static function table(): string
    {
        return static::$table;
    }
}