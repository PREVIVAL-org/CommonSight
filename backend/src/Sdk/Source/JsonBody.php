<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Source;

use CommonSight\Model\Decoded;
use CommonSight\Model\Http\HttpResponse;

/** Decodes the JSON body of a response; broken JSON makes the response unevaluable. */
final class JsonBody
{
    public function decode(HttpResponse $response): Decoded
    {
        try {
            $data = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new UnreadableResponse('Invalid JSON: ' . $e->getMessage(), 0, $e);
        }
        if (!is_array($data)) {
            throw new UnreadableResponse('JSON response is neither an object nor an array');
        }

        return Decoded::of($data);
    }

    /** @return list<Decoded> features of a FeatureCollection */
    public function features(Decoded $data): array
    {
        $features = $data->get('features');
        if (!$features->isList()) {
            throw new UnreadableResponse('Response is not a FeatureCollection');
        }

        return array_values(array_filter($features->list(), static fn(Decoded $f): bool => $f->isArray()));
    }
}
