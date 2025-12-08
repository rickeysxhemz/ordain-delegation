<?php

declare(strict_types=1);

namespace Ordain\Delegation\Domain\ValueObjects;

/**
 * Value object representing the result of a delegation operation.
 *
 * Immutable object that encapsulates operation success/failure.
 */
final readonly class DelegationResult
{
    /**
     * @param  bool  $success  Whether the operation succeeded
     * @param  string|null  $message  Human-readable message
     * @param  int|string|null  $targetUserId  The user who received/lost the role/permission
     * @param  int|string|null  $delegatorUserId  The user who performed the action
     * @param  string|null  $roleName  Role name if role was assigned/revoked
     * @param  string|null  $permissionName  Permission name if permission was granted/revoked
     * @param  array<string, string>  $errors  Validation errors
     */
    private function __construct(
        public bool $success,
        public ?string $message = null,
        public int|string|null $targetUserId = null,
        public int|string|null $delegatorUserId = null,
        public ?string $roleName = null,
        public ?string $permissionName = null,
        public array $errors = [],
    ) {}

    /**
     * Create a successful result.
     */
    public static function success(
        ?string $message = null,
        int|string|null $targetUserId = null,
        int|string|null $delegatorUserId = null,
        ?string $roleName = null,
        ?string $permissionName = null,
    ): self {
        return new self(
            success: true,
            message: $message,
            targetUserId: $targetUserId,
            delegatorUserId: $delegatorUserId,
            roleName: $roleName,
            permissionName: $permissionName,
            errors: [],
        );
    }

    /**
     * Create a successful result for role delegation.
     */
    public static function roleAssigned(
        int|string $targetUserId,
        int|string $delegatorUserId,
        string $roleName,
    ): self {
        return new self(
            success: true,
            message: "Role '$roleName' assigned successfully.",
            targetUserId: $targetUserId,
            delegatorUserId: $delegatorUserId,
            roleName: $roleName,
            errors: [],
        );
    }

    /**
     * Create a successful result for role revocation.
     */
    public static function roleRevoked(
        int|string $targetUserId,
        int|string $delegatorUserId,
        string $roleName,
    ): self {
        return new self(
            success: true,
            message: "Role '$roleName' revoked successfully.",
            targetUserId: $targetUserId,
            delegatorUserId: $delegatorUserId,
            roleName: $roleName,
            errors: [],
        );
    }

    /**
     * Create a successful result for permission delegation.
     */
    public static function permissionGranted(
        int|string $targetUserId,
        int|string $delegatorUserId,
        string $permissionName,
    ): self {
        return new self(
            success: true,
            message: "Permission '$permissionName' granted successfully.",
            targetUserId: $targetUserId,
            delegatorUserId: $delegatorUserId,
            permissionName: $permissionName,
            errors: [],
        );
    }

    /**
     * Create a successful result for permission revocation.
     */
    public static function permissionRevoked(
        int|string $targetUserId,
        int|string $delegatorUserId,
        string $permissionName,
    ): self {
        return new self(
            success: true,
            message: "Permission '$permissionName' revoked successfully.",
            targetUserId: $targetUserId,
            delegatorUserId: $delegatorUserId,
            permissionName: $permissionName,
            errors: [],
        );
    }

    /**
     * Create a failure result.
     *
     * @param  array<string, string>  $errors
     */
    public static function failure(string $message, array $errors = []): self
    {
        return new self(
            success: false,
            message: $message,
            errors: $errors,
        );
    }

    /**
     * Create a failure result from validation errors.
     *
     * @param  array<string, string>  $errors
     */
    public static function validationFailed(array $errors): self
    {
        return new self(
            success: false,
            message: 'Validation failed.',
            errors: $errors,
        );
    }

    /**
     * Check if the operation was successful.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if the operation failed.
     */
    public function isFailure(): bool
    {
        return ! $this->success;
    }

    /**
     * Check if there are validation errors.
     */
    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    /**
     * Get a specific error by key.
     */
    public function getError(string $key): ?string
    {
        return $this->errors[$key] ?? null;
    }

    /**
     * Check equality with another result.
     */
    public function equals(self $other): bool
    {
        return $this->success === $other->success
            && $this->message === $other->message
            && $this->targetUserId === $other->targetUserId
            && $this->delegatorUserId === $other->delegatorUserId
            && $this->roleName === $other->roleName
            && $this->permissionName === $other->permissionName
            && $this->errors === $other->errors;
    }

    /**
     * Convert to array.
     *
     * @return array{
     *     success: bool,
     *     message: string|null,
     *     target_user_id: int|string|null,
     *     delegator_user_id: int|string|null,
     *     role_name: string|null,
     *     permission_name: string|null,
     *     errors: array<string, string>
     * }
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'target_user_id' => $this->targetUserId,
            'delegator_user_id' => $this->delegatorUserId,
            'role_name' => $this->roleName,
            'permission_name' => $this->permissionName,
            'errors' => $this->errors,
        ];
    }
}
