<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Ehyd;

use CommonSight\Model\Fact;
use CommonSight\Model\Item\CatalogTerm;
use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Item\MeasurementItem;
use CommonSight\Model\Msg;
use CommonSight\Model\Value\Coordinate;
use CommonSight\Plugin\Ehyd\Record\EhydGauge;
use CommonSight\Sdk\Source\ItemMapper;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\RecordType;
use CommonSight\Sdk\Text\SafeUrl;
use CommonSight\Sdk\Text\UtcTimeParser;

/** Maps an eHYD water gauge to discharge (parameter Q) or water level with assessment per B-11 (Q-WA-AT-02, -03). */
final class EhydMapper implements ItemMapper
{
    public function __construct(
        private readonly AustrianWaterAssessor $assessor,
        private readonly UtcTimeParser $time,
        private readonly SafeUrl $url,
        private readonly \DateTimeZone $sourceZone,
    ) {}

    public function map(object $record, ParseContext $context): Item
    {
        $record = RecordType::expect($record, EhydGauge::class);
        $isDischarge = strtoupper($record->parameter) === 'Q';
        $facts = $record->hd === '' ? [] : [new Fact(new Msg('source.ehyd.fact.hydroService'), $record->hd)];

        return new MeasurementItem(
            new ItemCommon(
                id: 'ehyd:' . $record->hzbnr,
                title: implode(' · ', array_filter([$record->messstelle, $record->gewasser])),
                url: $this->url->orFallback($record->internet, 'https://ehyd.gv.at/'),
                time: $this->time->parse($record->zp, $this->sourceZone),
                position: Coordinate::fromLonLat($record->lon, $record->lat),
            ),
            $isDischarge ? new CatalogTerm('discharge') : new CatalogTerm('waterLevel'),
            $record->wert,
            $record->einheit !== '' ? $record->einheit : ($isDischarge ? 'm³/s' : 'cm'),
            new Msg($this->reference($isDischarge, $record->einheit)),
            $this->assessor->assess($record->gesamtcode),
            $facts,
        );
    }

    /** eHYD delivers some water levels in "m ü.A" (meters above the Adriatic), i.e. above sea level, not above gauge zero. */
    private function reference(bool $isDischarge, string $unit): string
    {
        return match (true) {
            $isDischarge => 'reference.dischargeAtGauge',
            preg_match('/ü\.?\s*A\b/u', $unit) === 1 => 'reference.seaLevel',
            default => 'reference.gaugeZero',
        };
    }
}
