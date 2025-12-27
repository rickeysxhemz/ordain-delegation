<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Audit;

use Illuminate\Http\Request;

/**
 * Value object containing audit context information.
 *
 * Encapsulates request-related data for audit logging,
 * providing a clean abstraction that works in both HTTP and CLI contexts.
 */
final readonly class AuditContext
{
    private const CLI_IDENTIFIER = 'cli';

    public function __construct(
        public string $ipAddress = self::CLI_IDENTIFIER,
        public string $userAgent = self::CLI_IDENTIFIER,
    ) {}

    /**
     * Create context from an HTTP request.
     */
    public static function fromRequest(?Request $request): self
    {
        if ($request === null) {
            return new self;
        }

        return new self(
            ipAddress: $request->ip() ?? self::CLI_IDENTIFIER,
            userAgent: self::sanitizeUserAgent($request->userAgent()),
        );
    }

    /**
     * Create context for CLI operations.
     */
    public static function forCli(): self
    {
        return new self;
    }

    /**
     * Create context with custom values.
     */
    public static function custom(string $ipAddress, string $userAgent): self
    {
        return new self(
            ipAddress: $ipAddress,
            userAgent: self::sanitizeUserAgent($userAgent),
        );
    }

    /**
     * Sanitize user agent to prevent log injection.
     *
     * Removes or escapes potentially dangerous characters.
     */
    private static function sanitizeUserAgent(?string $userAgent): string
    {
        if ($userAgent === null || $userAgent === '') {
            return self::CLI_IDENTIFIER;
        }

        // Remove control characters and limit length to prevent log injection
        $sanitized = preg_replace('/[\x00-\x1F\x7F]/', '', $userAgent);

        return mb_substr($sanitized ?? self::CLI_IDENTIFIER, 0, 500);
    }
}
