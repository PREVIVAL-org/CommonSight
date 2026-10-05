<?php

declare(strict_types=1);

namespace CommonSight\Tests\Infrastructure;

use CommonSight\Infrastructure\Contract\CatalogBuilder;
use CommonSight\Infrastructure\Contract\MessageCollector;
use CommonSight\Infrastructure\Contract\SourceListing;
use CommonSight\Infrastructure\Plugin\PluginDiscovery;
use CommonSight\Sdk\Plugin\SourceDescription;
use CommonSight\Tests\Support\Fixtures;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

/** V14, V17, V10: catalog terms and texts are contributed by the core and the plugins and checked when joined. */
final class CatalogTest extends TestCase
{
    public function testJoinsCoreAndPluginTermsInOrder(): void
    {
        $catalog = (new CatalogBuilder())->join(
            ['measuredQuantities' => [['id' => 'waterLevel', 'label' => 'Wasserstand', 'definition' => 'Level.']]],
            ['snow' => ['measuredQuantities' => [['id' => 'snowDepth', 'label' => 'Schneehöhe', 'definition' => 'Depth of snow.']]]],
        );

        self::assertSame(['waterLevel', 'snowDepth'], array_column($catalog['measuredQuantities'], 'id'));
        self::assertSame(['core', 'snow'], array_column($catalog['measuredQuantities'], 'contributedBy'));
        self::assertSame([], $catalog['newsFeeds']);
    }

    public function testRejectsDuplicatesAndIncompleteTermsAllAtOnce(): void
    {
        try {
            (new CatalogBuilder())->join(
                ['measuredQuantities' => [['id' => 'waterLevel', 'label' => 'Wasserstand', 'definition' => 'Level.']]],
                [
                    'copy' => ['measuredQuantities' => [['id' => 'waterLevel', 'label' => 'Pegel', 'definition' => 'Same.']]],
                    'sloppy' => [
                        'modelQuantities' => [['id' => 'pressure', 'label' => 'Luftdruck', 'definition' => 'Pressure.']],
                        'newsFeeds' => [['id' => 'feed', 'label' => 'Feed', 'definition' => 'A feed.', 'country' => 'Deutschland']],
                        'volcanoes' => [],
                    ],
                ],
            );
            self::fail('invalid terms accepted');
        } catch (AssertionFailedError $e) {
            throw $e; // self::fail() above, not the expected error
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('copy: measuredQuantities "waterLevel": already contributed by core', $e->getMessage());
            self::assertStringContainsString('sloppy: modelQuantities "pressure": decimals missing', $e->getMessage());
            self::assertStringContainsString('sloppy: newsFeeds "feed": country must be an ISO 3166-1 code', $e->getMessage());
            self::assertStringContainsString('sloppy: unknown catalog list "volcanoes"', $e->getMessage());
        }
    }

    public function testDerivesTheSchemaDefinitions(): void
    {
        $builder = new CatalogBuilder();
        $schema = $builder->schema($builder->join(['newsCategories' => [['id' => 'weather', 'label' => 'Unwetter', 'definition' => 'W.']]], []));

        self::assertSame(['type' => 'string', 'enum' => ['weather']], $schema['$defs']['newsCategory']);
        self::assertSame(['type' => 'string', 'enum' => []], $schema['$defs']['newsFeed']);
    }

    public function testGeneratedContractFilesMatchTheirSources(): void
    {
        $contract = Fixtures::contractDir();
        $builder = new CatalogBuilder();
        $catalog = $builder->join($this->json($contract . '/catalog/builtin.json'), $this->pluginFiles('catalog.json'));

        self::assertEquals($catalog, $this->json($contract . '/catalog/catalog.json'), 'contract/catalog/catalog.json is outdated: run bin/build-contract.php');
        self::assertEquals($builder->schema($catalog), $this->json($contract . '/schema/catalog.schema.json'), 'contract/schema/catalog.schema.json is outdated');
        $descriptions = array_values(array_filter(array_map(static fn(array $p) => (new $p['factory']())->describe(), (new PluginDiscovery())->discover(dirname($contract) . '/plugins')), static fn($d): bool => $d instanceof SourceDescription));
        self::assertEquals((new SourceListing())->list($descriptions), $this->json($contract . '/catalog/sources.json'), 'contract/catalog/sources.json is outdated');
    }

    public function testPluginTextsStayInTheirNamespace(): void
    {
        $collector = new MessageCollector();

        self::assertSame(
            ['source.demo.index.name' => 'Demo-Index'],
            $collector->collect(['note.space' => 'Weltraumwetter'], ['demo' => ['source.demo.index.name' => 'Demo-Index']]),
        );

        $plural = ['one' => '{count} Warnung', 'other' => '{count} Warnungen'];
        self::assertSame(['source.demo.count' => $plural], $collector->collect([], ['demo' => ['source.demo.count' => $plural]]));

        try {
            $collector->collect(['note.space' => 'Weltraumwetter'], ['demo' => ['source.other.name' => 'X', 'note.space' => 'Überschrieben', 'source.demo.empty' => ' ', 'source.demo.plural' => ['one' => 'x', 'few' => 'y']]]);
            self::fail('invalid texts accepted');
        } catch (AssertionFailedError $e) {
            throw $e; // self::fail() above, not the expected error
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('demo: "source.other.name" outside the namespace source.demo.', $e->getMessage());
            self::assertStringContainsString('demo: "note.space" outside the namespace', $e->getMessage());
            self::assertStringContainsString('demo: "source.demo.empty" empty text', $e->getMessage());
            self::assertStringContainsString('demo: "source.demo.plural" plural forms must be exactly "one" and "other"', $e->getMessage());
        }
    }

    /** @return array<mixed> */
    private function json(string $path): array
    {
        return (array) json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<array<mixed>> */
    private function pluginFiles(string $file): array
    {
        $found = [];
        foreach (glob(dirname(Fixtures::contractDir()) . '/plugins/*/*/' . $file) ?: [] as $path) {
            $found[basename(dirname($path))] = $this->json($path);
        }

        return $found;
    }
}
