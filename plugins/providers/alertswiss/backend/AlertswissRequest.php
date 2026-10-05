<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Alertswiss;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\SourceRequest;

/** Names the request to the current alerts of alert.swiss, in German (the texts of the other cantons machine-translated). */
final class AlertswissRequest implements SourceRequest
{
    public const SOURCE_ID = 'alertswiss';

    public function requestsFor(Scope $scope, UtcInstant $now): array
    {
        return [new HttpRequest(
            'https://www.alert.swiss/content/alertswiss-internet/de/home/_jcr_content/polyalert.alertswiss_alerts.actual.json',
            'application/json',
            self::SOURCE_ID,
        )];
    }
}
