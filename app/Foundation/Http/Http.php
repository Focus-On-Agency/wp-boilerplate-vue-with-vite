<?php

namespace PluginClassName\Foundation\Http;

if (!defined('ABSPATH')) { exit; }

final class Http
{
    private function __construct() {}

    /** Avvia una nuova richiesta pendente (stile Laravel Http::) */
    public static function client(): PendingRequest
    {
        return new PendingRequest();
    }

    // Aliases comodi, come in Laravel:
    public static function withHeaders(array $headers): PendingRequest
    {
        return self::client()->withHeaders($headers);
    }

    public static function withToken(string $token, string $type = 'Bearer'): PendingRequest
    {
        return self::client()->withToken($token, $type);
    }

    public static function acceptJson(): PendingRequest
    {
        return self::client()->acceptJson();
    }

    public static function asJson(): PendingRequest
    {
        return self::client()->asJson();
    }

    public static function asForm(): PendingRequest
    {
        return self::client()->asForm();
    }

    public static function baseUrl(string $baseUrl): PendingRequest
    {
        return self::client()->baseUrl($baseUrl);
    }
}