<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Audit;

use Closure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Ordain\Delegation\Contracts\DelegationAuditInterface;

/**
 * Factory for creating audit driver instances based on configuration.
 *
 * Supports built-in drivers (database, log, null) and custom drivers via:
 * - Container resolution (class name as driver)
 * - Custom driver registration via extend()
 */
final class AuditDriverFactory
{
    /**
     * Custom driver creators registered via extend().
     *
     * @var array<string, Closure(Application): DelegationAuditInterface>
     */
    private array $customDrivers = [];

    public function __construct(
        private readonly Application $app,
    ) {}

    /**
     * Register a custom audit driver.
     *
     * @param  Closure(Application): DelegationAuditInterface  $creator
     */
    public function extend(string $driver, Closure $creator): void
    {
        $this->customDrivers[$driver] = $creator;
    }

    /**
     * @throws BindingResolutionException
     */
    public function create(): DelegationAuditInterface
    {
        if (! config('permission-delegation.audit.enabled', true)) {
            return new NullDelegationAudit;
        }

        /** @var string $driver */
        $driver = config('permission-delegation.audit.driver', 'database');

        // Check for custom registered drivers first
        if (isset($this->customDrivers[$driver])) {
            return ($this->customDrivers[$driver])($this->app);
        }

        // Built-in drivers
        return match ($driver) {
            'database' => $this->app->make(DatabaseDelegationAudit::class),
            'log' => $this->app->make(LogDelegationAudit::class),
            'null' => new NullDelegationAudit,
            default => $this->app->make($driver),
        };
    }

    /**
     * Check if a custom driver is registered.
     */
    public function hasDriver(string $driver): bool
    {
        return isset($this->customDrivers[$driver]);
    }

    /**
     * Clear all custom drivers (primarily useful for testing).
     */
    public function clearCustomDrivers(): void
    {
        $this->customDrivers = [];
    }
}
