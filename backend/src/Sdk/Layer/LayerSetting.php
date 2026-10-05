<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Layer;

/**
 * A setting a layer reads from config.php (layers.<id>.settings, L-D5): a number with default and range. The core
 * validates the value; checks across settings (e.g. high above warning) are the layer's.
 */
final readonly class LayerSetting
{
    public function __construct(
        public string $name,
        public float $default,
        public ?float $min = null,
        public ?float $max = null,
        public string $description = '',
    ) {
        if (preg_match('/^[a-z][A-Za-z0-9]{0,40}$/', $name) !== 1) {
            throw new \InvalidArgumentException('Invalid setting name: ' . $name);
        }
        // The default is used when nobody configures the setting, so it must pass its own range.
        $this->accept($default);
    }

    /** @throws \InvalidArgumentException if the value is not a number in range */
    public function accept(mixed $value): float
    {
        if (!is_int($value) && !is_float($value)) {
            throw new \InvalidArgumentException($this->name . ' must be a number');
        }
        $number = (float) $value;
        if (!is_finite($number)) {
            throw new \InvalidArgumentException($this->name . ' must be a finite number');
        }
        if (($this->min !== null && $number < $this->min) || ($this->max !== null && $number > $this->max)) {
            throw new \InvalidArgumentException(sprintf('%s must be between %s and %s', $this->name, $this->min ?? '-∞', $this->max ?? '∞'));
        }

        return $number;
    }
}
