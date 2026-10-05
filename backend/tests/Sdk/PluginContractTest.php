<?php

declare(strict_types=1);

namespace CommonSight\Tests\Sdk;

use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Plugin\Attribution;
use CommonSight\Sdk\Plugin\Secrets;
use CommonSight\Sdk\Plugin\SourceDescription;
use CommonSight\Sdk\Plugin\SourceOutcome;
use CommonSight\Sdk\Plugin\SourceSchedule;
use CommonSight\Sdk\Source\ParseStatistics;
use CommonSight\Sdk\Source\SourceExpectations;
use PHPUnit\Framework\TestCase;

/** The contract between core and source plugins checks itself on creation (concept: sources as plugins, 4.1). */
final class PluginContractTest extends TestCase
{
    public function testEffectiveIntervalNeverBelowUpdateRateOrTerms(): void
    {
        self::assertSame(900, (new SourceSchedule(900))->effectiveIntervalSec());
        self::assertSame(1800, (new SourceSchedule(900, intervalSec: 1800))->effectiveIntervalSec());
        self::assertSame(900, (new SourceSchedule(900, intervalSec: 60))->effectiveIntervalSec(), 'own interval below the update rate');
        self::assertSame(3600, (new SourceSchedule(60, termsMinIntervalSec: 3600, intervalSec: 120))->effectiveIntervalSec(), 'terms of use win');
        self::assertSame('heavy', (new SourceSchedule(60))->lane);
    }

    public function testRejectsInvalidLaneNames(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SourceSchedule(60, lane: 'Fast Lane');
    }

    public function testDescriptionOfAValidSource(): void
    {
        $description = $this->description('pegelonline', [Scope::DE]);

        self::assertSame('water', $description->layer);
        self::assertSame(100, $description->order);
        self::assertNull($description->http->maxBytes, 'without limits of its own the configuration decides');
        self::assertNull($description->http->timeoutSec);
    }

    public function testRejectsInvalidIds(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->description('Pegel Online', [Scope::DE]);
    }

    public function testRejectsDuplicatedScopes(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->description('pegelonline', [Scope::DE, Scope::DE]);
    }

    public function testAttributionNeedsTextAndHttpLink(): void
    {
        self::assertSame('CC BY 4.0', (new Attribution('Daten: GeoSphere Austria', 'https://geosphere.at/', 'CC BY 4.0'))->license);

        $this->expectException(\InvalidArgumentException::class);
        new Attribution('Daten', 'javascript:alert(1)');
    }

    public function testOutcomeAcceptsOnlyItemsAndFailuresNeedACause(): void
    {
        $success = SourceOutcome::success([], new ParseStatistics(0, 0, 0));
        $failure = SourceOutcome::failure('http.429', 120);

        self::assertTrue($success->succeeded());
        self::assertFalse($failure->succeeded());
        self::assertSame(120, $failure->retryAfterSec);

        $this->expectException(\InvalidArgumentException::class);
        SourceOutcome::success([new \stdClass()], new ParseStatistics(1, 0, 0));
    }

    public function testSecretsAreNeverDumped(): void
    {
        $secrets = new Secrets(['apiKey' => 's3cret']);

        self::assertSame('s3cret', $secrets->get('apiKey'));
        self::assertFalse($secrets->has('token'));
        self::assertStringNotContainsString('s3cret', print_r($secrets, true));

        $this->expectException(\OutOfBoundsException::class);
        $secrets->get('token');
    }

    /** @param list<Scope> $scopes */
    private function description(string $id, array $scopes): SourceDescription
    {
        return new SourceDescription(
            id: $id,
            name: 'WSV · PEGELONLINE',
            attribution: new Attribution('Pegel: WSV, dl-de/zero-2-0', 'https://www.pegelonline.wsv.de/'),
            layer: 'water',
            scopes: $scopes,
            schedule: new SourceSchedule(900),
            expectations: SourceExpectations::measurements(400),
        );
    }

    /** A layer setting accepts finite numbers in its range only, and its default must be one (L-D5). */
    public function testLayerSettingsCheckTheirRangeAndDefault(): void
    {
        $setting = new \CommonSight\Sdk\Layer\LayerSetting('highUSvH', 1.0, 0.0, 100.0);
        self::assertSame(2.0, $setting->accept(2));
        foreach ([INF, NAN, 101.0, -1.0, '2'] as $invalid) {
            try {
                $setting->accept($invalid);
                self::fail('accepted: ' . var_export($invalid, true));
            } catch (\InvalidArgumentException) {
            }
        }
        $this->expectException(\InvalidArgumentException::class);
        new \CommonSight\Sdk\Layer\LayerSetting('highUSvH', 200.0, 0.0, 100.0);
    }
}
