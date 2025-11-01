<?php

namespace PluginClassName\Foundation\Notifications;

if (!defined('ABSPATH')) exit;

class VariablesLoader
{
    protected static $cache;

    public static function all(): array
    {
        if (isset(self::$cache)) return self::$cache;

        $base = require __DIR__ . '/variables.php';

        return self::$cache = $base;
    }

    public static function allTemplates(): array
    {
        return array_keys(self::all());
    }

    public static function for(string $templateKey): array
    {
        $all = self::all();
        return $all[$templateKey] ?? [];
    }
}
