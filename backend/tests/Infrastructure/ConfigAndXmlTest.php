<?php

declare(strict_types=1);

namespace CommonSight\Tests\Infrastructure;

use CommonSight\Config\Config;
use CommonSight\Config\ConfigError;
use CommonSight\Config\SourceSettings;
use CommonSight\Infrastructure\Xml\SafeXmlReader;
use CommonSight\Port\MalformedXml;
use PHPUnit\Framework\TestCase;

/** Architecture 1.3.3 (configuration validated in one place), B-14, F-09 (XML without DTD). */
final class ConfigAndXmlTest extends TestCase
{
    private const PATHS = ['data' => '/srv/data', 'state' => '/srv/state', 'cache' => '/srv/cache', 'locks' => '/srv/locks', 'logs' => '/srv/logs'];

    public function testDefaultsAndOverrides(): void
    {
        $config = Config::fromArray([
            'paths' => self::PATHS,
            'layers' => ['radiation' => ['settings' => ['highUSvH' => 2.0]]],
            'sources' => ['meteoalarm-ch' => ['enabled' => false, 'reason' => 'format change'], 'usgs' => ['lane' => 'slow', 'intervalSec' => 600, 'order' => 5, 'secrets' => ['apiKey' => 'k-123']]],
            'lanes' => ['heavy' => ['budgetSec' => 200], 'slow' => ['fallbackSec' => 90], 'night' => ['budgetSec' => 900]],
            'http' => ['maxBytesBySource' => ['pegelonline' => 2_000_000], 'requestTimeoutBySource' => ['hubeau' => 25]],
        ]);

        self::assertSame(['meteoalarm-ch' => false, 'usgs' => true], $config->sourceSwitches);
        self::assertEquals(new SourceSettings(true, 'slow', 600, 5, ['apiKey' => 'k-123']), $config->sources['usgs']);
        self::assertSame('format change', $config->sources['meteoalarm-ch']->reason);
        self::assertSame(5_000_000, $config->http->maxBytesFor('dwd-warnings'), 'the core knows no source');
        $withPlugins = $config->http->withSourceDefaults('dwd-warnings', 18, 20_000_000)->withSourceDefaults('pegelonline', 18, 9_000_000);
        self::assertSame(20_000_000, $withPlugins->maxBytesFor('dwd-warnings'), 'the limit the plugin declares');
        self::assertSame(2_000_000, $withPlugins->maxBytesFor('pegelonline'), 'the configuration wins over the plugin');
        self::assertSame(25, $config->http->withSourceDefaults('hubeau', 40, null)->requestTimeoutFor('hubeau'), 'also for the time per request');
        $undeclared = Config::fromArray(['paths' => self::PATHS, 'http' => ['requestTimeoutSec' => 30, 'maxBytes' => 10_000_000]])->http->withSourceDefaults('usgs', null, null);
        self::assertSame(10_000_000, $undeclared->maxBytesFor('usgs'), 'a plugin without limits of its own keeps the general ones');
        self::assertSame(30, $undeclared->requestTimeoutFor('usgs'));
        self::assertSame(2_000_000, $config->http->maxBytesFor('pegelonline'));
        self::assertSame(5_000_000, $config->http->maxBytesFor('usgs'));
        self::assertSame(['radiation' => ['highUSvH' => 2.0]], $config->layerSettings, 'raw layer settings, validated by the layer');
        self::assertSame(50, $config->lanes->budget('fast'));
        self::assertSame(200, $config->lanes->budget('heavy'), 'budget changed, fallback kept');
        self::assertSame(['fast' => 60, 'heavy' => 120, 'slow' => 90, 'night' => null], $config->lanes->fallbackSeconds(), 'a new lane has no fallback');
        self::assertSame(120, $config->lanes->maxFallbackSec());
        self::assertSame(['fast' => 60, 'heavy' => 300, 'slow' => 1800, 'night' => null], $config->lanes->cadences(), 'as often as the cron lines of crontab.example run');
        $withoutFallback = Config::fromArray(['paths' => self::PATHS, 'lanes' => ['heavy' => ['fallbackSec' => null]]]);
        self::assertNull($withoutFallback->lanes->fallbackSeconds()['heavy'], 'an explicit null switches the fallback off');
        self::assertSame(2.5, $config->staleFactor);
    }

    /** The former member gate became an auth provider: its former section must not leave the page open. */
    public function testRefusesTheFormerMemberGateSection(): void
    {
        $this->expectException(ConfigError::class);
        $this->expectExceptionMessage('memberGate is replaced by auth');

        Config::fromArray(['paths' => self::PATHS, 'memberGate' => ['cookie' => 'wsc_x_user_session']]);
    }

    /** The vicinity ("Umkreis", ADR 0038): 200 km unless config.php sets it; never beyond the map area of the build. */
    public function testReadsTheVicinityAndKeepsItWithinTheMapArea(): void
    {
        self::assertSame(200.0, Config::fromArray(['paths' => self::PATHS])->vicinityKm);
        $config = Config::fromArray(['paths' => self::PATHS, 'vicinityKm' => 150]);
        self::assertSame(150.0, \CommonSight\Entry\Vicinity::km($config, \CommonSight\Tests\Support\Fixtures::generated()));

        $this->expectException(ConfigError::class);
        $this->expectExceptionMessage('vicinityKm: at most 300');
        \CommonSight\Entry\Vicinity::km(Config::fromArray(['paths' => self::PATHS, 'vicinityKm' => 400]), \CommonSight\Tests\Support\Fixtures::generated());
    }

    public function testReadsTheAuthProvider(): void
    {
        self::assertNull(Config::fromArray(['paths' => self::PATHS])->auth, 'open without a provider');
        $auth = Config::fromArray(['paths' => self::PATHS, 'auth' => ['provider' => 'woltlab', 'settings' => ['cookie' => 'wsc_1_user_session']]])->auth;
        self::assertSame(['woltlab', ['cookie' => 'wsc_1_user_session']], [$auth?->provider, $auth?->settings]);
    }

    /** @return iterable<string, array{array<mixed>}> */
    public static function invalidConfigs(): iterable
    {
        yield 'path missing' => [['paths' => ['data' => '/x']]];
        yield 'relative path' => [['paths' => ['data' => 'data'] + self::PATHS]];
        yield 'interval per layer of former versions' => [['paths' => self::PATHS, 'layers' => ['news' => ['intervalSec' => 300]]]];
        yield 'budgets of former versions' => [['paths' => self::PATHS, 'budgets' => ['laneSec' => ['fast' => 50]]]];
        yield 'misspelled key of a source' => [['paths' => self::PATHS, 'sources' => ['usgs' => ['intervall' => 600]]]];
        yield 'switch of a source as a string' => [['paths' => self::PATHS, 'sources' => ['usgs' => ['enabled' => 'false']]]];
        yield 'switch of a source as a number' => [['paths' => self::PATHS, 'sources' => ['usgs' => ['enabled' => 0]]]];
        yield 'source without its array' => [['paths' => self::PATHS, 'sources' => ['usgs' => false]]];
        yield 'lane of a source as a number' => [['paths' => self::PATHS, 'sources' => ['usgs' => ['lane' => 1]]]];
        yield 'misspelled section' => [['paths' => self::PATHS, 'stalefactor' => 3.0]];
        yield 'misspelled http limit' => [['paths' => self::PATHS, 'http' => ['maxbytes' => 1000]]];
        yield 'misspelled lane setting' => [['paths' => self::PATHS, 'lanes' => ['heavy' => ['fallback' => 60]]]];
        yield 'lane without its array' => [['paths' => self::PATHS, 'lanes' => ['fast' => 50]]];
        yield 'stale factor below 1' => [['paths' => self::PATHS, 'staleFactor' => 0.5]];
        yield 'contact with line break' => [['paths' => self::PATHS, 'http' => ['contact' => "a\nb"]]];
        yield 'auth without provider' => [['paths' => self::PATHS, 'auth' => ['settings' => []]]];
        yield 'vicinity of zero' => [['paths' => self::PATHS, 'vicinityKm' => 0]];
        yield 'misspelled auth setting' => [['paths' => self::PATHS, 'auth' => ['provider' => 'woltlab', 'setting' => []]]];
        yield 'misspelled section of a layer' => [['paths' => self::PATHS, 'layers' => ['radiation' => ['setting' => ['highUSvH' => 2.0]]]]];
        yield 'radiation thresholds of former versions' => [['paths' => self::PATHS, 'assessment' => ['radiationWarningUSvH' => 0.3]]];
        yield 'interval of a source zero' => [['paths' => self::PATHS, 'sources' => ['usgs' => ['intervalSec' => 0]]]];
        yield 'secret not a string' => [['paths' => self::PATHS, 'sources' => ['usgs' => ['secrets' => ['apiKey' => 42]]]]];
        yield 'invalid lane name' => [['paths' => self::PATHS, 'lanes' => ['Fast Lane' => ['budgetSec' => 50]]]];
        yield 'new lane without budget' => [['paths' => self::PATHS, 'lanes' => ['night' => ['fallbackSec' => 60]]]];
        yield 'timeout as text' => [['paths' => self::PATHS, 'http' => ['requestTimeoutSec' => 'viel']]];
    }

    /** @param array<mixed> $raw */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidConfigs')]
    public function testRejectsInvalidConfiguration(array $raw): void
    {
        $this->expectException(ConfigError::class);

        Config::fromArray($raw);
    }

    public function testXmlReaderRejectsEntitiesAndFindsNamespacedEntries(): void
    {
        $reader = new SafeXmlReader();
        $document = $reader->read("\u{FEFF}<?xml version=\"1.0\"?><feed xmlns=\"http://www.w3.org/2005/Atom\"><entry><id>1</id></entry><entry><id>2</id></entry></feed>", ['entry']);

        self::assertSame('feed', $document->root->localName);
        self::assertCount(2, $document->entries);

        $this->expectException(MalformedXml::class);
        $reader->read('<!DOCTYPE x [<!ENTITY a "b">]><x>&a;</x>', ['x']);
    }

    /** A DTD hidden by UTF-16 (NUL bytes between the characters) is refused before libxml could expand it. */
    public function testXmlReaderRejectsAnEntityExpansionInUtf16(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-16"?><!DOCTYPE x [<!ENTITY a "aaaaaaaaaa"><!ENTITY b "&a;&a;&a;&a;&a;"><!ENTITY c "&b;&b;&b;&b;&b;">]><x>&c;</x>';
        $utf16 = "\xFF\xFE" . mb_convert_encoding($xml, 'UTF-16LE', 'UTF-8');

        $this->expectExceptionMessage('XML in UTF-16 or UTF-32 is not processed');
        (new SafeXmlReader())->read($utf16, ['x']);
    }

    public function testXmlReaderRejectsBrokenXml(): void
    {
        $this->expectException(MalformedXml::class);

        (new SafeXmlReader())->read('<rss><channel>', ['item']);
    }
}
