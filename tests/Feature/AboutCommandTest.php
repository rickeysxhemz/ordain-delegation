<?php

declare(strict_types=1);

use Composer\InstalledVersions;

describe('about command integration', function (): void {
    it('reports the installed package version rather than a hardcoded one', function (): void {
        $installed = InstalledVersions::getPrettyVersion('ordain/delegation');

        expect($installed)->not->toBeNull();

        $this->artisan('about', ['--only' => 'delegation'])
            ->assertSuccessful()
            ->expectsOutputToContain($installed);
    });

    it('reports the configured delegation settings', function (): void {
        config()->set('permission-delegation.audit.driver', 'log');
        config()->set('permission-delegation.root_admin.role', 'super-admin');

        $this->artisan('about', ['--only' => 'delegation'])
            ->assertSuccessful()
            ->expectsOutputToContain('log')
            ->expectsOutputToContain('super-admin');
    });
});
