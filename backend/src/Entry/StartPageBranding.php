<?php

declare(strict_types=1);

namespace CommonSight\Entry;

use CommonSight\Model\Decoded;

/**
 * Title, name, logo and favicon of the installation from `<WEBROOT>/custom/branding.json` (ACCESS-AND-BRANDING B-D3),
 * which updates never overwrite, so that they may be in any format and keep a dark variant: `title` (of the browser
 * tab), `name` (shown in the header), `logo`, `logoDark`, `logoLink`, `logoAlt`, `favicon`. Paths relative to the
 * webroot or http(s) addresses. Without the file the start page keeps its defaults (CommonSight as title and name,
 * custom/logo.svg, custom/favicon.svg).
 */
final readonly class StartPageBranding
{
    private const KEYS = ['title', 'name', 'logo', 'logoDark', 'logoLink', 'logoAlt', 'favicon'];
    /** Plain texts, not addresses. */
    private const TEXTS = ['title', 'name', 'logoAlt'];
    /** Attribute of the element per key. */
    private const ATTRIBUTES = ['name' => 'site-name', 'logo' => 'logo', 'logoDark' => 'logo-dark', 'logoLink' => 'logo-link', 'logoAlt' => 'logo-alt'];

    /** @param array<string, string> $values by key */
    private function __construct(private array $values) {}

    public static function none(): self
    {
        return new self([]);
    }

    /** @throws \InvalidArgumentException for a file that is no object of known keys with texts and safe addresses */
    public static function fromFile(string $file): self
    {
        if (!is_file($file)) {
            return self::none();
        }
        try {
            $data = Decoded::of(json_decode((string) file_get_contents($file), true, 4, JSON_THROW_ON_ERROR));
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException('custom/branding.json is no valid JSON: ' . $e->getMessage(), 0, $e);
        }
        $unknown = array_diff(array_keys($data->entries()), self::KEYS);
        if (!$data->isArray() || $unknown !== []) {
            throw new \InvalidArgumentException('custom/branding.json: an object with ' . implode(', ', self::KEYS));
        }
        $values = [];
        foreach (self::KEYS as $key) {
            $value = $data->get($key)->string();
            if ($value === null || trim($value) === '') {
                continue;
            }
            if (!in_array($key, self::TEXTS, true) && !self::isAddress($value)) {
                throw new \InvalidArgumentException('custom/branding.json: ' . $key . ' must be a path or an http(s) address');
            }
            $values[$key] = trim($value);
        }

        return new self($values);
    }

    /** A path relative to the webroot or an http(s) address; never javascript: or data: in a link or an image. */
    private static function isAddress(string $value): bool
    {
        $scheme = preg_match('#^[a-z][a-z0-9+.-]*:#i', $value) === 1;

        return preg_match('#[\s"<>]#', $value) !== 1 && (!$scheme || preg_match('#^https?://#i', $value) === 1);
    }

    /** The start page with the title of the tab, the name and logo attributes on the element and the favicon. */
    public function apply(string $html): string
    {
        $attributes = '';
        foreach (self::ATTRIBUTES as $key => $name) {
            if (isset($this->values[$key])) {
                $attributes .= sprintf(' %s="%s"', $name, self::escape($this->values[$key]));
            }
        }
        // Right after the element's name: of two equal attributes the first one counts, so these win over the defaults.
        $html = $attributes === '' ? $html : (string) preg_replace('/<commonsight-map\b/', '<commonsight-map' . $attributes, $html, 1);
        $html = $this->replace($html, 'title', '/<title>[^<]*<\/title>/', '<title>%s</title>');

        return $this->replace($html, 'favicon', '/<link rel="icon"[^>]*>/', '<link rel="icon" href="%s" />');
    }

    /** The first match of the pattern replaced by the template with the value of the key, if the installation sets it. */
    private function replace(string $html, string $key, string $pattern, string $template): string
    {
        if (!isset($this->values[$key])) {
            return $html;
        }
        // A closure, so that "$1" or "\1" in the value stays text.
        $replacement = sprintf($template, self::escape($this->values[$key]));

        return (string) preg_replace_callback($pattern, static fn(): string => $replacement, $html, 1);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
