<?php

declare(strict_types=1);

namespace CommonSight\Tests\Contract;

use CommonSight\Tests\Support\Fixtures;
use CommonSight\Tests\Support\PluginCheck;
use PHPUnit\Framework\TestCase;

/**
 * The generic plugin test (concept: sources as plugins, 7): every plugin in plugins/ is checked the same way, without
 * any entry per plugin. The example plugins under tests/Fixtures show that the check passes and fails as it should.
 */
final class PluginContractTest extends TestCase
{
    public function testEveryPluginOfTheRepositoryPassesTheCheck(): void
    {
        $problems = (new PluginCheck())->problems(dirname(Fixtures::backendDir()) . '/plugins');

        self::assertSame(array_fill_keys(array_keys($problems), []), $problems);
    }

    public function testAValidPluginPasses(): void
    {
        self::assertSame(['example' => []], (new PluginCheck())->problems(Fixtures::backendDir() . '/tests/Fixtures/plugins'));
    }

    public function testFindsEveryProblemOfAFaultyPlugin(): void
    {
        $problems = (new PluginCheck())->problems(Fixtures::backendDir() . '/tests/Fixtures/plugins-broken')['broken'] ?? [];

        self::assertCount(4, $problems, implode("\n", $problems));
        self::assertSame('PROFILE.md missing', $problems[0]);
        self::assertStringStartsWith('AT: item broken:1 violates the schema:', $problems[1]);
        self::assertStringContainsString('/quantity', $problems[1], 'the quantity snowDepth, which no catalog knows');
        self::assertSame('AT: item broken:1: text key without text: source.broken.unknown', $problems[2]);
        self::assertSame('CH: no recording in tests/responses/CH/', $problems[3]);
    }
}
