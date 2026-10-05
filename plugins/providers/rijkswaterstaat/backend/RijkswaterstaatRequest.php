<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Rijkswaterstaat;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\SourceRequest;
use CommonSight\Sdk\Station\StationDirectory;

/** Names the request to the latest water levels above NAP at the Rijkswaterstaat locations of the border zone (DD-API 2.0, POST). */
final class RijkswaterstaatRequest implements SourceRequest
{
    public const SOURCE_ID = 'rijkswaterstaat';
    private const URL = 'https://ddapi20-waterwebservices.rijkswaterstaat.nl/ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen';

    public function __construct(private readonly StationDirectory $stations) {}

    public function requestsFor(Scope $scope, UtcInstant $now): array
    {
        $body = [
            'LocatieLijst' => array_map(static fn(string $code): array => ['Code' => $code], $this->stations->ids()),
            'AquoPlusWaarnemingMetadataLijst' => [['AquoMetadata' => [
                'Compartiment' => ['Code' => 'OW'],
                'Grootheid' => ['Code' => 'WATHTE'],
                'Hoedanigheid' => ['Code' => 'NAP'],
            ]]],
        ];

        return [HttpRequest::postJson(self::URL, json_encode($body, JSON_THROW_ON_ERROR), self::SOURCE_ID)];
    }
}
