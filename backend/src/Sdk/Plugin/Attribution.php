<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Plugin;

/**
 * Attribution of a source as its terms of use require it: the text is shown verbatim and never translated (e.g. the
 * Polish sentence required by IMGW), with license and link.
 */
final readonly class Attribution
{
    public function __construct(public string $text, public string $url, public ?string $license = null)
    {
        if (trim($text) === '') {
            throw new \InvalidArgumentException('Attribution without text');
        }
        if (preg_match('#^https?://#i', $url) !== 1) {
            throw new \InvalidArgumentException('Attribution link must be http(s): ' . $url);
        }
    }
}
