<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Alertswiss;

use CommonSight\Model\Item\CatalogTerm;
use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Item\SectionHeading;
use CommonSight\Model\Item\Severity;
use CommonSight\Model\Item\WarningItem;
use CommonSight\Model\Msg;
use CommonSight\Plugin\Alertswiss\Record\AlertswissAlert;
use CommonSight\Sdk\Source\ItemMapper;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\RecordType;
use CommonSight\Sdk\Source\WarningSections;
use CommonSight\Sdk\Text\SafeUrl;

/** Maps an alert of Alertswiss to a civil protection warning, named after its event and its canton. */
final class AlertswissMapper implements ItemMapper
{
    private const FALLBACK_URL = 'https://www.alert.swiss/';

    public function __construct(private readonly SafeUrl $url, private readonly WarningSections $sections) {}

    public function map(object $record, ParseContext $context): Item
    {
        $record = RecordType::expect($record, AlertswissAlert::class);
        $common = new ItemCommon(
            id: 'alertswiss:' . $record->id,
            title: $record->title !== '' ? $record->title : ($record->event !== '' ? $record->event : 'Alertswiss'),
            url: $this->url->orFallback($record->link, self::FALLBACK_URL),
            source: 'Alertswiss · ' . ($record->publisher ?? 'BABS'),
            time: $record->sent,
            geometry: $record->geometry,
            lang: 'de',
        );

        return new WarningItem(
            $common,
            $record->event !== '' ? new Msg('hazard.source', ['text' => $record->event]) : new Msg('hazard.civilProtection'),
            Severity::fromSource($record->severity),
            $record->areaDescription,
            $record->sent,
            null,
            $this->sections->of([SectionHeading::Description->value => $record->description, SectionHeading::Advice->value => $record->instructions]),
            new CatalogTerm('civilProtection'),
        );
    }
}
