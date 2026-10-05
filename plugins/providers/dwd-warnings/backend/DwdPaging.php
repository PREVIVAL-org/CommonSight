<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Dwd;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Sdk\Source\Paging;
use CommonSight\Sdk\Source\ParseResult;

/** Determines the further pages of the DWD WFS from numberMatched, at most up to a fixed number of pages. */
final class DwdPaging implements Paging
{
    private const MAX_PAGES = 40;

    public function __construct(private readonly DwdWarningRequest $request) {}

    public function followUpRequests(HttpRequest $firstRequest, ParseResult $firstPage): array
    {
        $matched = $firstPage->totalAvailable ?? 0;
        $requests = [];
        for ($page = 1; $page < self::MAX_PAGES && $page * DwdWarningRequest::PAGE_SIZE < $matched; $page++) {
            $requests[] = $this->request->page($page * DwdWarningRequest::PAGE_SIZE);
        }

        return $requests;
    }
}
