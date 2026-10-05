<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Paa;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\SourceRequest;

/** Names the WFS request to the latest dose rate of the PMS stations of the Polish Atomic Energy Agency (PAA). */
final class PaaRequest implements SourceRequest
{
    public const SOURCE_ID = 'paa';

    public function requestsFor(Scope $scope, UtcInstant $now): array
    {
        return [HttpRequest::withQuery('https://monitoring.paa.gov.pl/geoserver/ows', [
            'service' => 'WFS',
            'version' => '2.0.0',
            'request' => 'GetFeature',
            'typeNames' => 'paa:kcad_siec_pms_moc_dawki_mapa',
            'outputFormat' => 'application/json',
            'srsName' => 'EPSG:4326',
        ], 'application/json', self::SOURCE_ID)];
    }
}
