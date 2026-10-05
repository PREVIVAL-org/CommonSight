<?php

declare(strict_types=1);

namespace CommonSight\Plugin\AtAlert\Tests;

use CommonSight\Plugin\AtAlert\GermanText;
use PHPUnit\Framework\TestCase;

/** The English versions the warning centres add, in their four shapes of the Zivilschutz-Probealarm 2026. */
final class GermanTextTest extends TestCase
{
    public function testAnEnglishParagraphGoesTheLinkStays(): void
    {
        $text = "Probewarnung - Testauslösung von Sirenen\nEs besteht keine Gefahr!\n\nTest Alert - Austria-wide test of sirens.\nThere is no danger!\n\nhttps://warnung-bgld.gv.at/Jk6NTd";

        self::assertSame("Probewarnung - Testauslösung von Sirenen\nEs besteht keine Gefahr!\n\nhttps://warnung-bgld.gv.at/Jk6NTd", (new GermanText())->of($text));
    }

    public function testEnglishLinesBetweenGermanOnesGo(): void
    {
        $text = "SYSTEMTEST für AT-ALERT\nDies ist nur ein Systemtest.\nThis is just a system test.\nBitte rufen Sie keine Notrufnummern an.\nPlease do not call emergency numbers.";

        self::assertSame("SYSTEMTEST für AT-ALERT\nDies ist nur ein Systemtest.\nBitte rufen Sie keine Notrufnummern an.", (new GermanText())->of($text));
    }

    public function testAnEnglishPartBetweenAsterisksGoes(): void
    {
        $text = '*Rufen Sie keine Notrufnummern an. *Land Salzburg* For translations copy this text and use https://www.deepl.com';

        self::assertSame("Rufen Sie keine Notrufnummern an.\nLand Salzburg", (new GermanText())->of($text));
    }

    public function testATextWithoutGermanStaysWhole(): void
    {
        self::assertSame('Please do not call emergency numbers.', (new GermanText())->of('Please do not call emergency numbers.'));
    }
}
