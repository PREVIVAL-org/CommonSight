<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Text;

/** Turns source text into plain text: HTML removed, entities decoded, whitespace normalized (D-14). */
final class TextCleaner
{
    public function clean(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }
        $withBreaks = preg_replace('#<\s*(br|/p|/li|/div)\b[^>]*>#i', ' ', (string) $value) ?? '';
        $decoded = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Entities like &lt;b&gt; only become tags after decoding; only real tags go, a decoded "<50 m" stays text.
        $plain = html_entity_decode(preg_replace('#</?[A-Za-z][A-Za-z0-9]*\b[^<>]*>#', '', $decoded) ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $plain) ?? '');
    }
}
