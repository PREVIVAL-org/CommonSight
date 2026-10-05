<?php

declare(strict_types=1);

namespace CommonSight\Application;

use CommonSight\Port\Logger;
use CommonSight\Sdk\Auth\AuthProvider;
use CommonSight\Sdk\Auth\AuthRequest;
use CommonSight\Sdk\Auth\Community;

/**
 * Decides whether a request sees the map or the members card (ACCESS-AND-BRANDING A-D3): without a provider everybody
 * is admitted; with one it decides. If it cannot decide (e.g. the database of the community is not reachable), the
 * page stays closed and the failure is logged, never anything about the visitor.
 */
final class StartPageGate
{
    public function __construct(private readonly ?AuthProvider $provider, private readonly Logger $log) {}

    /** @return Community|null null: admitted; else the community the visitor is pointed to */
    public function refuse(AuthRequest $request): ?Community
    {
        if ($this->provider === null) {
            return null;
        }
        try {
            if ($this->provider->admits($request)) {
                return null;
            }
        } catch (\RuntimeException $e) {
            $this->log->log('error', 'auth.failed', ['reason' => $e->getMessage()]);
        }

        return $this->community($request);
    }

    private function community(AuthRequest $request): Community
    {
        try {
            return $this->provider?->community($request) ?? new Community('');
        } catch (\RuntimeException | \InvalidArgumentException $e) {
            $this->log->log('error', 'auth.failed', ['reason' => $e->getMessage()]);

            return new Community('');
        }
    }
}
