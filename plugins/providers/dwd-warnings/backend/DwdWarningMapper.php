<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Dwd;

use CommonSight\Model\Item\CatalogTerm;
use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Item\SectionHeading;
use CommonSight\Model\Item\Severity;
use CommonSight\Model\Item\WarningItem;
use CommonSight\Model\Msg;
use CommonSight\Plugin\Dwd\Record\DwdWarningFeature;
use CommonSight\Sdk\Geo\GeometryRounding;
use CommonSight\Sdk\Source\ItemMapper;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\RecordType;
use CommonSight\Sdk\Source\WarningSections;
use CommonSight\Sdk\Text\SafeUrl;

/** Maps a DWD warning to a warning of the model (Q-W-DE-02). */
final class DwdWarningMapper implements ItemMapper
{
    private const FALLBACK_URL = 'https://www.dwd.de/warnungen';

    public function __construct(
        private readonly SafeUrl $url,
        private readonly GeometryRounding $rounding,
        private readonly WarningSections $sections,
    ) {}

    public function map(object $record, ParseContext $context): Item
    {
        $record = RecordType::expect($record, DwdWarningFeature::class);
        $common = new ItemCommon(
            id: 'dwd:' . $record->id,
            title: $record->HEADLINE !== '' ? $record->HEADLINE : $record->EVENT,
            url: $this->url->orFallback($record->WEB, self::FALLBACK_URL),
            source: 'Deutscher Wetterdienst',
            time: $record->SENT,
            geometry: $record->geometry === null ? null : $this->rounding->round($record->geometry),
            lang: 'de',
        );

        return new WarningItem(
            $common,
            $record->EVENT !== '' ? new Msg('hazard.source', ['text' => $record->EVENT]) : new Msg('hazard.weather'),
            Severity::fromSource($record->SEVERITY),
            $record->AREADESC,
            $record->ONSET,
            $record->EXPIRES,
            $this->sections->of([SectionHeading::Description->value => $record->DESCRIPTION, SectionHeading::Advice->value => $record->INSTRUCTION]),
            new CatalogTerm('weather'),
        );
    }
}
