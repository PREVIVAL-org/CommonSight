<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Source;

use CommonSight\Model\Http\HttpRequest;

/** After the first page, determines the requests for the further pages of a source. */
interface Paging
{
    /** @return list<HttpRequest> */
    public function followUpRequests(HttpRequest $firstRequest, ParseResult $firstPage): array;
}
