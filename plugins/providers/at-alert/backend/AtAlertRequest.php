<?php

declare(strict_types=1);

namespace CommonSight\Plugin\AtAlert;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\SourceRequest;

/**
 * Names the request to the current warnings of warnungen.at-alert.at: the levels the website shows by default, without
 * the internal test levels (Test, Exercise, MonthlyTest); without a date range the list holds the current warnings only.
 */
final class AtAlertRequest implements SourceRequest
{
    public const SOURCE_ID = 'at-alert';
    /** More than enough for the current warnings; the parser reports a longer list as incomplete. */
    public const LIMIT = 100;
    private const LEVELS = ['AlertLevel1', 'AlertLevel2', 'AlertLevel3', 'AlertLevel4', 'Amber'];

    public function requestsFor(Scope $scope, UtcInstant $now): array
    {
        $body = json_encode(['json' => ['regions' => [], 'alertLevels' => self::LEVELS, 'search' => '', 'limit' => self::LIMIT, 'offset' => 0]], JSON_THROW_ON_ERROR);

        return [HttpRequest::postJson('https://warnungen.at-alert.at/api/rpc/alert/list', $body, self::SOURCE_ID)];
    }
}
