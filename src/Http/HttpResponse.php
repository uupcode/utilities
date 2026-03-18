<?php

declare(strict_types=1);

namespace UupCode\Utilities\Http;

/**
 * Value object wrapping a WordPress HTTP response array.
 *
 * Returned by HttpRequest::send().
 */
final class HttpResponse
{
    /**
     * @param array<string, string>|object $headers
     */
    public function __construct(
        private readonly int    $statusCode,
        private readonly string $body,
        private readonly mixed  $headers,
    ) {
    }

    /**
     * True when the HTTP status code is in the 2xx range.
     */
    public function ok(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function status(): int
    {
        return $this->statusCode;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * Decode the response body as JSON and return it as an array.
     *
     * @return array<mixed>
     * @throws \RuntimeException When the body is not valid JSON.
     */
    public function json(): array
    {
        $decoded = json_decode($this->body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                'Failed to decode JSON response: ' . json_last_error_msg()
            );
        }
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Retrieve a single response header (case-insensitive key).
     */
    public function header(string $name): string
    {
        $name = strtolower($name);
        $all  = $this->headers();
        return $all[ $name ] ?? '';
    }

    /**
     * Return all response headers as a lowercase-keyed array.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        if (is_array($this->headers)) {
            return array_change_key_case($this->headers, CASE_LOWER);
        }
        // Requests_Utility_CaseInsensitiveDictionary or similar object.
        if (is_object($this->headers) && method_exists($this->headers, 'getAll')) {
            return array_change_key_case($this->headers->getAll(), CASE_LOWER);
        }
        return [];
    }

    /**
     * Throw a RuntimeException when the response is not successful.
     * Designed for method chaining.
     *
     * @throws \RuntimeException
     */
    public function throw(): static
    {
        if (! $this->ok()) {
            throw new \RuntimeException(
                sprintf('HTTP request failed with status %d.', $this->statusCode)
            );
        }
        return $this;
    }
}
