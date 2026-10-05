<?php

declare(strict_types=1);

namespace CommonSight\Model\Value;

/** Scope of a snapshot: a country, country-independent or the border zone beyond DACH. */
enum Scope: string
{
    case DE = 'DE';
    case AT = 'AT';
    case CH = 'CH';
    case Global = 'global';
    case Border = 'border';

    public function isCountry(): bool
    {
        return $this !== self::Global && $this !== self::Border;
    }

    /** @return list<self> */
    public static function countries(): array
    {
        return [self::DE, self::AT, self::CH];
    }
}
