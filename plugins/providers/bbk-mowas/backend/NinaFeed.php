<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Mowas;

use CommonSight\Model\Item\CatalogTerm;
use CommonSight\Model\Msg;

/**
 * The warning systems NINA publishes besides the weather warnings of the DWD (which come from their own source): all in
 * the same format at warnung.bund.de/api31/<feed>/mapData.json, their areas at warnings/<id>.geojson (Q-W-DE-03).
 */
enum NinaFeed: string
{
    /** Modular warning system of the federal government and the states (BBK). */
    case Mowas = 'mowas';
    /** Warnings of the authorities that use KATWARN. */
    case Katwarn = 'katwarn';
    /** Warnings of the authorities that use BIWAPP. */
    case Biwapp = 'biwapp';
    /** Police messages, e.g. a search or a dangerous situation. */
    case Police = 'police';
    /** Flood messages of the states (Länderübergreifendes Hochwasserportal). */
    case Lhp = 'lhp';

    public function overviewUrl(): string
    {
        return 'https://warnung.bund.de/api31/' . $this->value . '/mapData.json';
    }

    /** Prefix of the item ids, the name of the feed (mowas: as before the other feeds were added). */
    public function idPrefix(): string
    {
        return $this->value . ':';
    }

    /** Where the message comes from, as shown with it. */
    public function label(): string
    {
        return 'BBK / NINA · ' . match ($this) {
            self::Mowas => 'MoWaS',
            self::Katwarn => 'KATWARN',
            self::Biwapp => 'BIWAPP',
            self::Police => 'Polizei',
            self::Lhp => 'Hochwasserportal',
        };
    }

    public function hazard(): Msg
    {
        return match ($this) {
            self::Police => new Msg('source.bbk-mowas.hazard.police'),
            self::Lhp => new Msg('source.bbk-mowas.hazard.flood'),
            default => new Msg('hazard.civilProtection'),
        };
    }

    public function category(): CatalogTerm
    {
        return new CatalogTerm(match ($this) {
            self::Police => 'police',
            self::Lhp => 'flood',
            default => 'civilProtection',
        });
    }
}
