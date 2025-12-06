<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Ordain\Delegation\Adapters\SpatieRoleAdapter;
use Ordain\Delegation\Contracts\RoleInterface;
use Spatie\Permission\Contracts\Role as SpatieRoleContract;

describe('SpatieRoleAdapter', function (): void {
    beforeEach(function (): void {
        $this->mockRole = Mockery::mock(SpatieRoleContract::class);
        $this->mockRole->shouldReceive('getKey')->andReturn(1);
        $this->mockRole->name = 'admin';
        $this->mockRole->guard_name = 'web';
    });

    it('implements RoleInterface', function (): void {
        $adapter = new SpatieRoleAdapter($this->mockRole);

        expect($adapter)->toBeInstanceOf(RoleInterface::class);
    });

    it('returns role identifier', function (): void {
        $adapter = new SpatieRoleAdapter($this->mockRole);

        expect($adapter->getRoleIdentifier())->toBe(1);
    });

    it('returns role name', function (): void {
        $adapter = new SpatieRoleAdapter($this->mockRole);

        expect($adapter->getRoleName())->toBe('admin');
    });

    it('returns role guard', function (): void {
        $adapter = new SpatieRoleAdapter($this->mockRole);

        expect($adapter->getRoleGuard())->toBe('web');
    });

    it('returns underlying model', function (): void {
        $adapter = new SpatieRoleAdapter($this->mockRole);

        expect($adapter->getModel())->toBe($this->mockRole);
    });

    it('creates from model via factory', function (): void {
        $adapter = SpatieRoleAdapter::fromModel($this->mockRole);

        expect($adapter)->toBeInstanceOf(SpatieRoleAdapter::class)
            ->and($adapter->getRoleIdentifier())->toBe(1);
    });

    it('creates collection of adapters', function (): void {
        $mockRole2 = Mockery::mock(SpatieRoleContract::class);
        $mockRole2->shouldReceive('getKey')->andReturn(2);
        $mockRole2->name = 'editor';
        $mockRole2->guard_name = 'web';

        $roles = new Collection([$this->mockRole, $mockRole2]);
        $adapters = SpatieRoleAdapter::collection($roles);

        expect($adapters)->toHaveCount(2)
            ->and($adapters->first())->toBeInstanceOf(SpatieRoleAdapter::class)
            ->and($adapters->first()->getRoleName())->toBe('admin')
            ->and($adapters->last()->getRoleName())->toBe('editor');
    });

    it('checks equality correctly', function (): void {
        $adapter1 = new SpatieRoleAdapter($this->mockRole);

        $sameRole = Mockery::mock(SpatieRoleContract::class);
        $sameRole->shouldReceive('getKey')->andReturn(1);
        $sameRole->guard_name = 'web';
        $adapter2 = new SpatieRoleAdapter($sameRole);

        $differentRole = Mockery::mock(SpatieRoleContract::class);
        $differentRole->shouldReceive('getKey')->andReturn(2);
        $differentRole->guard_name = 'web';
        $adapter3 = new SpatieRoleAdapter($differentRole);

        expect($adapter1->equals($adapter2))->toBeTrue()
            ->and($adapter1->equals($adapter3))->toBeFalse();
    });
});
