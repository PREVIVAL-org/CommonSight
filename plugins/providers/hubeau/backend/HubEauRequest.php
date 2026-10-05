<?php

declare(strict_types=1);

namespace CommonSight\Plugin\HubEau;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\SourceRequest;
use CommonSight\Sdk\Station\StationDirectory;

/**
 * Names the requests to the real-time water levels of Hub'Eau around the stations of the border zone: all
 * observations of the last two hours (values every 5 to 60 minutes, some stations deliver late), in five latitude
 * bands so that each response stays well below the limit of 20,000 rows (2026-10-02: at most 7,200 per band). The
 * parser keeps the newest value per station.
 */
final class HubEauRequest implements SourceRequest
{
    public const SOURCE_ID = 'hubeau';
    private const WINDOW_SEC = 7200;
    private const BANDS = 5;
    public const PAGE_SIZE = 20000;

    public function __construct(private readonly StationDirectory $stations) {}

    public function requestsFor(Scope $scope, UtcInstant $now): array
    {
        $box = $this->stations->bounds();
        $height = ($box->north - $box->south) / self::BANDS;
        $requests = [];
        for ($band = 0; $band < self::BANDS; $band++) {
            // Only the outer edges get a margin: bands that overlapped would return a station near a border twice.
            $south = $band === 0 ? $box->south - 0.001 : $box->south + $band * $height;
            $north = $band === self::BANDS - 1 ? $box->north + 0.001 : $box->south + ($band + 1) * $height;
            $requests[] = HttpRequest::withQuery('https://hubeau.eaufrance.fr/api/v2/hydrometrie/observations_tr', [
                'bbox' => sprintf('%.3F,%.3F,%.3F,%.3F', $box->west - 0.001, $south, $box->east + 0.001, $north),
                'grandeur_hydro' => 'H',
                'date_debut_obs' => $now->plusSeconds(-self::WINDOW_SEC)->toIso(),
                'size' => self::PAGE_SIZE,
                'fields' => 'code_station,date_obs,resultat_obs',
            ], 'application/json', self::SOURCE_ID, 'band' . ($band + 1));
        }

        return $requests;
    }
}
