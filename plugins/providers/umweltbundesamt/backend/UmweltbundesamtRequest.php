<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Umweltbundesamt;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\SourceRequest;

/** Names the request to the current values of the Austrian radiation early warning system (BMLUK, Umweltbundesamt). */
final class UmweltbundesamtRequest implements SourceRequest
{
    public const SOURCE_ID = 'umweltbundesamt';

    public function requestsFor(Scope $scope, UtcInstant $now): array
    {
        return [new HttpRequest('https://mb.strahlenschutz.gv.at/api/current', 'application/json', self::SOURCE_ID)];
    }
}
