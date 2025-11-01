<?php

namespace PluginClassName\Support;

if (!defined('ABSPATH')) {
	exit;
}

class Logger
{
    protected static $logFile;

    public static function log($message, $level = 'info', array $context = [])
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $dir = WP_CONTENT_DIR . '/logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        self::$logFile = $dir . '/rrt.log';

        $timestamp = current_time('mysql');
        $prefix = strtoupper($level);
        $formatted = is_string($message) ? $message : print_r($message, true);

        if (!empty($context)) {
            $formatted .= ' | CONTEXT: ' . json_encode($context);
        }

        $line = "[$timestamp] [$prefix] $formatted" . PHP_EOL;
        error_log($line, 3, self::$logFile);
    }

    public static function info($message, array $context = []) {
        self::log($message, 'info', $context);
    }

    public static function error($message, array $context = []) {
        self::log($message, 'error', $context);
    }

    public static function debug($message, array $context = []) {
        self::log($message, 'debug', $context);
    }
}