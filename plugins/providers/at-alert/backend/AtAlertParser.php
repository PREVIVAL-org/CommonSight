<?php

declare(strict_types=1);

namespace CommonSight\Plugin\AtAlert;

use CommonSight\Model\Decoded;
use CommonSight\Model\Geometry;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Plugin\AtAlert\Record\AtAlertWarning;
use CommonSight\Sdk\Geo\GeometryRounding;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\ParseCounter;
use CommonSight\Sdk\Source\ParseResult;
use CommonSight\Sdk\Source\SourceParser;
use CommonSight\Sdk\Source\UnreadableResponse;
use CommonSight\Sdk\Text\TextCleaner;
use CommonSight\Sdk\Text\UtcTimeParser;

/**
 * Converts the warning list of warnungen.at-alert.at into records. A test of the warning system that is sent at a real
 * level (the Zivilschutz-Probealarm on the first Saturday of October) is kept and marked: everybody received it on
 * the phone. The total count of the list tells whether it was cut off at the limit.
 */
final class AtAlertParser implements SourceParser
{
    /** Words of the tests in title or text (Probealarm, "SYSTEMTEST für AT-ALERT", "AT-Alert Testnachricht", ...). */
    private const TEST_WORDS = '/probealarm|probewarnung|testalarm|testalert|test alert|übungsalarm|systemtest|funktionstest|testnachricht|amtlicher test/iu';
    /** A title that starts with the word test, e.g. "TEST - TEST - TEST" of the interior ministry. */
    private const TEST_TITLE = '/^test\b/iu';
    /** Area description the warning centres leave when they name none. */
    private const NO_AREA = 'DefaultAreaDescription';

    public function __construct(
        private readonly JsonBody $json,
        private readonly UtcTimeParser $time,
        private readonly TextCleaner $text,
        private readonly GeometryRounding $rounding,
        private readonly GermanText $german = new GermanText(),
    ) {}

    public function parse(HttpResponse $response, ParseContext $context): ParseResult
    {
        $list = $this->json->decode($response)->get('json');
        $alerts = $list->get('alerts');
        if (!$alerts->isList()) {
            throw new UnreadableResponse('AT-Alert response without alerts');
        }
        $counter = new ParseCounter();
        $records = [];
        foreach ($alerts->list() as $alert) {
            $record = $this->record($alert);
            if ($record === null) {
                $counter->rejected('missingId');
                continue;
            }
            $counter->valid();
            $records[] = $record;
        }

        return new ParseResult($records, $counter->statistics(), null, $list->get('totalCount')->int());
    }

    private function record(Decoded $alert): ?AtAlertWarning
    {
        $id = $alert->get('consolidation_identifier')->string();
        if ($id === null || preg_match('/^[A-Za-z0-9._-]+$/D', $id) !== 1) {
            return null;
        }
        $title = $this->text->clean($alert->get('title')->raw());

        return new AtAlertWarning(
            $id,
            $alert->get('alert_level')->string() ?? '',
            $title,
            $this->body($alert, $title),
            $this->area($alert),
            $this->time->parseUtc($alert->get('sent')->raw()),
            $this->time->parseUtc($alert->get('info_expires')->raw()),
            $this->isTest($title, $this->text->clean($alert->get('info_description')->raw())),
            $this->geometry($alert->get('geometries')->list()),
        );
    }

    private function isTest(string $title, string $text): bool
    {
        return preg_match(self::TEST_TITLE, $title) === 1 || preg_match(self::TEST_WORDS, $title . ' ' . $text) === 1;
    }

    /** The text of the warning without the title it repeats at its start. */
    private function body(Decoded $alert, string $title): string
    {
        // The English version the warning centres add is left out (before the cleaner joins the lines).
        $raw = $alert->get('info_description')->string();
        $text = $this->text->clean($raw === null ? null : $this->german->of($raw));
        if ($text === '') {
            $text = $this->text->clean($alert->get('description')->raw());
        }

        return $title !== '' && str_starts_with($text, $title) ? trim(substr($text, strlen($title))) : $text;
    }

    /** The states or districts the warning was sent to; the area description only when it names one. */
    private function area(Decoded $alert): string
    {
        $names = array_values(array_unique(array_filter(array_map(
            static fn(Decoded $p): string => trim((string) $p->get('name')->string()),
            $alert->get('matched_polygons')->list(),
        ))));
        if ($names !== []) {
            return implode(', ', $names);
        }
        $description = $this->text->clean($alert->get('info_area_description')->raw());

        return $description === self::NO_AREA ? '' : $description;
    }

    /** @param list<Decoded> $geometries GeoJSON polygons and multipolygons */
    private function geometry(array $geometries): ?Geometry
    {
        $polygons = [];
        foreach ($geometries as $geometry) {
            $parts = match ($geometry->get('type')->string()) {
                'Polygon' => [$geometry->get('coordinates')->array()],
                'MultiPolygon' => array_map(static fn(Decoded $p): array => $p->array(), $geometry->get('coordinates')->list()),
                default => [],
            };
            array_push($polygons, ...array_values(array_filter($parts, $this->isPolygon(...))));
        }
        if ($polygons === []) {
            return null;
        }

        return $this->rounding->round(count($polygons) === 1 ? Geometry::fromGeoJson('Polygon', $polygons[0]) : Geometry::fromGeoJson('MultiPolygon', $polygons));
    }

    /** @param array<mixed> $polygon */
    private function isPolygon(array $polygon): bool
    {
        try {
            Geometry::fromGeoJson('Polygon', $polygon);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
