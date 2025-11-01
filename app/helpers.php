<?php

if (!function_exists('class_basename')) {
	/**
	 * Get the class name from a fully qualified class name.
	 */
    function class_basename($class)
    {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }
}

if (!function_exists('dispatch')) {
    /**
     * @param class-string<BaseJob> $jobClass
     */
    function dispatch(string $jobClass, array $payload = [], int $delaySeconds = 0): void
    {
        $jobClass::dispatch($payload, $delaySeconds);
    }
}

if(!function_exists('get_public_key')) {
    function get_public_key(): string
    {
        return defined('PluginClassName_PUBLIC_KEY') ? PluginClassName_PUBLIC_KEY : null;
    }
}

if (!function_exists('encrypt_value')) {
    function encrypt_value(string $plain): string
    {
        $k = base64_decode(get_public_key());
        if (!$k) {
            throw new \RuntimeException('Public key is not defined.');
        }

        $n = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $c = sodium_crypto_secretbox(serialize($plain), $n, $k);

        return base64_encode($n.$c);
    }
}

if (!function_exists('decrypt_value')) {
    function decrypt_value(string $encrypted): ?string
    {
        $k = base64_decode(get_public_key());
        if (!$k) {
            throw new \RuntimeException('Public key is not defined.');
        }

        $decoded = base64_decode($encrypted);
        $n = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $c = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $p = sodium_crypto_secretbox_open($c, $n, $k);

        if (!is_string($p)) {
            return null;
        }

        return unserialize($p);
    }
}

if (!function_exists('base64url_decode')) {
    function base64url_decode($s) {
        $s = strtr($s, '-_', '+/');
        return base64_decode($s . str_repeat('=', 3 - (3 + strlen($s)) % 4));
    }
}