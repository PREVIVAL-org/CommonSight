<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Lindas;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\SourceRequest;

/** Names the SPARQL query to LINDAS: per BAFU station only the latest observation, complete (Q-WA-CH-01). */
final class LindasRequest implements SourceRequest
{
    public const SOURCE_ID = 'bafu-lindas';

    private const QUERY = <<<'SPARQL'
        PREFIX d: <https://environment.ld.admin.ch/foen/hydro/dimension/>
        PREFIX schema: <http://schema.org/>
        PREFIX geo: <http://www.opengis.net/ont/geosparql#>
        SELECT ?id ?name ?waterName ?wkt ?time ?level ?flow ?temperature ?danger
        FROM <https://lindas.admin.ch/foen/hydro>
        WHERE {
          {
            SELECT ?station (MAX(?t) AS ?time)
            WHERE { ?o a <https://cube.link/Observation>; d:station ?station; d:measurementTime ?t. }
            GROUP BY ?station
          }
          ?observation a <https://cube.link/Observation>; d:station ?station; d:measurementTime ?time.
          ?station schema:identifier ?id; schema:name ?name; geo:hasGeometry/geo:asWKT ?wkt.
          OPTIONAL { ?station schema:containedInPlace/schema:name ?waterName. }
          OPTIONAL { ?observation d:waterLevel ?level. }
          OPTIONAL { ?observation d:discharge ?flow. }
          OPTIONAL { ?observation d:waterTemperature ?temperature. }
          OPTIONAL { ?observation d:dangerLevel ?danger. }
        }
        SPARQL;

    public function requestsFor(Scope $scope, UtcInstant $now): array
    {
        return [HttpRequest::withQuery('https://lindas.admin.ch/query', ['query' => self::QUERY], 'application/sparql-results+json', self::SOURCE_ID)];
    }
}
