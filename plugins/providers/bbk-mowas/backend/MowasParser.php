<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Mowas;

use CommonSight\Model\Decoded;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Plugin\Mowas\Record\MowasWarning;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\ParseCounter;
use CommonSight\Sdk\Source\ParseResult;
use CommonSight\Sdk\Source\SourceParser;
use CommonSight\Sdk\Source\UnreadableResponse;
use CommonSight\Sdk\Text\TextCleaner;
use CommonSight\Sdk\Text\UtcTimeParser;

/** Converts the mapData.json of a NINA feed into MowasWarnings; skips cancellations (Cancel) and expired messages. */
final class MowasParser implements SourceParser
{
    public function __construct(
        private readonly JsonBody $json,
        private readonly UtcTimeParser $time,
        private readonly TextCleaner $text,
    ) {}

    public function parse(HttpResponse $response, ParseContext $context): ParseResult
    {
        $data = $this->json->decode($response);
        if (!$data->isList()) {
            throw new UnreadableResponse('MoWaS overview is not a list');
        }
        $feed = NinaFeed::tryFrom($response->request->key) ?? NinaFeed::Mowas;
        $counter = new ParseCounter();
        $records = [];
        foreach ($data->list() as $entry) {
            $record = $this->record($entry, $feed);
            if ($record === null) {
                $counter->rejected('missingId');
            } elseif ($entry->get('type')->string() === 'Cancel' || ($record->expiresDate !== null && !$record->expiresDate->isAfter($context->now))) {
                $counter->skipped();
            } else {
                $counter->valid();
                $records[] = $record;
            }
        }

        return new ParseResult($records, $counter->statistics());
    }

    private function record(Decoded $entry, NinaFeed $feed): ?MowasWarning
    {
        $id = $entry->get('id')->string();
        if ($id === null || preg_match('/^[A-Za-z0-9._-]+$/D', $id) !== 1) {
            return null;
        }
        $title = $this->text->clean($entry->get('i18nTitle', 'de')->raw());

        return new MowasWarning(
            $id,
            $entry->get('version')->int() ?? 0,
            $title !== '' ? $title : null,
            $this->time->parseUtc($entry->get('startDate')->raw()),
            $this->time->parseUtc($entry->get('expiresDate')->raw()),
            $entry->get('severity')->string() ?? 'Unknown',
            $feed,
        );
    }
}
