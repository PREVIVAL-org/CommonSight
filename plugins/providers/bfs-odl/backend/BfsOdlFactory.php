<?php

declare(strict_types=1);

namespace CommonSight\Plugin\BfsOdl;

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

/** Ambient dose rate of the German ODL network of the Federal Office for Radiation Protection (BfS/IMIS, Q-RA-*). */
final class BfsOdlFactory implements SourcePluginFactory
{
    /** Info page of the probes: the ODL-Info of the BfS (Appendix A). */
    private const INFO_URL = 'https://odlinfo.bfs.de/';

    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: ImisRequest::SOURCE_ID,
            name: 'BfS ODL',
            // dl-de/by-2-0 asks for the source and the licence (BfS open data terms).
            attribution: new Attribution('Strahlung: Bundesamt für Strahlenschutz (BfS), ODL (Datenlizenz Deutschland – Namensnennung – 2.0)', 'https://odlinfo.bfs.de/', 'dl-de/by-2-0'),
            layer: 'radiation',
            scopes: [Scope::DE],
            schedule: new SourceSchedule(600),
            expectations: SourceExpectations::measurements(1000),
            order: 10,
        );
    }

    public function create(PluginEnvironment $environment): SourcePlugin
    {
        return new StandardSourcePlugin(new RequestParseMap($environment->http, new SourceParts(
            new ImisRequest(),
            new ImisParser(new JsonBody()),
            new ImisMapper(new UtcTimeParser(), ['DE' => self::INFO_URL]),
        )));
    }
}
