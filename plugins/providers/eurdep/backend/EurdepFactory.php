<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Eurdep;

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
use CommonSight\Sdk\Text\UtcTimeParser;

/** Ambient dose rate of Switzerland from the European exchange platform EURDEP, as published by BfS/IMIS (Q-RA-*). */
final class EurdepFactory implements SourcePluginFactory
{
    /** Info page of the probes: the daily means of the NAZ (Appendix A). */
    private const INFO_URL = 'https://www.naz.ch/de/aktuell/tagesmittelwerte';

    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: ImisRequest::SOURCE_ID,
            name: 'EURDEP',
            attribution: new Attribution('Strahlung: EURDEP (Schweiz) über BfS', 'https://www.imis.bfs.de/'),
            layer: 'radiation',
            scopes: [Scope::CH],
            schedule: new SourceSchedule(600),
            expectations: SourceExpectations::measurements(30),
            order: 10,
        );
    }

    public function create(PluginEnvironment $environment): SourcePlugin
    {
        return new StandardSourcePlugin(new RequestParseMap($environment->http, new SourceParts(
            new ImisRequest(),
            new ImisParser(new JsonBody()),
            new ImisMapper(new UtcTimeParser(), ['CH' => self::INFO_URL]),
        )));
    }
}
