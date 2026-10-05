<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Contract;

/**
 * Joins the texts of the plugins (plugins/<group>/<id>/messages/de.json) for the frontend (V10): each plugin may only define
 * keys in its own namespace (source.<id>. for a source, layer.<id>. for a layer), none may override a text of the core.
 * A text is a string or, for a count, the plural forms {"one": …, "other": …} like the texts of the user interface.
 * Works on decoded JSON.
 */
final class MessageCollector
{
    /**
     * @param array<mixed> $core decoded contract/messages/de.json
     * @param array<array<mixed>> $plugins decoded messages per plugin id
     * @param array<string, string> $namespaces namespace per plugin id, e.g. "layer.water."; default "source.<id>."
     * @return array<string, string|array{one: string, other: string}> key -> text, sorted by key
     * @throws \RuntimeException listing every problem
     */
    public function collect(array $core, array $plugins, array $namespaces = []): array
    {
        $messages = [];
        $problems = [];
        foreach ($plugins as $id => $texts) {
            $namespace = $namespaces[$id] ?? 'source.' . $id . '.';
            foreach ($texts as $key => $text) {
                $problem = match (true) {
                    !str_starts_with((string) $key, $namespace) => 'outside the namespace ' . $namespace,
                    isset($core[$key]) => 'already a text of the core',
                    default => self::textProblem($text),
                };
                if ($problem !== null) {
                    $problems[] = sprintf('%s: "%s" %s', $id, $key, $problem);
                    continue;
                }
                /** @var string|array{one: string, other: string} $text checked by textProblem() */
                $messages[(string) $key] = $text;
            }
        }
        if ($problems !== []) {
            throw new \RuntimeException("Invalid plugin texts:\n  " . implode("\n  ", $problems));
        }
        ksort($messages);

        return $messages;
    }

    private static function textProblem(mixed $text): ?string
    {
        if (is_array($text)) {
            $forms = array_keys($text);
            sort($forms);

            return $forms === ['one', 'other'] && self::filled($text['one']) && self::filled($text['other']) ? null : 'plural forms must be exactly "one" and "other", both filled';
        }

        return self::filled($text) ? null : 'empty text';
    }

    private static function filled(mixed $text): bool
    {
        return is_string($text) && trim($text) !== '';
    }
}
