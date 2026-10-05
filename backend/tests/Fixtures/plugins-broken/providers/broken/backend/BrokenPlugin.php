<?php

declare(strict_types=1);

namespace CommonSight\Tests\Fixtures\Plugins\Broken;

use CommonSight\Model\Item\CatalogTerm;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Item\ModelValueItem;
use CommonSight\Model\Msg;
use CommonSight\Sdk\Plugin\SourceOutcome;
use CommonSight\Sdk\Plugin\SourcePlugin;
use CommonSight\Sdk\Plugin\SourceRun;
use CommonSight\Sdk\Source\ParseStatistics;

/** Delivers a quantity no catalog knows and a text key without text. */
final class BrokenPlugin implements SourcePlugin
{
    public function fetch(SourceRun $run): SourceOutcome
    {
        $item = new ModelValueItem(
            new ItemCommon('broken:1', 'Irgendwo', 'https://api.example.org/'),
            new CatalogTerm('snowDepth'),
            12.0,
            'cm',
            new Msg('source.broken.unknown'),
        );

        return SourceOutcome::success([$item], new ParseStatistics(1, 0, 0));
    }
}
