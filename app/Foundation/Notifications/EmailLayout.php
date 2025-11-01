<?php

namespace PluginClassName\Foundation\Notifications;

if (!defined('ABSPATH')) exit;

class EmailLayout
{
    // relative path dentro tema/child e nel plugin
    public const RELATIVE = 'resources/emails/base.html';

    protected static $cache;

    /** Restituisce il markup del layout (stringa) */
    public static function load(): string
    {
        if (isset(self::$cache)) return self::$cache;

        // 1) Tema/child: child-first, poi parent
        $themeFile = locate_template([self::RELATIVE], false, false);
        if (is_string($themeFile) && $themeFile && file_exists($themeFile)) {
            return self::$cache = file_get_contents($themeFile) ?: '';
        }

        // 2) Fallback plugin: .../resources/email/base.html
        $pluginRoot = dirname(__DIR__, 3);
        $pluginFile = $pluginRoot . '/' . self::RELATIVE;

        if (file_exists($pluginFile)) {
            return self::$cache = file_get_contents($pluginFile) ?: '';
        }

        return self::$cache = '{{{ content }}}';
    }
}
