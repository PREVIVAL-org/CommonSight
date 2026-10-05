<?php

declare(strict_types=1);

namespace CommonSight\Tests\Contract;

use CommonSight\Tests\Support\Fixtures;
use CommonSight\Tests\Support\LayerCheck;
use PHPUnit\Framework\TestCase;

/** Every layer package of the repository passes the same check (concept: layers as plugins, L5). */
final class LayerContractTest extends TestCase
{
    public function testEveryLayerPackageOfTheRepositoryPassesTheCheck(): void
    {
        $problems = (new LayerCheck())->problems(dirname(Fixtures::backendDir()) . '/plugins');

        self::assertNotEmpty($problems, 'no layer package found');
        self::assertSame(array_fill_keys(array_keys($problems), []), $problems);
    }
}
