<?php

declare(strict_types=1);

namespace UupCode\Utilities\Http;

/**
 * Static facade — entry point for fluent HTTP requests.
 *
 * Usage:
 *
 *   $res = Http::get('https://api.example.com/posts')
 *       ->withHeader('Authorization', 'Bearer token')
 *       ->withQuery(['page' => 2])
 *       ->timeout(15)
 *       ->send();
 *
 *   $res->ok();          // bool
 *   $res->status();      // int
 *   $res->json();        // array
 *   $res->body();        // string
 *
 *   Http::post('https://api.example.com/items')
 *       ->withJson(['name' => 'Widget'])
 *       ->send();
 *
 *   Http::post('https://api.example.com/upload')
 *       ->withBody('raw string')
 *       ->withHeader('Content-Type', 'text/plain')
 *       ->send();
 */
final class Http
{
    public static function get(string $url): HttpRequest
    {
        return new HttpRequest('GET', $url);
    }

    public static function post(string $url): HttpRequest
    {
        return new HttpRequest('POST', $url);
    }

    public static function put(string $url): HttpRequest
    {
        return new HttpRequest('PUT', $url);
    }

    public static function patch(string $url): HttpRequest
    {
        return new HttpRequest('PATCH', $url);
    }

    public static function delete(string $url): HttpRequest
    {
        return new HttpRequest('DELETE', $url);
    }
}

/**
 * Fluent request builder — configure and then call send().
 */
final class HttpRequest
{
    private string $url;

    /** @var array<string, string> */
    private array $headers = [];

    /** @var array<string, mixed> */
    private array $query = [];

    private string|null $body = null;

    private int $timeout = 5;

    private bool $sslVerify = true;

    private bool $safe = false;

    public function __construct(
        private readonly string $method,
        string                  $url,
    ) {
        $this->url = $url;
    }

    // ─── Builder methods ──────────────────────────────────────────────────────

    public function withHeader(string $name, string $value): static
    {
        $this->headers[ $name ] = $value;
        return $this;
    }

    /**
     * Merge additional query-string parameters into the URL.
     *
     * @param array<string, mixed> $params
     */
    public function withQuery(array $params): static
    {
        $this->query = array_merge($this->query, $params);
        return $this;
    }

    /**
     * Set a raw string body.
     */
    public function withBody(string $body): static
    {
        $this->body = $body;
        return $this;
    }

    /**
     * JSON-encode $data and set the body + Content-Type header.
     *
     * @param array<mixed> $data
     */
    public function withJson(array $data): static
    {
        $this->body                    = wp_json_encode($data);
        $this->headers['Content-Type'] = 'application/json';
        return $this;
    }

    public function timeout(int $seconds): static
    {
        $this->timeout = $seconds;
        return $this;
    }

    public function sslVerify(bool $verify): static
    {
        $this->sslVerify = $verify;
        return $this;
    }

    /**
     * Use wp_safe_remote_* (blocks requests to private/local IP ranges).
     */
    public function safe(bool $safe = true): static
    {
        $this->safe = $safe;
        return $this;
    }

    // ─── Dispatch ─────────────────────────────────────────────────────────────

    /**
     * Execute the request and return an HttpResponse.
     *
     * @throws \RuntimeException On WP_Error.
     */
    public function send(): HttpResponse
    {
        $url = $this->url;
        if (! empty($this->query)) {
            $url = add_query_arg($this->query, $url);
        }

        $args = [
            'method'    => $this->method,
            'headers'   => $this->headers,
            'timeout'   => $this->timeout,
            'sslverify' => $this->sslVerify,
        ];

        if ($this->body !== null) {
            $args['body'] = $this->body;
        }

        if ($this->safe) {
            $response = wp_safe_remote_request($url, $args);
        } else {
            $response = wp_remote_request($url, $args);
        }

        if (is_wp_error($response)) {
            throw new \RuntimeException(
                'HTTP request failed: ' . $response->get_error_message()
            );
        }

        return new HttpResponse(
            statusCode: (int) wp_remote_retrieve_response_code($response),
            body:       wp_remote_retrieve_body($response),
            headers:    wp_remote_retrieve_headers($response),
        );
    }
}
