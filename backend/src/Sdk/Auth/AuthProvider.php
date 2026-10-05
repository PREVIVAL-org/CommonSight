<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Auth;

/**
 * Decides whether a request may see the start page (ACCESS-AND-BRANDING A-D5): yes or no, nothing about the visitor is
 * kept or logged. A failure (e.g. the database of the community cannot be reached) is thrown; the core then closes the
 * page and logs it.
 */
interface AuthProvider
{
    /** @throws \RuntimeException if the decision cannot be made */
    public function admits(AuthRequest $request): bool;

    /** Name and links of the community for a visitor who is not admitted. */
    public function community(AuthRequest $request): Community;
}
