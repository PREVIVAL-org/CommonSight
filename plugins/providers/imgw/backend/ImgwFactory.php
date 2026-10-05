<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Imgw;

use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Plugin\Attribution;
use CommonSight\Sdk\Plugin\PluginEnvironment;
use CommonSight\Sdk\Plugin\SourceDescription;
use CommonSight\Sdk\Plugin\SourcePlugin;
use CommonSight\Sdk\Plugin\SourcePluginFactory;
use CommonSight\Sdk\Plugin\SourceSchedule;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\RequestParseMap;
use CommonSight\Sdk\Source\SourceExpectations;
use CommonSight\Sdk\Source\SourceParts;
use CommonSight\Sdk\Source\StandardSourcePlugin;
use CommonSight\Sdk\Station\WaterReadingMapper;
use CommonSight\Sdk\Text\NumberParser;
use CommonSight\Sdk\Text\UtcTimeParser;

/** Border zone, Poland: IMGW-PIB gauges with warning and alarm level. */
final class ImgwFactory implements SourcePluginFactory
{
    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: 'imgw',
            name: 'IMGW-PIB',
            attribution: new Attribution('Pegel: Źródłem pochodzenia danych jest Instytut Meteorologii i Gospodarki Wodnej – Państwowy Instytut Badawczy, dane przetworzone (CC BY 4.0)', 'https://danepubliczne.imgw.pl/regulations'),
            layer: 'water',
            scopes: [Scope::Border],
            schedule: new SourceSchedule(600),
            expectations: SourceExpectations::measurements(400),
            order: 40,
        );
    }

    public function create(PluginEnvironment $environment): SourcePlugin
    {
        return new StandardSourcePlugin(new RequestParseMap($environment->http, new SourceParts(
            new ImgwRequest(),
            new ImgwParser(new JsonBody(), new NumberParser(), new UtcTimeParser()),
            new WaterReadingMapper(new ImgwStageAssessor()),
        )));
    }
}
