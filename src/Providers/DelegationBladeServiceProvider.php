<?php

declare(strict_types=1);

namespace Ordain\Delegation\Providers;

use Illuminate\Support\ServiceProvider;
use Ordain\Delegation\View\BladeDirectives;

/**
 * Registers Blade directives for delegation checks.
 */
final class DelegationBladeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        (new BladeDirectives)->register();
    }
}
