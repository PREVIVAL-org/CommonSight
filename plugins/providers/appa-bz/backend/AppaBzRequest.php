<?php

declare(strict_types=1);

namespace CommonSight\Plugin\AppaBz;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\SourceRequest;

/** Names the request to the current sensor values of the South Tyrol Agency for Environment (air and gamma probes). */
final class AppaBzRequest implements SourceRequest
{
    public const SOURCE_ID = 'appa-bz';

    public function requestsFor(Scope $scope, UtcInstant $now): array
    {
        return [new HttpRequest('https://dati.retecivica.bz.it/services/airquality/sensors', 'application/json', self::SOURCE_ID)];
    }
}
