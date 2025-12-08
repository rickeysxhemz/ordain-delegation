<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Ordain\Delegation\Adapters\SpatiePermissionAdapter;
use Ordain\Delegation\Contracts\PermissionInterface;
use Spatie\Permission\Contracts\Permission as SpatiePermissionContract;

describe('SpatiePermissionAdapter', function (): void {
    beforeEach(function (): void {
        $this->mockPermission = Mockery::mock(SpatiePermissionContract::class);
        $this->mockPermission->shouldReceive('getKey')->andReturn(1);
        $this->mockPermission->name = 'create-posts';
        $this->mockPermission->guard_name = 'web';
    });

    it('implements PermissionInterface', function (): void {
        $adapter = new SpatiePermissionAdapter($this->mockPermission);

        expect($adapter)->toBeInstanceOf(PermissionInterface::class);
    });

    it('returns permission identifier', function (): void {
        $adapter = new SpatiePermissionAdapter($this->mockPermission);

        expect($adapter->getPermissionIdentifier())->toBe(1);
    });

    it('returns permission name', function (): void {
        $adapter = new SpatiePermissionAdapter($this->mockPermission);

        expect($adapter->getPermissionName())->toBe('create-posts');
    });

    it('returns permission guard', function (): void {
        $adapter = new SpatiePermissionAdapter($this->mockPermission);

        expect($adapter->getPermissionGuard())->toBe('web');
    });

    it('returns underlying model', function (): void {
        $adapter = new SpatiePermissionAdapter($this->mockPermission);

        expect($adapter->getModel())->toBe($this->mockPermission);
    });

    it('creates from model via factory', function (): void {
        $adapter = SpatiePermissionAdapter::fromModel($this->mockPermission);

        expect($adapter)->toBeInstanceOf(SpatiePermissionAdapter::class)
            ->and($adapter->getPermissionIdentifier())->toBe(1);
    });

    it('creates collection of adapters', function (): void {
        $mockPermission2 = Mockery::mock(SpatiePermissionContract::class);
        $mockPermission2->shouldReceive('getKey')->andReturn(2);
        $mockPermission2->name = 'edit-posts';
        $mockPermission2->guard_name = 'web';

        $permissions = new Collection([$this->mockPermission, $mockPermission2]);
        $adapters = SpatiePermissionAdapter::collection($permissions);

        expect($adapters)->toHaveCount(2);

        $first = $adapters->first();
        $last = $adapters->last();

        expect($first)->toBeInstanceOf(SpatiePermissionAdapter::class);
        expect($last)->toBeInstanceOf(SpatiePermissionAdapter::class);

        if ($first instanceof SpatiePermissionAdapter && $last instanceof SpatiePermissionAdapter) {
            expect($first->getPermissionName())->toBe('create-posts')
                ->and($last->getPermissionName())->toBe('edit-posts');
        }
    });

    it('checks equality correctly', function (): void {
        $adapter1 = new SpatiePermissionAdapter($this->mockPermission);

        $samePermission = Mockery::mock(SpatiePermissionContract::class);
        $samePermission->shouldReceive('getKey')->andReturn(1);
        $samePermission->guard_name = 'web';
        $adapter2 = new SpatiePermissionAdapter($samePermission);

        $differentPermission = Mockery::mock(SpatiePermissionContract::class);
        $differentPermission->shouldReceive('getKey')->andReturn(2);
        $differentPermission->guard_name = 'web';
        $adapter3 = new SpatiePermissionAdapter($differentPermission);

        expect($adapter1->equals($adapter2))->toBeTrue()
            ->and($adapter1->equals($adapter3))->toBeFalse();
    });
});
