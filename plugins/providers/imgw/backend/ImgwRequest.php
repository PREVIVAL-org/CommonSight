<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Imgw;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\SourceRequest;

/** Names the request to all hydrological stations of IMGW-PIB (one response, about 900 stations with coordinates). */
final class ImgwRequest implements SourceRequest
{
    public const SOURCE_ID = 'imgw';

    public function requestsFor(Scope $scope, UtcInstant $now): array
    {
        return [new HttpRequest('https://danepubliczne.imgw.pl/api/data/hydro/', 'application/json', self::SOURCE_ID)];
    }
}
