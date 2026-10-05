<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Autobahn;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\SourceRequest;

/** Names one request per selected Autobahn and service: warnings, closures and roadworks (Q-TR-DE-01). */
final class AutobahnRequest implements SourceRequest
{
    public const SOURCE_ID = 'autobahn';
    public const ROADS = ['A1', 'A2', 'A3', 'A4', 'A5', 'A6', 'A7', 'A8', 'A9', 'A10', 'A81', 'A93'];
    /** The services of the API that the layer shows; each answers with a list under its own name. */
    public const SERVICES = ['warning', 'closure', 'roadworks'];

    public function requestsFor(Scope $scope, UtcInstant $now): array
    {
        $requests = [];
        foreach (self::ROADS as $road) {
            foreach (self::SERVICES as $service) {
                $requests[] = new HttpRequest('https://verkehr.autobahn.de/o/autobahn/' . $road . '/services/' . $service, 'application/json', self::SOURCE_ID, $road . '/' . $service);
            }
        }

        return $requests;
    }
}
