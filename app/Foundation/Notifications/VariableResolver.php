<?php

namespace PluginClassName\Foundation\Notifications;

use DateTime;
use DateTimeInterface;

if (!defined('ABSPATH')) exit;

class VariableResolver
{
    public static function definitions(): array
    {
        return VariablesLoader::all();
    }

    /** Ritorna: ['{{ booking.id }}' => '123', '{{ booking.date:d M Y }}' => '01 Gen 2024', ...] */
    public static function resolveFlat(string $event, array|object $context): array
    {
        $defs = self::definitions()[$event] ?? [];
        $out  = [];

        foreach ($defs as $key => $_meta) {
            [$path, $fmt] = self::splitKey($key);
            $value = self::getByPath($context, $path);
            $value = self::applyFormat($value, $fmt);
            $out[self::token($key)] = $value;
        }
        return $out;
    }

    /** Se ti serve l’array annidato come quello che costruivi con getParamsNotify */
    public static function resolveNested(string $event, array|object $context): array
    {
        $defs = self::definitions()[$event] ?? [];
        $out  = [];

        foreach (array_keys($defs) as $key) {
            [$path, $fmt] = self::splitKey($key);
            $value = self::applyFormat(self::getByPath($context, $path), $fmt);
            self::setByPath($out, $path, $value);
        }
        return $out;
    }

    /* ----------------- helpers ----------------- */

    private static function splitKey(string $key): array
    {
        $parts = explode(':', $key, 2);
        return [$parts[0], $parts[1] ?? null]; // [path, format]
    }

    private static function token(string $key): string
    {
        return '{{ ' . $key . ' }}';
    }

    private static function getByPath(array|object $src, string $path): mixed
    {
        $cur = $src;
        foreach (explode('.', $path) as $seg) {
            if (is_array($cur) && array_key_exists($seg, $cur)) {
                $cur = $cur[$seg];
            } elseif (is_object($cur) && isset($cur->{$seg})) {
                $cur = $cur->{$seg};
            } else {
                return ''; // fallback safe
            }
        }
        return $cur;
    }

    private static function setByPath(array &$dst, string $path, mixed $value): void
    {
        $ref =& $dst;
        $segs = explode('.', $path);
        foreach ($segs as $i => $seg) {
            if ($i === count($segs) - 1) {
                $ref[$seg] = $value;
            } else {
                if (!isset($ref[$seg]) || !is_array($ref[$seg])) $ref[$seg] = [];
                $ref =& $ref[$seg];
            }
        }
    }

    private static function applyFormat(mixed $value, ?string $fmt): mixed
    {
        if ($fmt === null) return $value;
        if ($value === null || $value === '') return '';

        $withSiteLocale = function (callable $cb) {
            $switched = false;
            if (function_exists('switch_to_locale')) {
                $switched = switch_to_locale( get_locale() );
            }
            try {
                return $cb();
            } finally {
                if ($switched && function_exists('restore_previous_locale')) {
                    restore_previous_locale();
                }
            }
        };

        // per date/time (string o DateTime)
        try {
            $dt = $value instanceof DateTimeInterface ? $value : new DateTime((string)$value);
            return $withSiteLocale(fn() => date_i18n($fmt, $dt->getTimestamp()));
        } catch (\Throwable) {
            // altri formatter futuri (es. upper/lower) li puoi gestire qui con sintassi tipo "upper"
            return (string)$value;
        }
    }
}