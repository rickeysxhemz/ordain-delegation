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
     * @param  array<string, mixed>  $data  Additional data
     * @param  array<string, string>  $errors  Validation errors
     */
    private function __construct(
        public bool $success,
        public ?string $message = null,
        public array $data = [],
        public array $errors = [],
    ) {}

    /**
     * Create a successful result.
     *
     * @param  array<string, mixed>  $data
     */
    public static function success(?string $message = null, array $data = []): self
    {
        return new self(
            success: true,
            message: $message,
            data: $data,
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
            data: [],
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
            data: [],
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
     * Get a specific data value by key.
     */
    public function getData(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
            'errors' => $this->errors,
        ];
    }
}
