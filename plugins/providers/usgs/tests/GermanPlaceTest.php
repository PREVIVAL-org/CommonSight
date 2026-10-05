<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Usgs\Tests;

use CommonSight\Plugin\Usgs\GermanPlace;
use PHPUnit\Framework\TestCase;

/** The English place texts of USGS in German; unknown shapes stay as they are. */
final class GermanPlaceTest extends TestCase
{
    public function testThePatternsOfUsgsInGerman(): void
    {
        $place = new GermanPlace();

        self::assertSame('0 km NNW von Baumkirchen, Österreich', $place->of('0 km NNW of Baumkirchen, Austria'));
        self::assertSame('12 km ONO von Udine, Italien', $place->of('12 km ENE of Udine, Italy'));
        self::assertSame('Süden von Deutschland', $place->of('southern Germany'));
        self::assertSame('Grenzgebiet Schweiz–Frankreich', $place->of('Switzerland-France border region'));
        self::assertSame('Österreich', $place->of('Austria'));
        self::assertSame('3 km SO von Kotor, Montenegro', $place->of('3 km SE of Kotor, Montenegro'), 'an unknown country stays');
        self::assertSame('Mid-Atlantic Ridge', $place->of('Mid-Atlantic Ridge'), 'an unknown shape stays');
    }
}
