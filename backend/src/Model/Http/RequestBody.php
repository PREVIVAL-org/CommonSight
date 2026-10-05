<?php

declare(strict_types=1);

namespace CommonSight\Model\Http;

/** Body of a POST request with its content type: JSON, XML (e.g. SOAP) or form data. */
final readonly class RequestBody
{
    private function __construct(public string $contentType, public string $content) {}

    public static function json(string $json): self
    {
        return new self('application/json', $json);
    }

    public static function xml(string $xml): self
    {
        return new self('application/xml; charset=utf-8', $xml);
    }

    /** @param array<string, string|int|float> $fields */
    public static function form(array $fields): self
    {
        return new self('application/x-www-form-urlencoded', http_build_query($fields, '', '&', PHP_QUERY_RFC3986));
    }
}
