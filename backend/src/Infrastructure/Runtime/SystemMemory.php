<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Runtime;

use CommonSight\Port\MemoryUsage;

/** Memory of this PHP process: allocated memory and the limit from memory_limit. */
final class SystemMemory implements MemoryUsage
{
    public function usedBytes(): int
    {
        return memory_get_usage(true);
    }

    public function limitBytes(): ?int
    {
        return self::bytes((string) ini_get('memory_limit'));
    }

    /** "256M", "1G", "131072", "-1" (no limit) -> bytes or null */
    public static function bytes(string $setting): ?int
    {
        $setting = trim($setting);
        if ($setting === '' || $setting === '-1') {
            return null;
        }
        $number = (int) $setting;
        $factor = match (strtoupper(substr($setting, -1))) {
            'G' => 1024 ** 3,
            'M' => 1024 ** 2,
            'K' => 1024,
            default => 1,
        };

        return $number > 0 ? $number * $factor : null;
    }
}
