<?php

declare(strict_types=1);

namespace CommonSight\Config;

/**
 * The situation endpoint (`api/situation.php`, concept: situation level): the origins of other sites that may read it in
 * the browser, e.g. a forum that shows the situation level in a box. Without any, only pages of this site read it.
 */
final readonly class SituationSettings
{
    /** @var list<string> as the browser sends them in Origin: without the default port */
    public array $allowOrigins;

    /** @param list<string> $allowOrigins e.g. 'https://forum.example.org' */
    public function __construct(array $allowOrigins = [])
    {
        foreach ($allowOrigins as $origin) {
            if (preg_match('#^https?://[a-z0-9.-]+(:\d{1,5})?$#D', $origin) !== 1) {
                throw new ConfigError(sprintf('situation.allowOrigins: \'%s\' is no origin; scheme and host in lower case without path, e.g. \'https://forum.example.org\'', $origin));
            }
        }
        $this->allowOrigins = array_map(static fn(string $origin): string => preg_replace('#^(https://[^:]+):443$|^(http://[^:]+):80$#D', '$1$2', $origin) ?? $origin, $allowOrigins);
    }

    public function allows(string $origin): bool
    {
        return in_array($origin, $this->allowOrigins, true);
    }
}
