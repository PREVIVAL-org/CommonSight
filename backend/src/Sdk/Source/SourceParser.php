<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Source;

use CommonSight\Model\Http\HttpResponse;

/** Converts a response of the source into source records (types of the API) and counts invalid ones. */
interface SourceParser
{
    /** @throws UnreadableResponse if the response as a whole cannot be evaluated */
    public function parse(HttpResponse $response, ParseContext $context): ParseResult;
}
