<?php

declare(strict_types=1);

namespace Ordain\Delegation\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Interface for users that can delegate permissions.
 *
 * Implement this interface on your User model to enable delegation features.
 */
interface DelegatableUserInterface
{
    /**
     * Get the user's unique identifier.
     */
    public function getDelegatableIdentifier(): int|string;

    /**
     * Check if user can manage other users.
     */
    public function canManageUsers(): bool;

    /**
     * Get maximum number of users this user can create.
     * Returns null for unlimited.
     */
    public function getMaxManageableUsers(): ?int;

    /**
     * Get the user who created this user (relationship).
     */
    public function creator(): BelongsTo;

    /**
     * Get the creator user instance.
     *
     * This method provides a type-safe way to access the creator
     * without relying on dynamic property access.
     */
    public function getCreator(): ?self;

    /**
     * Get users created by this user.
     */
    public function createdUsers(): HasMany;

    /**
     * Get roles this user can assign to others.
     */
    public function assignableRoles(): BelongsToMany;

    /**
     * Get permissions this user can grant to others.
     */
    public function assignablePermissions(): BelongsToMany;

    /**
     * Get roles assigned to this user.
     */
    public function roles(): BelongsToMany;

    /**
     * Get permissions assigned to this user.
     */
    public function permissions(): BelongsToMany;

    /**
     * Update the model in the database.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     * @return bool
     */
    public function update(array $attributes = [], array $options = []);
}
