<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Http;

use CommonSight\Config\HttpLimits;
use CommonSight\Model\Http\FailureKind;
use CommonSight\Model\Http\HttpFailure;
use CommonSight\Port\HttpClient;

/**
 * Executes HTTPS requests in parallel via curl_multi: HTTPS only, also for redirects, TLS verification, size and
 * time limits, parallelism per host, one retry on timeout or 5xx, no new requests after the budget (Architecture 4.4).
 */
final class CurlHttpClient implements HttpClient
{
    private const MAX_ATTEMPTS = 2;
    private const MIN_REMAINING_SEC = 2.0;

    public function __construct(
        private readonly HttpLimits $limits,
        private readonly CurlHandleFactory $handles,
        private readonly float $deadline,
    ) {}

    public function fetchAll(array $requests): array
    {
        $queue = new RequestQueue($requests, $this->limits);
        $multi = curl_multi_init();
        /** @var array<int, CurlTransfer> $active */
        $active = [];
        try {
            while (!$queue->isDone($active)) {
                $this->start($multi, $queue, $active);
                $this->pump($multi, $queue, $active);
            }
        } finally {
            curl_multi_close($multi);
        }

        return $queue->results();
    }

    /** @param array<int, CurlTransfer> $active */
    private function start(\CurlMultiHandle $multi, RequestQueue $queue, array &$active): void
    {
        while (($next = $queue->nextStartable(count($active), microtime(true))) !== null) {
            [$index, $request, $attempt] = $next;
            $remaining = $this->deadline - microtime(true);
            if ($remaining < self::MIN_REMAINING_SEC) {
                $queue->finish($index, new HttpFailure($request, FailureKind::BudgetExhausted, 'Time budget of the run used up'));
                continue;
            }
            $ownLimit = (float) $this->limits->requestTimeoutFor($request->sourceId);
            $transfer = $this->handles->create($request, $this->limits, min($ownLimit, $remaining));
            $transfer->budgetLimited = $remaining < $ownLimit;
            curl_multi_add_handle($multi, $transfer->handle);
            $active[spl_object_id($transfer->handle)] = $transfer->withSlot($index, $attempt);
            $queue->started($request);
        }
    }

    /** @param array<int, CurlTransfer> $active */
    private function pump(\CurlMultiHandle $multi, RequestQueue $queue, array &$active): void
    {
        if ($active === []) {
            usleep(50_000);

            return;
        }
        curl_multi_exec($multi, $running);
        if (curl_multi_select($multi, 0.2) === -1) {
            usleep(10_000);
        }
        while (($info = curl_multi_info_read($multi)) !== false) {
            $handle = $info['handle'] ?? null;
            if (!$handle instanceof \CurlHandle || !isset($active[spl_object_id($handle)])) {
                continue;
            }
            $transfer = $active[spl_object_id($handle)];
            unset($active[spl_object_id($handle)]);
            curl_multi_remove_handle($multi, $handle);
            $queue->stopped($transfer->request);
            $result = $transfer->result(is_int($info['result'] ?? null) ? $info['result'] : CURLE_RECV_ERROR);
            if ($result instanceof HttpFailure && $result->kind->isRetryable() && $transfer->attempt < self::MAX_ATTEMPTS) {
                $queue->retry($transfer->slot, $transfer->request, $transfer->attempt + 1, microtime(true) + 0.5 + mt_rand(0, 1000) / 1000);
                continue;
            }
            $queue->finish($transfer->slot, $result);
        }
    }
}
