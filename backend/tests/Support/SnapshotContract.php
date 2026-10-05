<?php

declare(strict_types=1);

namespace CommonSight\Tests\Support;

use CommonSight\Domain\Snapshot\ContentHash;
use CommonSight\Model\Snapshot;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Assert;

/**
 * Validates snapshots and status responses against the JSON Schema in contract/schema (Architecture 3.1) and stores
 * validated snapshots as examples in contract/fixtures, which the frontend tests read.
 */
final class SnapshotContract
{
    private const BASE = 'https://commonsight.org/schema/v1/';

    private static ?Validator $validator = null;

    public static function assertValid(Snapshot $snapshot): void
    {
        self::assertJsonValid((new ContentHash())->json($snapshot), 'snapshot.schema.json');
        self::assertMessageKeysExist($snapshot);
    }

    public static function assertJsonValid(string $json, string $schema): void
    {
        $violations = self::violations($json, $schema);
        Assert::assertNull($violations, 'Violation of ' . $schema . ': ' . $violations);
    }

    /** The violations of the schema as text, null if the JSON is valid. */
    public static function violations(string $json, string $schema): ?string
    {
        $result = self::validator()->validate(json_decode($json), self::BASE . $schema);

        return $result->isValid() ? null : (string) json_encode((new ErrorFormatter())->format($result->error(), false), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /** @return array<string, mixed> texts of the core and of the plugins (contract/messages, V10) */
    public static function messages(): array
    {
        return [...Fixtures::contractJson('messages/de.json'), ...Fixtures::contractJson('messages/plugins.de.json')];
    }

    /** Validates the snapshot and stores it as contract/fixtures/<name>.json. */
    public static function record(string $name, Snapshot $snapshot): void
    {
        self::assertValid($snapshot);
        $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        // Atomically: the frontend tests may read the fixtures while the backend tests write them.
        $target = Fixtures::contractDir() . '/fixtures/' . $name . '.json';
        file_put_contents($target . '.tmp', $json . "\n");
        rename($target . '.tmp', $target);
    }

    /** Every Msg key must be present in contract/messages (core or plugins). */
    public static function assertMessageKeysExist(Snapshot $snapshot): void
    {
        Assert::assertSame([], self::missingMessageKeys($snapshot), 'Keys missing in contract/messages');
    }

    /** @return list<string> the Msg keys of the snapshot without a text in contract/messages */
    public static function missingMessageKeys(Snapshot $snapshot): array
    {
        $catalog = self::messages();
        preg_match_all('/"key":"([^"]+)"/', (new ContentHash())->json($snapshot), $matches);

        return array_values(array_filter(array_unique($matches[1]), static fn(string $key): bool => !isset($catalog[$key])));
    }

    private static function validator(): Validator
    {
        if (self::$validator === null) {
            self::$validator = new Validator();
            self::$validator->resolver()?->registerPrefix(self::BASE, Fixtures::contractDir() . '/schema/');
            self::$validator->setMaxErrors(5);
        }

        return self::$validator;
    }
}
