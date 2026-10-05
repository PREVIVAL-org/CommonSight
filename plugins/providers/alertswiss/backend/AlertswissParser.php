<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Alertswiss;

use CommonSight\Model\Decoded;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Plugin\Alertswiss\Record\AlertswissAlert;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\ParseCounter;
use CommonSight\Sdk\Source\ParseResult;
use CommonSight\Sdk\Source\SourceParser;
use CommonSight\Sdk\Source\UnreadableResponse;
use CommonSight\Sdk\Text\TextCleaner;
use CommonSight\Sdk\Text\UtcTimeParser;

/**
 * Converts the current alerts of alert.swiss into records. Test alerts (testAlert, technicalTestAlert) and all-clear
 * messages are no warnings and are skipped. The feed lists only current alerts: an alert is valid as long as it is in it.
 */
final class AlertswissParser implements SourceParser
{
    /** Line break inside the texts of Alertswiss. */
    private const BREAK = '❘';

    public function __construct(
        private readonly JsonBody $json,
        private readonly UtcTimeParser $time,
        private readonly TextCleaner $text,
        private readonly AlertAreas $areas,
    ) {}

    public function parse(HttpResponse $response, ParseContext $context): ParseResult
    {
        $alerts = $this->json->decode($response)->get('alerts');
        if (!$alerts->isList()) {
            throw new UnreadableResponse('Alertswiss response without alerts');
        }
        $counter = new ParseCounter();
        $records = [];
        foreach ($alerts->list() as $alert) {
            if ($alert->get('testAlert')->bool() === true || $alert->get('technicalTestAlert')->bool() === true || $alert->get('allClear')->bool() === true) {
                $counter->skipped();
                continue;
            }
            $record = $this->record($alert);
            if ($record === null) {
                $counter->rejected('missingId');
                continue;
            }
            $counter->valid();
            $records[] = $record;
        }

        return new ParseResult($records, $counter->statistics());
    }

    private function record(Decoded $alert): ?AlertswissAlert
    {
        $id = $alert->get('identifier')->string();
        if ($id === null || preg_match('/^[A-Za-z0-9._-]+$/D', $id) !== 1) {
            return null;
        }
        $areas = $alert->get('areas')->list();

        return new AlertswissAlert(
            $id,
            $this->clean($alert->get('title', 'title')->raw()),
            $this->clean($alert->get('event')->raw()),
            $alert->get('severity')->string() ?? 'unknown',
            $this->sent($alert),
            $this->clean($alert->get('description', 'description')->raw()),
            implode(' ', array_filter(array_map(fn(Decoded $i): string => $this->clean($i->get('text')->raw()), $alert->get('instructions')->list()))),
            implode(', ', array_filter(array_map(fn(Decoded $a): string => $this->clean($a->get('description', 'description')->raw()), $areas))),
            $this->clean($alert->get('publisherName')->raw()) ?: null,
            $alert->get('links', 0, 'href')->string(),
            $this->areas->geometry($areas),
        );
    }

    /** The time of the alert from its CAP reference "sender,identifier,sent" (the field sent is a localized text). */
    private function sent(Decoded $alert): ?UtcInstant
    {
        $parts = explode(',', (string) $alert->get('reference')->string());

        return $this->time->parseUtc(end($parts));
    }

    private function clean(mixed $value): string
    {
        return $this->text->clean(is_string($value) ? str_replace(self::BREAK, ' ', $value) : $value);
    }
}
