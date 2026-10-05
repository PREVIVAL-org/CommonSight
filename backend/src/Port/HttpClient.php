<?php

declare(strict_types=1);

namespace CommonSight\Port;

use CommonSight\Model\Http\HttpFailure;
use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Http\HttpResponse;

/** Executes HTTP requests, several in parallel. */
interface HttpClient
{
    /**
     * @param list<HttpRequest> $requests
     * @return list<HttpResponse|HttpFailure> in the order of the requests
     */
    public function fetchAll(array $requests): array;
}
