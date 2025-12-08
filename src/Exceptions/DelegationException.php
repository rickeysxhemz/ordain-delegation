<?php

declare(strict_types=1);

namespace Ordain\Delegation\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception for delegation errors.
 */
class DelegationException extends Exception
{
    /**
     * @param  array<string, string|int|bool|null>  $context
     */
    protected function __construct(
        string $message,
        protected readonly array $context = [],
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create a generic delegation exception.
     *
     * @param  array<string, string|int|bool|null>  $context
     */
    public static function create(
        string $message,
        array $context = [],
    ): self {
        return new self($message, $context);
    }

    /**
     * Get exception context.
     *
     * @return array<string, string|int|bool|null>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
