<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Usgs;

/**
 * The place of a USGS earthquake in German. USGS has English texts only, made of a few patterns: "5 km SW of Udine,
 * Italy", "southern Germany", "Switzerland-France border region" or just "Austria". Directions and the countries around
 * DACH are translated; a place of another shape or an unknown country stays as USGS names it.
 */
final class GermanPlace
{
    /** Compass points: English letters to German ones (east is O). */
    private const DIRECTIONS = [
        'N' => 'N', 'NNE' => 'NNO', 'NE' => 'NO', 'ENE' => 'ONO', 'E' => 'O', 'ESE' => 'OSO', 'SE' => 'SO', 'SSE' => 'SSO',
        'S' => 'S', 'SSW' => 'SSW', 'SW' => 'SW', 'WSW' => 'WSW', 'W' => 'W', 'WNW' => 'WNW', 'NW' => 'NW', 'NNW' => 'NNW',
    ];
    private const COUNTRIES = [
        'Germany' => 'Deutschland', 'Austria' => 'Österreich', 'Switzerland' => 'Schweiz', 'Liechtenstein' => 'Liechtenstein',
        'Italy' => 'Italien', 'France' => 'Frankreich', 'Slovenia' => 'Slowenien', 'Croatia' => 'Kroatien',
        'Czechia' => 'Tschechien', 'Czech Republic' => 'Tschechien', 'Slovakia' => 'Slowakei', 'Hungary' => 'Ungarn',
        'Poland' => 'Polen', 'Netherlands' => 'Niederlande', 'Belgium' => 'Belgien', 'Luxembourg' => 'Luxemburg',
        'Denmark' => 'Dänemark', 'Bosnia and Herzegovina' => 'Bosnien und Herzegowina', 'Serbia' => 'Serbien',
        'Monaco' => 'Monaco', 'San Marino' => 'San Marino', 'Adriatic Sea' => 'Adria', 'Ligurian Sea' => 'Ligurisches Meer',
    ];
    private const PARTS = ['northern' => 'Norden', 'southern' => 'Süden', 'eastern' => 'Osten', 'western' => 'Westen', 'central' => 'Mitte'];

    public function of(string $place): string
    {
        $place = trim($place);
        if (preg_match('/^(\d+(?:\.\d+)?) km ([NESW]{1,3}) of (.+)$/', $place, $m) === 1 && isset(self::DIRECTIONS[$m[2]])) {
            return sprintf('%s km %s von %s', $m[1], self::DIRECTIONS[$m[2]], $this->town($m[3]));
        }
        if (preg_match('/^(northern|southern|eastern|western|central) (.+)$/', $place, $m) === 1 && isset(self::COUNTRIES[$m[2]])) {
            return self::PARTS[$m[1]] . ' von ' . self::COUNTRIES[$m[2]];
        }
        if (preg_match('/^(.+)-(.+) border region$/', $place, $m) === 1 && isset(self::COUNTRIES[$m[1]], self::COUNTRIES[$m[2]])) {
            return 'Grenzgebiet ' . self::COUNTRIES[$m[1]] . '–' . self::COUNTRIES[$m[2]];
        }

        return self::COUNTRIES[$place] ?? $place;
    }

    /** "Udine, Italy": the town stays, the country is translated. */
    private function town(string $town): string
    {
        $comma = strrpos($town, ', ');
        if ($comma === false) {
            return $town;
        }
        $country = substr($town, $comma + 2);

        return substr($town, 0, $comma) . ', ' . (self::COUNTRIES[$country] ?? $country);
    }
}
