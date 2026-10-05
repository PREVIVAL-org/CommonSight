<?php

declare(strict_types=1);

namespace CommonSight\Plugin\GeoSphere;

use CommonSight\Model\Decoded;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Plugin\GeoSphere\Record\DetailWarning;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Text\TextCleaner;

/** Converts the response of getWarningsForCoords into DetailWarnings. */
final class GeoSphereDetailParser
{
    public function __construct(private readonly JsonBody $json, private readonly TextCleaner $text) {}

    /** @return list<DetailWarning> */
    public function parse(HttpResponse $response): array
    {
        $data = $this->json->decode($response);
        if ($data->get('type')->string() !== 'Feature') {
            return [];
        }

        return array_map($this->warning(...), $data->get('properties', 'warnings')->list());
    }

    private function warning(Decoded $warning): DetailWarning
    {
        $p = $warning->get('properties');
        $raw = $p->get('rawinfo');
        $create = $p->get('create')->string();

        return new DetailWarning(
            $p->get('warnid')->text() ?? '',
            $p->get('chgid')->text() ?? '',
            $p->get('verlaufid')->text() ?? '',
            $raw->get('wtype')->int() ?? 0,
            $raw->get('wlevel')->int() ?? 0,
            $raw->get('start')->int(),
            $raw->get('end')->int(),
            $this->text->clean($p->get('text')->raw()),
            $this->text->clean($p->get('meteotext')->raw()),
            $this->text->clean($p->get('auswirkungen')->raw()),
            $this->text->clean($p->get('empfehlungen')->raw()),
            $this->text->clean($p->get('updategrund')->raw()),
            $create !== '' ? $create : null,
        );
    }
}
