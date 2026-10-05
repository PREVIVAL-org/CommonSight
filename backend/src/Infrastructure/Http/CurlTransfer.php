<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Http;

use CommonSight\Model\Http\FailureKind;
use CommonSight\Model\Http\HttpFailure;
use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Http\HttpResponse;

/** A running transfer: collects body and headers up to the limits and evaluates status and errors at the end. */
final class CurlTransfer
{
    /** More header lines than this are ignored: no source needs them, and they must not fill the memory. */
    private const MAX_HEADER_LINES = 200;

    private string $body = '';
    private bool $tooLarge = false;
    /** @var list<string> */
    private array $headerLines = [];
    public int $slot = 0;
    public int $attempt = 1;
    /** The time budget of the run, not the source's own limit, set this transfer's timeout. */
    public bool $budgetLimited = false;

    public function __construct(
        public readonly \CurlHandle $handle,
        public readonly HttpRequest $request,
        private readonly int $maxBytes,
        private readonly ResponseHeaderParser $headers,
    ) {}

    public function withSlot(int $slot, int $attempt): self
    {
        $this->slot = $slot;
        $this->attempt = $attempt;

        return $this;
    }

    /** Write callback of curl; aborts as soon as the size limit is exceeded (F-08). */
    public function write(\CurlHandle $handle, string $chunk): int
    {
        if (strlen($this->body) + strlen($chunk) > $this->maxBytes) {
            $this->tooLarge = true;

            return -1;
        }
        $this->body .= $chunk;

        return strlen($chunk);
    }

    /** Header callback of curl; a new status line (redirect, 100 Continue) starts the headers of the next response. */
    public function header(\CurlHandle $handle, string $line): int
    {
        $length = strlen($line);
        $line = trim($line);
        if (str_starts_with($line, 'HTTP/')) {
            $this->headerLines = [];
        } elseif ($line !== '' && count($this->headerLines) < self::MAX_HEADER_LINES) {
            $this->headerLines[] = $line;
        }

        return $length;
    }

    public function result(int $curlCode): HttpResponse|HttpFailure
    {
        if ($this->tooLarge) {
            return new HttpFailure($this->request, FailureKind::TooLarge, sprintf('Response larger than %d bytes', $this->maxBytes));
        }
        if ($curlCode !== CURLE_OK) {
            // A timeout the run's budget cut short is not the source's failure (Architecture 5.1): its last outcome stays.
            $kind = match (true) {
                $curlCode === CURLE_OPERATION_TIMEDOUT && $this->budgetLimited => FailureKind::BudgetExhausted,
                $curlCode === CURLE_OPERATION_TIMEDOUT => FailureKind::Timeout,
                default => FailureKind::Network,
            };

            return new HttpFailure($this->request, $kind, curl_strerror($curlCode) . ' (' . curl_error($this->handle) . ')');
        }

        return $this->resultFor((int) curl_getinfo($this->handle, CURLINFO_RESPONSE_CODE), time());
    }

    /** The answer for an HTTP status with the headers of the last response: 429 is rate limiting with its Retry-After. */
    public function resultFor(int $status, int $now): HttpResponse|HttpFailure
    {
        $headers = $this->headers->parse($this->headerLines, $now);
        if ($status >= 200 && $status < 300) {
            return new HttpResponse($this->request, $status, $this->body, $headers);
        }
        $kind = match (true) {
            $status === 429 => FailureKind::RateLimited,
            $status >= 500 => FailureKind::ServerError,
            default => FailureKind::ClientError,
        };

        return new HttpFailure($this->request, $kind, 'HTTP ' . $status, $headers->retryAfterSec);
    }
}
