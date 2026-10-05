<?php

declare(strict_types=1);

namespace CommonSight\Domain\Source;

use CommonSight\Sdk\Plugin\SourceDescription;
use CommonSight\Sdk\Plugin\SourcePlugin;

/**
 * A source known to the core: its description, its rank within its layer (from the description, overridable in the
 * configuration, D6), the secrets it declared but the configuration lacks (then it is not run), and its plugin, which is
 * only created when the source is fetched.
 */
final class RegisteredSource
{
    public readonly int $order;
    private ?SourcePlugin $plugin = null;

    /**
     * @param \Closure(): SourcePlugin $create
     * @param list<string> $missingSecrets
     */
    public function __construct(
        public readonly SourceDescription $description,
        private readonly \Closure $create,
        ?int $order = null,
        public readonly array $missingSecrets = [],
    ) {
        $this->order = $order ?? $description->order;
    }

    public function plugin(): SourcePlugin
    {
        return $this->plugin ??= ($this->create)();
    }
}
