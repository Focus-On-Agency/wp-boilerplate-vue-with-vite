<?php

namespace PluginClassName\Foundation\Http;

use RuntimeException;

if (!defined('ABSPATH')) { exit; }

class Response
{
    /** @var array|\WP_Error */
    protected $raw;
    protected string $url;
    protected array $args;

    public function __construct($raw, string $url, array $args)
    {
        $this->raw  = $raw;
        $this->url  = $url;
        $this->args = $args;
    }

    public function successful(): bool
    {
        return $this->ok();
    }

    public function ok(): bool
    {
        if (is_wp_error($this->raw)) return false;
        $code = wp_remote_retrieve_response_code($this->raw);
        return $code >= 200 && $code < 300;
    }

    public function failed(): bool
    {
        return !$this->ok();
    }

    public function status(): int
    {
        if (is_wp_error($this->raw)) return 0;

        return (int) wp_remote_retrieve_response_code($this->raw);
    }

    public function header(string $key, $default = null)
    {
        $headers = $this->headers();
        foreach ($headers as $k => $v) {
            if (strcasecmp($k, $key) === 0) return $v;
        }
        return $default;
    }

    public function headers(): array
    {
        if (is_wp_error($this->raw)) return [];
        $h = wp_remote_retrieve_headers($this->raw);
        // WP_HTTP_Headers implementa ArrayAccess, normalizziamo a array.
        return is_array($h) ? $h : (method_exists($h, 'getAll') ? $h->getAll() : (array) $h);
    }

    public function body(): ?string
    {
        if (is_wp_error($this->raw)) return null;
        return (string) wp_remote_retrieve_body($this->raw);
    }

    public function json(bool $assoc = true)
    {
        $body = $this->body();
        if ($body === null || $body === '') return $assoc ? [] : null;
        
        $decoded = json_decode($body, $assoc);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : ($assoc ? [] : null);
    }

    public function object()
    {
        return $this->json(false);
    }

    /** Lancia eccezione se la risposta non è 2xx (Laravel-like ->throw()) */
    public function throw(?callable $callback = null): self
    {
        if ($this->ok()) return $this;

        $ex = new class($this) extends RuntimeException {
            public Response $response;
            public function __construct(Response $response)
            {
                $this->response = $response;
                $msg = sprintf('HTTP request failed with status %d: %s',
                    $response->status(), substr((string) $response->body(), 0, 512)
                );
                parent::__construct($msg, $response->status());
            }
        };

        if ($callback) {
            $callback($this, $ex);
        }

        throw $ex;
    }

    /** Accesso alla risposta grezza WP (per casi speciali) */
    public function raw()
    {
        return $this->raw;
    }

    public function requestedUrl(): string
    {
        return $this->url;
    }

    public function requestedArgs(): array
    {
        return $this->args;
    }
}