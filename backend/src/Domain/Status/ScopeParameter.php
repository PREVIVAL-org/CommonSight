<?php

declare(strict_types=1);

namespace CommonSight\Domain\Status;

use CommonSight\Model\Value\Scope;

/** Checks the scope parameter of the status endpoint against the allowlist of countries (A-03). */
final class ScopeParameter
{
    public function parse(mixed $value): ?Scope
    {
        if (!is_string($value)) {
            return null;
        }
        $scope = Scope::tryFrom($value);

        return $scope !== null && $scope->isCountry() ? $scope : null;
    }
}
