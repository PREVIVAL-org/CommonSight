<?php

declare(strict_types=1);

namespace CommonSight\Model\Http;

/**
 * An HTTP request to a source: GET, or POST with a body; `key` matches the response to the request. Headers and query
 * parameters marked secret (API keys, tokens) are sent but never logged: logs use redactedUrl().
 */
final readonly class HttpRequest
{
    /** Set by the HTTP client itself, never by a source (F-10). */
    private const RESERVED_HEADERS = ['accept', 'accept-encoding', 'content-length', 'content-type', 'host', 'user-agent'];

    /**
     * @param array<string, string> $headers additional request headers by name
     * @param list<string> $secrets names of headers and query parameters whose values must not be logged
     */
    public function __construct(
        public string $url,
        public string $accept,
        public string $sourceId,
        public string $key = '',
        public ?RequestBody $body = null,
        public array $headers = [],
        public array $secrets = [],
    ) {
        if (preg_match('#^https://#i', $url) !== 1) {
            throw new \InvalidArgumentException('Only HTTPS requests are allowed: ' . $this->redactedUrl());
        }
        foreach ($headers as $name => $value) {
            self::checkHeader($name, $value);
        }
    }

    /** @param array<string, string|int|float> $query */
    public static function withQuery(string $base, array $query, string $accept, string $sourceId, string $key = ''): self
    {
        return new self($base . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986), $accept, $sourceId, $key);
    }

    /** POST with a JSON body, for sources that take their query only that way (Rijkswaterstaat). */
    public static function postJson(string $url, string $json, string $sourceId): self
    {
        return new self($url, 'application/json', $sourceId, '', RequestBody::json($json));
    }

    public function withBody(RequestBody $body): self
    {
        return new self($this->url, $this->accept, $this->sourceId, $this->key, $body, $this->headers, $this->secrets);
    }

    public function withHeader(string $name, string $value): self
    {
        return new self($this->url, $this->accept, $this->sourceId, $this->key, $this->body, [...$this->headers, $name => $value], $this->secrets);
    }

    /** A header with a credential (e.g. Authorization, X-Api-Key): sent, never logged. */
    public function withSecretHeader(string $name, string $value): self
    {
        return $this->withHeader($name, $value)->withSecret($name);
    }

    /** A query parameter with a credential (e.g. apikey): appended to the URL, its value never logged. */
    public function withSecretQuery(string $name, string $value): self
    {
        $separator = str_contains($this->url, '?') ? '&' : '?';
        $url = $this->url . $separator . rawurlencode($name) . '=' . rawurlencode($value);

        return (new self($url, $this->accept, $this->sourceId, $this->key, $this->body, $this->headers, $this->secrets))->withSecret($name);
    }

    /** The URL for logs and error messages: values of secret query parameters replaced by ***. */
    public function redactedUrl(): string
    {
        $url = $this->url;
        foreach ($this->secrets as $name) {
            $url = (string) preg_replace('/([?&]' . preg_quote(rawurlencode($name), '/') . '=)[^&#]*/', '$1***', $url);
        }

        return $url;
    }

    private function withSecret(string $name): self
    {
        return new self($this->url, $this->accept, $this->sourceId, $this->key, $this->body, $this->headers, [...$this->secrets, $name]);
    }

    private static function checkHeader(string $name, string $value): void
    {
        if (preg_match('/^[A-Za-z0-9-]+$/', $name) !== 1 || in_array(strtolower($name), self::RESERVED_HEADERS, true)) {
            throw new \InvalidArgumentException('Header not allowed: ' . $name);
        }
        if (preg_match('/[\r\n\0]/', $value) === 1) {
            throw new \InvalidArgumentException('Line break in the value of header ' . $name);
        }
    }
}
