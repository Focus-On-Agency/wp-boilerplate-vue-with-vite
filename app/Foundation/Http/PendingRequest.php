<?php

namespace PluginClassName\Foundation\Http;

use Closure;
use PluginClassName\Support\LicenseService;

if (!defined('ABSPATH')) { exit; }

class PendingRequest
{
    protected string $baseUrl       = '';
    protected array  $headers       = [];
    protected array  $query         = [];
    protected array  $options       = []; // WP HTTP args override
    protected bool   $verify        = true;
    protected int    $timeout       = 10;
    protected int    $redirection   = 3;

    protected string $bodyFormat    = 'json'; // json|form|raw|multipart
    protected mixed  $rawBody       = null;   // per raw()
    protected array  $multipart     = [];     // attach() raccolte

    // Retry config
    protected int    $tries         = 1;
    protected int    $sleepMs       = 0;
    /** @var null|Closure($responseOrWpError,int):bool */
    protected ?Closure $retryWhen   = null;

    // --------- CONFIG (chainable) ---------

    public function baseUrl(string $baseUrl): self
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        return $this;
    }

    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function withHeader(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function withToken(string $token, string $type = 'Bearer'): self
    {
        return $this->withHeader('Authorization', trim($type.' '.$token));
    }

    public function withBasicAuth(string $username, string $password): self
    {
        return $this->withHeader('Authorization', 'Basic '.base64_encode($username.':'.$password));
    }

    public function acceptJson(): self
    {
        return $this->withHeader('Accept', 'application/json');
    }

    public function asJson(): self
    {
        $this->bodyFormat = 'json';
        $this->withHeader('Content-Type', 'application/json');
        return $this;
    }

    public function asForm(): self
    {
        $this->bodyFormat = 'form';
        $this->withHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        return $this;
    }

    /** Imposta body raw + content-type custom (es. text/plain, application/pdf con stream) */
    public function withBody(string $content, string $contentType = 'text/plain'): self
    {
        $this->bodyFormat = 'raw';
        $this->rawBody = $content;
        $this->withHeader('Content-Type', $contentType);
        return $this;
    }

    public function withQuery(array $query): self
    {
        $this->query = array_merge($this->query, $query);
        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->timeout = max(1, $seconds);
        return $this;
    }

    public function withoutVerifying(): self
    {
        $this->verify = false;
        return $this;
    }

    /** Pass-through verso WP HTTP args (blocking, stream, filename, cookies, ecc.) */
    public function withOptions(array $wpHttpArgs): self
    {
        $this->options = array_merge($this->options, $wpHttpArgs);
        return $this;
    }

    /**
     * Allegati multipart (Laravel-like).
     * $contents può essere: string path file | string contenuto | resource stream.
     */
    public function attach(string $name, $contents, ?string $filename = null, array $headers = []): self
    {
        $this->bodyFormat = 'multipart';
        $this->multipart[] = compact('name', 'contents', 'filename', 'headers');
        return $this;
    }

    /** Retry con backoff fisso (sleepMs) o custom con $when($responseOrError,$attempt):bool */
    public function retry(int $times, int $sleepMs = 0, ?Closure $when = null): self
    {
        $this->tries     = max(1, $times);
        $this->sleepMs   = max(0, $sleepMs);
        $this->retryWhen = $when;
        return $this;
    }

    // --------- VERBI HTTP ---------

    public function get(string $url, array $query = []): Response
    {
        if ($query) $this->withQuery($query);
        return $this->send('GET', $url, null);
    }

    public function post(string $url, $data = []): Response
    {
        return $this->send('POST', $url, $data);
    }

    public function put(string $url, $data = []): Response
    {
        return $this->send('PUT', $url, $data);
    }

    public function patch(string $url, $data = []): Response
    {
        return $this->send('PATCH', $url, $data);
    }

    public function delete(string $url, $data = []): Response
    {
        return $this->send('DELETE', $url, $data);
    }

    // --------- CORE SEND ---------

    protected function send(string $method, string $url, $data = null): Response
    {
        $uri = $this->buildUrl($url);
        $args = $this->buildArgs($method, $data);

        $attempt = 0;
        $response = null;

        do {
            $attempt++;
            $response = wp_remote_request($uri, $args);

            if (!$this->shouldRetry($response, $attempt)) {
                break;
            }

            // rispetta Retry-After se presente
            $retryAfterMs = $this->extractRetryAfterMs($response) ?? $this->sleepMs;
            if ($retryAfterMs > 0) {
                usleep($retryAfterMs * 1000);
            }
        } while ($attempt < $this->tries);

        return new Response($response, $uri, $args);
    }

    protected function buildUrl(string $url): string
    {
        $isAbsolute = (bool) preg_match('~^https?://~i', $url);
        $full = $isAbsolute ? $url : ($this->baseUrl ? $this->baseUrl.'/'.ltrim($url, '/') : $url);

        if (!empty($this->query)) {
            $sep  = (str_contains($full, '?') ? '&' : '?');
            $full = $full . $sep . http_build_query($this->query, '', '&', PHP_QUERY_RFC3986);
        }

        return $full;
    }

    protected function buildArgs(string $method, $data): array
    {
        $headers = $this->headers;

        $headers['Origin'] = $headers['Origin'] ?? home_url();
        $headers['X-Site'] = home_url();
        $headers['X-Plugin-Version'] = PluginClassName_VERSION;
        $headers['X-INSTANCE'] = get_option(LicenseService::OPTION_INSTANCE_UUID, 'unknown');

        $body = null;

        switch ($this->bodyFormat) {
            case 'json':
                if (!empty($data)) {
                    $body = is_string($data) ? $data : wp_json_encode($data);
                    $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/json';
                }
                break;

            case 'form':
                $body = is_array($data) ? $data : (string) $data;
                $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/x-www-form-urlencoded; charset=UTF-8';
                break;

            case 'multipart':
                // WP HTTP API supporta multipart via CURL se presenti \CURLFile.
                $body = $this->toMultipartBody($data);
                // non sovrascrivere Content-Type: WP la setta correttamente.
                break;

            case 'raw':
                $body = $this->rawBody ?? $data;
                break;
        }

        // IS DEV ENV: disabilita SSL verify
        if (defined('PluginClassName_DEVELOPMENT')
            && PluginClassName_DEVELOPMENT === 'yes'
        ) {
            $this->verify = false;
        }

        return array_merge([
            'method'      => strtoupper($method),
            'headers'     => $headers,
            'body'        => $body,
            'timeout'     => $this->timeout,
            'redirection' => $this->redirection,
            'sslverify'   => $this->verify,
            'blocking'    => $this->options['blocking'] ?? true,
        ], $this->options);
    }

    protected function toMultipartBody($data): array
    {
        $fields = is_array($data) ? $data : [];

        foreach ($this->multipart as $part) {
            $name     = $part['name'];
            $contents = $part['contents'];
            $filename = $part['filename'] ?? null;

            // Se è un path reale, usa CURLFile
            if (is_string($contents) && @is_file($contents)) {
                $fields[$name] = function_exists('curl_file_create')
                    ? curl_file_create($contents, null, $filename ?: basename($contents))
                    : '@'.$contents;
                continue;
            }

            // Se è una risorsa o una stringa di contenuto, crea un file temp
            $tmp = $this->materializeToTmp($contents, $filename);
            $fields[$name] = function_exists('curl_file_create')
                ? curl_file_create($tmp, null, $filename ?: basename($tmp))
                : '@'.$tmp;
        }

        return $fields;
    }

    protected function materializeToTmp($contents, ?string $filename = null): string
    {
        $dir = get_temp_dir();
        $tmp = tempnam($dir, 'fson_http_');
        $target = $tmp . '_' . ($filename ?: 'upload.bin');

        // rinomina temp per un nome più “umano”
        @rename($tmp, $target);

        if (is_resource($contents)) {
            $out = fopen($target, 'wb');
            stream_copy_to_stream($contents, $out);
            fclose($out);
        } else {
            file_put_contents($target, (string) $contents);
        }

        return $target;
    }

    protected function shouldRetry($response, int $attempt): bool
    {
        // custom closure ha priorità
        if ($this->retryWhen) {
            return ($this->retryWhen)($response, $attempt) && $attempt < $this->tries;
        }

        if (is_wp_error($response)) {
            return $attempt < $this->tries;
        }

        $code = wp_remote_retrieve_response_code($response);
        if (in_array($code, [429, 502, 503, 504], true)) {
            return $attempt < $this->tries;
        }

        return false;
    }

    protected function extractRetryAfterMs($response): ?int
    {
        if (is_wp_error($response)) return null;

        $headers = wp_remote_retrieve_headers($response);
        $retry   = $headers['retry-after'] ?? $headers['Retry-After'] ?? null;
        if (!$retry) return null;

        if (ctype_digit((string)$retry)) {
            return ((int) $retry) * 1000;
        }

        $ts = strtotime($retry);
        return $ts ? max(0, ($ts - time()) * 1000) : null;
    }
}