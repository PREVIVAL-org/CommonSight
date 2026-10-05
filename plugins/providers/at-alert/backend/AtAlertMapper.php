<?php

declare(strict_types=1);

namespace CommonSight\Plugin\AtAlert;

use CommonSight\Model\Item\CatalogTerm;
use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Item\SectionHeading;
use CommonSight\Model\Item\Severity;
use CommonSight\Model\Item\WarningItem;
use CommonSight\Model\Msg;
use CommonSight\Plugin\AtAlert\Record\AtAlertWarning;
use CommonSight\Sdk\Source\ItemMapper;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\RecordType;
use CommonSight\Sdk\Source\WarningSections;

/**
 * Maps a warning of AT-Alert to a civil protection warning. The EU-Alert levels become severities; a test of the
 * warning system keeps its texts but counts as minor and is named "Probealarm", so that it never looks like danger.
 */
final class AtAlertMapper implements ItemMapper
{
    private const LEVELS = [
        'AlertLevel1' => Severity::Extreme,
        'AlertLevel2' => Severity::Extreme,
        'AlertLevel3' => Severity::Severe,
        'AlertLevel4' => Severity::Moderate,
        'Amber' => Severity::Moderate,
    ];

    public function __construct(private readonly WarningSections $sections) {}

    public function map(object $record, ParseContext $context): Item
    {
        $record = RecordType::expect($record, AtAlertWarning::class);
        $common = new ItemCommon(
            id: 'at-alert:' . $record->id,
            title: $record->title !== '' ? $record->title : 'AT-Alert',
            url: 'https://warnungen.at-alert.at/de/alerts/' . rawurlencode($record->id),
            source: 'AT-Alert',
            time: $record->sent,
            geometry: $record->geometry,
            lang: 'de',
        );

        return new WarningItem(
            $common,
            new Msg($record->test ? 'source.at-alert.level.test' : 'source.at-alert.level.' . (isset(self::LEVELS[$record->level]) ? $record->level : 'unknown')),
            $record->test ? Severity::Minor : (self::LEVELS[$record->level] ?? Severity::Unknown),
            $record->area,
            $record->sent,
            $record->expires,
            $this->sections->of([SectionHeading::Description->value => $record->text]),
            new CatalogTerm('civilProtection'),
        );
    }
}
