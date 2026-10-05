<?php

declare(strict_types=1);

namespace CommonSight\Entry;

use CommonSight\Application\StartPageGate;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Auth\AuthRequest;
use CommonSight\Sdk\Auth\Community;

/**
 * Delivers the start page through gate.php (ACCESS-AND-BRANDING A-D3): unchanged for an admitted visitor; for one who
 * is not, the element is marked access="denied" with the name and links of the community, and shows the members
 * card instead of the map. The answer depends on the cookies: never cached as shared.
 */
final class StartPageEndpoint
{
    private const ELEMENT = '<commonsight-map';

    public function __construct(
        private readonly StartPageGate $gate,
        private readonly string $indexFile,
        private readonly StartPageBranding $branding,
    ) {}

    /** The start page, closed: for a configuration that cannot be read or an unexpected error. */
    public static function closed(string $indexFile, StartPageBranding $branding): string
    {
        return $branding->apply(self::page($indexFile, new Community('')));
    }

    /**
     * @param array<mixed> $cookies
     * @return string the HTML of the page
     */
    public function respond(array $cookies, string $pageUrl): string
    {
        $request = new AuthRequest(
            array_filter($cookies, static fn(mixed $v, mixed $k): bool => is_string($k) && is_string($v), ARRAY_FILTER_USE_BOTH),
            $pageUrl,
            UtcInstant::fromTimestamp(time()),
        );
        $refused = $this->gate->refuse($request);

        return $this->branding->apply(self::page($this->indexFile, $refused));
    }

    /** @param Community|null $refused null: the page as it is */
    private static function page(string $indexFile, ?Community $refused): string
    {
        $html = is_file($indexFile) ? (string) file_get_contents($indexFile) : '';
        if ($refused === null) {
            return $html;
        }
        $attributes = ' access="denied"' . self::attribute('community', $refused->name) . self::attribute('login-url', $refused->loginUrl) . self::attribute('register-url', $refused->registerUrl);
        $position = strpos($html, self::ELEMENT);

        // Without the element (a broken start page) nothing of the map is delivered.
        return $position === false ? '' : substr_replace($html, self::ELEMENT . $attributes, $position, strlen(self::ELEMENT));
    }

    private static function attribute(string $name, ?string $value): string
    {
        return $value === null || $value === '' ? '' : sprintf(' %s="%s"', $name, htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
