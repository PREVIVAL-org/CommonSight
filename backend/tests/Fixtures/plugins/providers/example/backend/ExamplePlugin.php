<?php

declare(strict_types=1);

namespace CommonSight\Tests\Fixtures\Plugins\Example;

use CommonSight\Model\Http\HttpFailure;
use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Item\CatalogTerm;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Item\ModelValueItem;
use CommonSight\Model\Msg;
use CommonSight\Model\Value\Coordinate;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Port\HttpClient;
use CommonSight\Sdk\Plugin\SourceOutcome;
use CommonSight\Sdk\Plugin\SourcePlugin;
use CommonSight\Sdk\Plugin\SourceRun;
use CommonSight\Sdk\Source\ParseStatistics;

/** Fetches the temperature of one place and maps it to a model value, with a text of its own (source.example.*). */
final class ExamplePlugin implements SourcePlugin
{
    public function __construct(private readonly HttpClient $http) {}

    public function fetch(SourceRun $run): SourceOutcome
    {
        $request = HttpRequest::withQuery('https://api.example.org/v1/temperature', ['place' => 'wien'], 'application/json', 'example');
        $response = $this->http->fetchAll([$request])[0] ?? null;
        if ($response === null || $response instanceof HttpFailure) {
            return SourceOutcome::failure($response?->reason ?? 'no response');
        }
        $data = json_decode($response->body, true);
        if (!is_array($data) || !is_float($data['temperature'] ?? null)) {
            return SourceOutcome::failure('unreadable response');
        }
        $item = new ModelValueItem(
            new ItemCommon(
                id: 'example:wien',
                title: 'Wien',
                url: 'https://api.example.org/',
                time: UtcInstant::fromIso((string) $data['time']),
                position: Coordinate::fromLatLon((float) $data['lat'], (float) $data['lon']),
            ),
            new CatalogTerm('temperature'),
            $data['temperature'],
            '°C',
            new Msg('source.example.mild'),
        );

        return SourceOutcome::success([$item], new ParseStatistics(1, 0, 0));
    }
}
