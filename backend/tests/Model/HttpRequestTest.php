<?php

declare(strict_types=1);

namespace CommonSight\Tests\Model;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Http\RequestBody;
use PHPUnit\Framework\TestCase;

/** F-07, F-10: requests to sources; credentials are sent but never logged. */
final class HttpRequestTest extends TestCase
{
    public function testSecretQueryParametersAreSentButRedacted(): void
    {
        $request = HttpRequest::withQuery('https://api.example/v1', ['station' => 'A 1'], 'application/json', 'x')
            ->withSecretQuery('apikey', 's3cret&more');

        self::assertSame('https://api.example/v1?station=A%201&apikey=s3cret%26more', $request->url);
        self::assertSame('https://api.example/v1?station=A%201&apikey=***', $request->redactedUrl());
        self::assertStringNotContainsString('s3cret', $request->redactedUrl());
    }

    public function testSecretQueryOnAUrlWithoutQuery(): void
    {
        $request = (new HttpRequest('https://api.example/v1', 'application/json', 'x'))->withSecretQuery('key', 'abc');

        self::assertSame('https://api.example/v1?key=abc', $request->url);
        self::assertSame('https://api.example/v1?key=***', $request->redactedUrl());
    }

    public function testHeadersAndSecretHeaders(): void
    {
        $request = (new HttpRequest('https://api.example/', 'application/json', 'x'))
            ->withHeader('Accept-Language', 'de')
            ->withSecretHeader('Authorization', 'Bearer t0ken');

        self::assertSame(['Accept-Language' => 'de', 'Authorization' => 'Bearer t0ken'], $request->headers);
        self::assertSame(['Authorization'], $request->secrets);
    }

    public function testRejectsHeadersSetByTheClient(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new HttpRequest('https://api.example/', 'application/json', 'x'))->withHeader('User-Agent', 'other');
    }

    public function testRejectsLineBreaksInHeaderValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new HttpRequest('https://api.example/', 'application/json', 'x'))->withHeader('X-Test', "a\r\nInjected: 1");
    }

    public function testBodiesCarryTheirContentType(): void
    {
        $json = HttpRequest::postJson('https://api.example/', '{"a":1}', 'x');
        $soap = (new HttpRequest('https://api.example/soap', 'text/xml', 'x'))->withBody(RequestBody::xml('<Envelope/>'));

        self::assertSame(['application/json', '{"a":1}'], [$json->body?->contentType, $json->body?->content]);
        self::assertSame('application/xml; charset=utf-8', $soap->body?->contentType);
        self::assertSame('a=1&b=x%20y', RequestBody::form(['a' => 1, 'b' => 'x y'])->content);
    }
}
