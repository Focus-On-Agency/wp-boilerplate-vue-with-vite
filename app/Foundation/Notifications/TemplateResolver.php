<?php

namespace PluginClassName\Foundation\Notifications;

use DateTimeImmutable;

if (!defined('ABSPATH')) exit;

class TemplateResolver
{
    public static function render(string $html, array $ctx): string
    {
        // 1) Tripla graffa (RAW)
        $html = preg_replace_callback('/\{\{\{\s*(.*?)\s*\}\}\}/s', function ($m) use ($ctx) {
            [$val,] = self::resolveExpr($m[1], $ctx); // senza escape
            return is_scalar($val) ? (string)$val : json_encode($val);
        }, $html);

        // 2) Doppia graffa (ESCAPED)
        return preg_replace_callback('/\{\{\s*(.*?)\s*\}\}/s', function ($m) use ($ctx) {
            [$val,] = self::resolveExpr($m[1], $ctx);
            return esc_html(is_scalar($val) ? (string)$val : json_encode($val));
        }, $html);
    }

    protected static function resolveExpr(string $exprRaw, array $ctx): array
    {
        $expr = self::normalizeExpr($exprRaw);
        $parts = preg_split('/\s*\?\?\s*/', $expr, 2);
        $left = trim($parts[0]);
        $fallback = isset($parts[1]) ? self::cleanQuotes(trim($parts[1])) : '';
        
        $path = $left;
        $pos = strpos($left, ':');
        if ($pos !== false) {
            $path = trim(substr($left, 0, $pos));
        }
        
        $val = self::dataGet($ctx, $path);
        if ($val === null || $val === '') {
            return [$fallback, true];
        }

        return [$val, false];
    }

    /** Pulisce entità/tag/nbsp dentro l'espressione tra {{ ... }} */
    protected static function normalizeExpr(string $expr): string
    {
        // rimuovi eventuali tag HTML finiti dentro al token
        $expr = strip_tags($expr);

        // decodifica entità (&nbsp;, &#160;, &quot;…)
        $expr = html_entity_decode($expr, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // sostituisci NBSP unicode con spazio
        $expr = str_replace("\xc2\xa0", ' ', $expr); // U+00A0

        // rimuovi spazi extra
        $expr = preg_replace('/\s+/u', ' ', $expr);

        return trim($expr);
    }

    protected static function dataGet(array $data, string $path)
    {
        foreach (explode('.', $path) as $seg) {
            if (!is_array($data) || !array_key_exists($seg, $data)) {
                return null;
            }
            $data = $data[$seg];
        }
        return $data;
    }


    protected static function cleanQuotes(string $s): string
    {
        if ((str_starts_with($s, '"') && str_ends_with($s, '"')) ||
            (str_starts_with($s, "'") && str_ends_with($s, "'"))) {
            return substr($s, 1, -1);
        }
        return $s;
    }
}