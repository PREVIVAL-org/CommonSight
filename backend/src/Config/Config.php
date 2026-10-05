<?php

declare(strict_types=1);

namespace CommonSight\Config;

use CommonSight\Model\Decoded;

/**
 * Reads and validates the configuration (config.php) in exactly one place; each component only gets its typed slice (Architecture 1.3.3).
 */
final readonly class Config
{
    /** What config.php may set per source (sources.<id>). */
    private const SOURCE_KEYS = ['enabled', 'reason', 'lane', 'intervalSec', 'order', 'secrets'];
    /** The sections of config.php; the former ones (assessment, budgets, memberGate) are refused with a hint of their own. */
    private const TOP_KEYS = ['paths', 'http', 'lanes', 'staleFactor', 'triggerMinIntervalSec', 'vicinityKm', 'layers', 'sources', 'drift', 'auth', 'assessment', 'budgets', 'memberGate'];
    private const HTTP_KEYS = ['connectTimeoutSec', 'requestTimeoutSec', 'maxBytes', 'maxBytesBySource', 'requestTimeoutBySource', 'perHostConcurrency', 'totalConcurrency', 'caFile', 'contact'];
    private const LANE_KEYS = ['budgetSec', 'fallbackSec', 'everySec'];

    /** @var array<string, bool> enabled per source id, for the source selection */
    public array $sourceSwitches;

    /** @param array<string, SourceSettings> $sources settings per source id */
    private function __construct(
        public Paths $paths,
        public HttpLimits $http,
        public Lanes $lanes,
        public float $staleFactor,
        public int $triggerMinIntervalSec,
        /** @var array<string, array<string, mixed>> raw settings per layer id (layers.<id>.settings), validated by the layer loader */
        public array $layerSettings,
        public array $sources,
        public float $driftMaxRejectedShare,
        /** null: the start page is open to everyone */
        public ?AuthSettings $auth = null,
        /** radius ("Umkreis") around the selected country or region within which items count as its vicinity (ADR 0038) */
        public float $vicinityKm = 200.0,
    ) {
        $this->sourceSwitches = array_map(static fn(SourceSettings $s): bool => $s->enabled, $sources);
    }

    /** @param array<mixed> $raw */
    public static function fromArray(array $raw): self
    {
        $data = Decoded::of($raw);
        // A misspelled setting would silently fall back to its default.
        self::refuseUnknown($data, self::TOP_KEYS, 'config.php');
        self::refuseUnknown($data->get('http'), self::HTTP_KEYS, 'http');
        self::refuseUnknown($data->get('drift'), ['maxRejectedShare'], 'drift');
        try {
            return new self(
                self::paths($data->get('paths')),
                self::httpLimits($data->get('http')),
                self::lanes($data->get('lanes'), $data),
                self::staleFactor($data->get('staleFactor')),
                self::positiveInt($data->get('triggerMinIntervalSec'), 60, 'triggerMinIntervalSec'),
                self::layerSettings($data),
                self::sources($data->get('sources')),
                self::positiveFloat($data->get('drift', 'maxRejectedShare'), 0.10, 'drift.maxRejectedShare'),
                self::auth($data->get('auth')),
                self::positiveFloat($data->get('vicinityKm'), 200.0, 'vicinityKm'),
            );
        } catch (\InvalidArgumentException $e) {
            throw new ConfigError($e->getMessage(), 0, $e);
        }
    }


    private static function paths(Decoded $paths): Paths
    {
        $path = static fn(string $name): string => $paths->get($name)->string() ?? throw new ConfigError('paths.' . $name . ' missing');

        return new Paths($path('data'), $path('state'), $path('cache'), $path('locks'), $path('logs'));
    }

    private static function httpLimits(Decoded $http): HttpLimits
    {
        $defaults = new HttpLimits();
        $bySource = [];
        foreach ($http->get('maxBytesBySource')->entries() as $source => $bytes) {
            $bySource[$source] = self::positiveInt($bytes, 0, 'http.maxBytesBySource.' . $source);
        }
        $timeoutBySource = [];
        foreach ($http->get('requestTimeoutBySource')->entries() as $source => $seconds) {
            $timeoutBySource[$source] = self::positiveInt($seconds, 0, 'http.requestTimeoutBySource.' . $source);
        }

        return new HttpLimits(
            self::positiveInt($http->get('connectTimeoutSec'), $defaults->connectTimeoutSec, 'http.connectTimeoutSec'),
            self::positiveInt($http->get('requestTimeoutSec'), $defaults->requestTimeoutSec, 'http.requestTimeoutSec'),
            self::positiveInt($http->get('maxBytes'), $defaults->maxBytes, 'http.maxBytes'),
            $bySource,
            self::positiveInt($http->get('perHostConcurrency'), $defaults->perHostConcurrency, 'http.perHostConcurrency'),
            self::positiveInt($http->get('totalConcurrency'), $defaults->totalConcurrency, 'http.totalConcurrency'),
            ($http->get('caFile')->string() ?? '') !== '' ? $http->get('caFile')->string() : null,
            self::userAgent($http->get('contact'), $defaults->userAgent),
            $timeoutBySource,
        );
    }

    /** The lanes of the defaults, changed or extended by the section lanes. */
    private static function lanes(Decoded $section, Decoded $config): Lanes
    {
        self::refuseFormerSettings($config);
        $lanes = Lanes::DEFAULTS;
        foreach ($section->entries() as $name => $settings) {
            if (preg_match('/^[a-z][a-z0-9-]{0,30}$/', $name) !== 1) {
                throw new ConfigError('Invalid lane name: ' . $name);
            }
            if (!$settings->isArray()) {
                throw new ConfigError('lanes.' . $name . ' must be an array, e.g. [\'budgetSec\' => 240]');
            }
            self::refuseUnknown($settings, self::LANE_KEYS, 'lanes.' . $name);
            $default = $lanes[$name] ?? ['budgetSec' => 0, 'fallbackSec' => null, 'everySec' => null];
            $fallback = $settings->get('fallbackSec');
            $every = $settings->get('everySec');
            $lanes[$name] = [
                'budgetSec' => self::positiveInt($settings->get('budgetSec'), $default['budgetSec'], 'lanes.' . $name . '.budgetSec'),
                'fallbackSec' => $fallback->isNull() ? (is_array($settings->raw()) && array_key_exists('fallbackSec', $settings->raw()) ? null : $default['fallbackSec']) : self::positiveInt($fallback, 0, 'lanes.' . $name . '.fallbackSec'),
                'everySec' => $every->isNull() ? $default['everySec'] : self::positiveInt($every, 0, 'lanes.' . $name . '.everySec'),
            ];
        }

        return new Lanes($lanes);
    }

    /**
     * The raw settings of the layers (layers.<id>.settings, L-D5), validated by the layers themselves when they are
     * created.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function layerSettings(Decoded $config): array
    {
        $settings = [];
        foreach ($config->get('layers')->entries() as $layer => $section) {
            $unknown = array_diff(array_keys($section->entries()), ['settings']);
            if ($unknown !== []) {
                throw new ConfigError(sprintf('layers.%s: unknown %s, a layer has only settings', $layer, implode(', ', $unknown)));
            }
            $settings[$layer] = [];
            foreach ($section->get('settings')->entries() as $name => $value) {
                $settings[$layer][$name] = $value->raw();
            }
        }
        return $settings;
    }

    /** Settings of former versions (scheduling per layer, radiation thresholds) are refused with a hint to their replacement. */
    private static function refuseFormerSettings(Decoded $config): void
    {
        if (!$config->get('assessment')->isNull()) {
            throw new ConfigError('assessment is replaced by the settings of the layers, e.g. [\'layers\' => [\'radiation\' => [\'settings\' => [\'warningUSvH\' => 0.3, \'highUSvH\' => 1.0]]]]');
        }
        if (!$config->get('memberGate')->isNull()) {
            throw new ConfigError('memberGate is replaced by auth, e.g. [\'provider\' => \'woltlab\', \'settings\' => [\'cookie\' => ..., \'database\' => [...]]] (doc/INSTALLATION.md)');
        }
        if (!$config->get('budgets')->isNull()) {
            throw new ConfigError('budgets is replaced by lanes, e.g. [\'heavy\' => [\'budgetSec\' => 240, \'fallbackSec\' => 120]]');
        }
        foreach ($config->get('layers')->entries() as $layer => $settings) {
            if (!$settings->get('intervalSec')->isNull()) {
                throw new ConfigError('layers.' . $layer . '.intervalSec is replaced by sources.<id>.intervalSec: sources are scheduled one by one');
            }
        }
    }

    /** The authentication provider: `['provider' => '<id>', 'settings' => [...]]`; absent or null for an open page. */
    private static function auth(Decoded $auth): ?AuthSettings
    {
        if ($auth->isNull()) {
            return null;
        }
        self::refuseUnknown($auth, ['provider', 'settings'], 'auth');
        $provider = $auth->get('provider')->string() ?? '';
        if (preg_match('/^[a-z][a-z0-9-]{1,30}$/', $provider) !== 1) {
            throw new ConfigError('auth.provider: the id of a package in plugins/auth/, e.g. \'woltlab\'');
        }
        $settings = $auth->get('settings');
        if (!$settings->isNull() && !$settings->isArray()) {
            throw new ConfigError('auth.settings must be an array');
        }

        return new AuthSettings($provider, $settings->array());
    }

    /** @param list<string> $known */
    private static function refuseUnknown(Decoded $section, array $known, string $name): void
    {
        $unknown = array_diff(array_keys($section->entries()), $known);
        if ($unknown !== []) {
            throw new ConfigError(sprintf('%s: unknown %s (known: %s)', $name, implode(', ', $unknown), implode(', ', $known)));
        }
    }

    /** Below 1 a layer would be stale before its next regular run, and every request would trigger the fallback. */
    private static function staleFactor(Decoded $value): float
    {
        $factor = self::positiveFloat($value, 2.5, 'staleFactor');
        if ($factor < 1.0) {
            throw new ConfigError('staleFactor must be at least 1');
        }

        return $factor;
    }

    /** The contact of the operator in the User-Agent (F-10), so providers know whom to reach. */
    private static function userAgent(Decoded $contact, string $default): string
    {
        if ($contact->isNull()) {
            return $default;
        }
        $text = trim($contact->string() ?? '');
        if ($text === '' || preg_match('/[\x00-\x1f()]/', $text) === 1) {
            throw new ConfigError('http.contact must be a URL or e-mail address, e.g. \'https://example.org/contact\'');
        }

        return 'CommonSight/2.0 (+' . $text . ')';
    }

    /** @return array<string, SourceSettings> */
    private static function sources(Decoded $sources): array
    {
        $settings = [];
        foreach ($sources->entries() as $id => $source) {
            $settings[$id] = self::sourceSettings('sources.' . $id, $source);
        }

        return $settings;
    }

    /** A wrong type is an error, never the default: `'enabled' => 'false'` must not leave a source running. */
    private static function sourceSettings(string $name, Decoded $source): SourceSettings
    {
        if (!$source->isArray()) {
            throw new ConfigError($name . ' must be an array, e.g. [\'enabled\' => false]');
        }
        $unknown = array_diff(array_keys($source->entries()), self::SOURCE_KEYS);
        if ($unknown !== []) {
            throw new ConfigError(sprintf('%s: unknown %s (known: %s)', $name, implode(', ', $unknown), implode(', ', self::SOURCE_KEYS)));
        }
        $optional = static fn(string $key): ?int => $source->get($key)->isNull() ? null : self::positiveInt($source->get($key), 0, $name . '.' . $key);

        return new SourceSettings(
            self::typed($source->get('enabled'), $source->get('enabled')->bool(), $name . '.enabled must be true or false') ?? true,
            self::typed($source->get('lane'), $source->get('lane')->string(), $name . '.lane must be a string'),
            $optional('intervalSec'),
            $optional('order'),
            self::secrets($source->get('secrets'), $name . '.secrets'),
            self::typed($source->get('reason'), $source->get('reason')->string(), $name . '.reason must be a string'),
        );
    }

    /**
     * The read value of a setting; null only when the setting is absent.
     *
     * @template T
     *
     * @param T|null $value
     *
     * @return T|null
     */
    private static function typed(Decoded $setting, mixed $value, string $error): mixed
    {
        if ($value === null && !$setting->isNull()) {
            throw new ConfigError($error);
        }

        return $value;
    }

    /** @return array<string, string> */
    private static function secrets(Decoded $secrets, string $name): array
    {
        $values = [];
        foreach ($secrets->entries() as $key => $value) {
            $values[$key] = $value->string() ?? throw new ConfigError($name . '.' . $key . ' must be a string');
        }

        return $values;
    }

    private static function positiveInt(Decoded $value, int $default, string $name): int
    {
        if ($value->isNull() && $default > 0) {
            return $default;
        }
        $number = $value->int();
        if ($number === null || $number < 1) {
            throw new ConfigError($name . ' must be a positive integer');
        }

        return $number;
    }

    private static function positiveFloat(Decoded $value, float $default, string $name): float
    {
        if ($value->isNull()) {
            return $default;
        }
        $number = $value->float();
        if ($number === null || $number <= 0) {
            throw new ConfigError($name . ' must be a positive number');
        }

        return $number;
    }
}
