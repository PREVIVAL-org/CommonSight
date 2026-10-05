<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Chmi;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\SourceRequest;
use CommonSight\Sdk\Station\StationDirectory;

/**
 * Names one request per ČHMÚ gauge with flood stages (static files of the open data server, regenerated every
 * 10 to 30 minutes); the key carries the station ID to the parser.
 */
final class ChmiRequest implements SourceRequest
{
    public const SOURCE_ID = 'chmi';
    private const URL = 'https://opendata.chmi.cz/hydrology/now/data/%s.json';

    public function __construct(private readonly StationDirectory $stations) {}

    public function requestsFor(Scope $scope, UtcInstant $now): array
    {
        return array_map(
            static fn(string $id): HttpRequest => new HttpRequest(sprintf(self::URL, rawurlencode($id)), 'application/json', self::SOURCE_ID, $id),
            $this->stations->ids(),
        );
    }
}
