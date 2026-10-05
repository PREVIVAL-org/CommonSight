<?php

declare(strict_types=1);

namespace CommonSight\Plugin\AtAlert;

/**
 * The German part of an AT-Alert text. The warning centres add an English version in their own way: as a paragraph
 * after a blank line, line by line after each German line, as a second long line, or as a part between asterisks
 * ("*Land Salzburg* For translations ..."). Paragraphs, lines and such parts that are
 * English (several English and hardly any German function words) are left out; anything with German words stays, and
 * so does a line without words (a time, a link). A text without any German part is kept whole.
 */
final class GermanText
{
    private const ENGLISH = [
        'the', 'of', 'is', 'are', 'there', 'please', 'do', 'not', 'dial', 'call', 'emergency', 'numbers', 'this', 'an', 'a',
        'and', 'for', 'further', 'information', 'danger', 'warning', 'center', 'centre', 'federal', 'ministry', 'interior',
        'just', 'official', 'message', 'sirens', 'civil', 'protection', 'end', 'provincial', 'mission', 'control', 'test',
        'technical', 'functional', 'state', 'stay', 'indoors', 'keep', 'windows', 'doors', 'closed', 'area', 'avoid', 'pm', 'am',
    ];
    private const GERMAN = [
        'der', 'die', 'das', 'und', 'ist', 'es', 'besteht', 'keine', 'kein', 'bitte', 'sie', 'nicht', 'für', 'von', 'den',
        'dem', 'des', 'ein', 'eine', 'nur', 'gefahr', 'wählen', 'waehlen', 'rufen', 'notrufnummern', 'uhr', 'weitere',
        'informationen', 'ihre', 'dies', 'im', 'in', 'auf', 'mit', 'bei', 'zu', 'land', 'achtung', 'ende', 'gebiet',
    ];

    public function of(string $text): string
    {
        $paragraphs = preg_split('/\R\s*\R/u', trim($text)) ?: [];
        $kept = [];
        foreach ($paragraphs as $paragraph) {
            if ($this->isEnglish($paragraph)) {
                continue;
            }
            $parts = preg_split('/\R|\*/u', $paragraph) ?: [];
            $lines = array_filter(array_map('trim', $parts), fn(string $line): bool => $line !== '' && !$this->isEnglish($line));
            if ($lines !== []) {
                $kept[] = implode("\n", $lines);
            }
        }

        return $kept === [] ? $text : implode("\n\n", $kept);
    }

    private function isEnglish(string $text): bool
    {
        $words = preg_split('/[^\p{L}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $english = count(array_filter($words, static fn(string $w): bool => in_array($w, self::ENGLISH, true)));
        $german = count(array_filter($words, static fn(string $w): bool => in_array($w, self::GERMAN, true)));

        return $english >= 2 && $german === 0 || $english >= 4 && $english > 3 * $german;
    }
}
