<?php

declare(strict_types=1);

namespace Ordain\Delegation\Contracts;

/**
 * Interface for role entities.
 *
 * This allows the package to work with any role implementation
 * (Spatie, custom, etc.)
 */
interface RoleInterface
{
    /**
     * Get the role's unique identifier.
     */
    public function getRoleIdentifier(): int|string;

    /**
     * Get the role's name.
     */
    public function getRoleName(): string;

    /**
     * Get the role's guard name.
     */
    public function getRoleGuard(): string;
}
