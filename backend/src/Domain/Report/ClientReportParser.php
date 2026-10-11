<?php

declare(strict_types=1);

namespace CommonSight\Domain\Report;

use CommonSight\Model\Decoded;

/**
 * Reads the error report of a browser (Architecture 6.6): only the known fields, texts cut to their length, everything
 * else dropped. The report comes from the visitor's browser and is never trusted.
 */
final class ClientReportParser
{
    public const KINDS = ['map-start', 'map-error', 'context-lost', 'script-error'];
    private const WEBGL = ['webgl2', 'webgl', 'none'];

    /**
     * @return array<string, scalar|null|array<string, scalar|null>> the fields for the log
     * @throws \InvalidArgumentException for a body that is not a report
     */
    public function parse(string $body): array
    {
        try {
            $report = Decoded::of(json_decode($body, true, 4, JSON_THROW_ON_ERROR));
        } catch (\JsonException) {
            throw new \InvalidArgumentException('not JSON');
        }
        $kind = $report->get('kind')->string();
        $message = self::text($report->get('message'), 500);
        if (!in_array($kind, self::KINDS, true) || $message === null) {
            throw new \InvalidArgumentException('kind or message missing');
        }

        return [
            'kind' => $kind,
            'message' => $message,
            'source' => self::text($report->get('source'), 100),
            'stack' => self::text($report->get('stack'), 2000, true),
            'release' => self::text($report->get('release'), 40),
            'env' => self::environment($report->get('env')),
        ];
    }

    /** @return array<string, scalar|null> */
    private static function environment(Decoded $env): array
    {
        $webgl = $env->get('webgl')->string();
        $ratio = $env->get('pixelRatio')->float();

        return [
            'userAgent' => self::text($env->get('userAgent'), 300),
            'language' => self::text($env->get('language'), 35),
            'webgl' => in_array($webgl, self::WEBGL, true) ? $webgl : null,
            'renderer' => self::text($env->get('renderer'), 200),
            'softwareRendering' => $env->get('softwareRendering')->bool(),
            'workers' => $env->get('workers')->bool(),
            'canvasReadable' => $env->get('canvasReadable')->bool(),
            'pixelRatio' => $ratio === null ? null : round(min(max($ratio, 0.0), 16.0), 2),
            'viewport' => preg_match('/^\d{1,5}x\d{1,5}$/', $env->get('viewport')->string() ?? '') === 1 ? $env->get('viewport')->string() : null,
        ];
    }

    /** A text without control characters (a stack keeps its line breaks), cut to `$max` characters; empty is null. */
    private static function text(Decoded $value, int $max, bool $multiline = false): ?string
    {
        $text = $value->string();
        if ($text === null) {
            return null;
        }
        $clean = trim((string) preg_replace($multiline ? '/[\x00-\x09\x0b-\x1f\x7f]/u' : '/[\x00-\x1f\x7f]/u', ' ', $text));
        $cut = mb_substr($clean, 0, $max, 'UTF-8');

        return $cut === '' ? null : $cut;
    }
}
