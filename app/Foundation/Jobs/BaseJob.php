<?php

namespace PluginClassName\Foundation\Jobs;

use PluginClassName\Foundation\Queue;

if (!defined('ABSPATH')) {
	exit;
}

abstract class BaseJob
{
    // Ogni job deve definire queste COSTANTI ↓
    public const ACTION = '';
    public const QUEUE  = 'default';
    public const MAX_ATTEMPTS = 5;

    // Backoff esponenziale: 60,120,240,480...
    public static function backoffSeconds(int $attempt): int
    {
        $n = max(1, $attempt - 1);
        return MINUTE_IN_SECONDS * (2 ** ($n - 1));
    }

    protected static function shouldRunSync(array $payload, int $delay): bool
    {
        return (bool) apply_filters('fson_rrt_queue_sync', false, static::ACTION, $payload, static::QUEUE, $delay);
    }

    protected static function isSync(): bool
    {
        // Costante o filtro, come preferisci
        return (bool) apply_filters('fson_rrt_force_sync_jobs', $flag, static::class, null, null);
    }

    /** Registra l'hook del job */
    public static function register(): void
    {
        add_action(static::ACTION, [static::class, 'handleWrapper'], 10, 1);
    }

    public static function dispatchNow(array $payload = [], bool $useWrapper = false): void
    {
        $payload['_attempt'] = (int)($payload['_attempt'] ?? 1);

        if ($useWrapper) {
            // NB: handleWrapper cattura le eccezioni; per vederle in debug,
            // puoi cambiare handleWrapper a rilanciarle quando FSON_RRT_QUEUE_SYNC=true.
            static::handleWrapper($payload);
            return;
        }

        // Esecuzione "nuda": solleva eventuali eccezioni subito.
        static::handle($payload);
    }

    public static function dispatch(array $payload = [], int $delay = 0): void
    {
        $payload['_attempt'] = (int)($payload['_attempt'] ?? 1);

        if (static::shouldRunSync($payload, $delay)) {
            // Per debug ha più senso far esplodere subito (niente retry)
            static::handle($payload);
            return;
        }

        Queue::instance()->dispatch(static::ACTION, $payload, $delay, static::QUEUE);
    }

    /** Wrapper con retry/backoff */
    public static function handleWrapper(array $payload): void
    {
        $attempt = (int)($payload['_attempt'] ?? 1);
        $ok = false;
        $error = null;

        try {
            static::handle($payload);
            $ok = true;
        } catch (\Throwable $e) {
            $error = $e;
            $ok = false;
        }

        if (!$ok && $attempt < static::MAX_ATTEMPTS) {
            $payload['_attempt'] = $attempt + 1;
            $delay = static::backoffSeconds($attempt + 1);
            Queue::instance()->dispatch(static::ACTION, $payload, $delay, static::QUEUE);
            return;
        }

        if (!$ok && $error) {
            error_log('[JobFail '.static::class.'] '.$error->getMessage());
        }
    }

    abstract public static function handle(array $payload): void;
    abstract public static function validatePayload(array $input, array $rules, array $messages = []): void;
}