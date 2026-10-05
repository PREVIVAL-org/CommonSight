<?php

declare(strict_types=1);

namespace CommonSight\Model;

/** Translatable text as a key with parameters (E12, V10). */
final readonly class Msg implements \JsonSerializable
{
    /** @param array<string, string|int|float> $params */
    public function __construct(public string $key, public array $params = [])
    {
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9]*(\.[a-zA-Z0-9][a-zA-Z0-9-]*)+$/D', $key) !== 1) {
            throw new \InvalidArgumentException('Invalid text key: ' . $key);
        }
    }

    /** @return array{key: string, params?: array<string, string|int|float>} */
    public function jsonSerialize(): array
    {
        return $this->params === [] ? ['key' => $this->key] : ['key' => $this->key, 'params' => $this->params];
    }
}
