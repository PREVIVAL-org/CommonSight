<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Http;

use CommonSight\Config\HttpLimits;
use CommonSight\Model\Http\HttpRequest;

/**
 * Creates a curl handle with the fixed security settings: HTTPS only, at most 3 redirects, TLS verification (F-07, F-10);
 * POST with the content type of the body when the request has one; the additional headers of the request.
 */
final class CurlHandleFactory
{
    private const MAX_REDIRECTS = 3;

    public function __construct(private readonly ResponseHeaderParser $headers = new ResponseHeaderParser()) {}

    public function create(HttpRequest $request, HttpLimits $limits, float $timeoutSec): CurlTransfer
    {
        $handle = curl_init();
        $transfer = new CurlTransfer($handle, $request, $limits->maxBytesFor($request->sourceId), $this->headers);
        $url = $request->url;
        $userAgent = $limits->userAgent;
        if ($url === '' || $userAgent === '') {
            throw new \InvalidArgumentException('Empty URL or empty user agent');
        }
        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($handle, CURLOPT_MAXREDIRS, self::MAX_REDIRECTS);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, $limits->connectTimeoutSec);
        curl_setopt($handle, CURLOPT_TIMEOUT_MS, (int) ($timeoutSec * 1000));
        curl_setopt($handle, CURLOPT_ENCODING, '');
        curl_setopt($handle, CURLOPT_USERAGENT, $userAgent);
        $headers = ['Accept: ' . $request->accept];
        if ($request->body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $request->body->content);
            $headers[] = 'Content-Type: ' . $request->body->contentType;
        }
        foreach ($request->headers as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($handle, CURLOPT_WRITEFUNCTION, $transfer->write(...));
        curl_setopt($handle, CURLOPT_HEADERFUNCTION, $transfer->header(...));
        curl_setopt($handle, CURLOPT_NOSIGNAL, true);
        $this->restrictToHttps($handle);
        if ($limits->caFile !== null && $limits->caFile !== '') {
            curl_setopt($handle, CURLOPT_CAINFO, $limits->caFile);
        }

        return $transfer;
    }

    private function restrictToHttps(\CurlHandle $handle): void
    {
        if (defined('CURLOPT_PROTOCOLS_STR')) {
            curl_setopt($handle, CURLOPT_PROTOCOLS_STR, 'https');
            curl_setopt($handle, CURLOPT_REDIR_PROTOCOLS_STR, 'https');

            return;
        }
        curl_setopt($handle, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        curl_setopt($handle, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
    }
}
