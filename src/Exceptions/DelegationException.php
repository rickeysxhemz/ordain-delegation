<?php

declare(strict_types=1);

namespace Ordain\Delegation\Exceptions;

use Exception;

/**
 * Base exception for delegation errors.
 */
class DelegationException extends Exception
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        protected array $context = [],
        int $code = 0,
        ?Exception $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get exception context.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
