<?php

declare(strict_types=1);

namespace Ordain\Delegation\Contracts;

/**
 * Interface for permission entities.
 *
 * This allows the package to work with any permission implementation
 * (Spatie, custom, etc.)
 */
interface PermissionInterface
{
    /**
     * Get the permission's unique identifier.
     */
    public function getPermissionIdentifier(): int|string;

    /**
     * Get the permission's name.
     */
    public function getPermissionName(): string;

    /**
     * Get the permission's guard name.
     */
    public function getPermissionGuard(): string;
}
