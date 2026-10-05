<?php

declare(strict_types=1);

namespace CommonSight\Sdk\News;

use CommonSight\Model\Item\CatalogTerm;

/**
 * Assigns a message to a topic by keyword; the order of the checks is the precedence (Q-NE-04). Keywords match inside
 * words, because German compounds carry them (Drohnenangriff, Bürgerkrieg); only short names that hide in other words
 * are bound to a word start (Senator, Tirana). Some stems carry ordinary words too and are excluded where they do: the
 * football club Sturm Graz and the Ansturm are no storm, kriegen (to get) is no war, a Kosten- or Preisexplosion no
 * blast, a Tarif- or Handelskonflikt no armed conflict.
 */
final class NewsTopicClassifier
{
    private const PATTERNS = [
        'infrastructure' => '/sabotage|cyberangriff|hackerangriff|stromausfall|blackout|netzausfall|versorgungsausfall|(?<!kosten|preis)explosion/iu',
        'weather' => '/unwetter|starkregen|hochwasser|überschwemm|überflut|(?<!an)sturm(?!\\s*graz)|hurrikan|orkan|taifun|tornado|lawine|erdbeben|waldbrand|dürre|trockenperiode|hitze/iu',
        'conflict' => '/krieg(?!en\\b|t\\b|st\\b)|ukrain|russland|russisch|rakete|drohnen|angriff|israel|\\biran|gaza|\\bnato\\b|terror|(?<!tarif|handels)konflikt|waffen/iu',
    ];

    public function classify(string $text): ?CatalogTerm
    {
        foreach (self::PATTERNS as $category => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return new CatalogTerm($category);
            }
        }

        return null;
    }
}
