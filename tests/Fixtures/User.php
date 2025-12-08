<?php

declare(strict_types=1);

namespace Ordain\Delegation\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Traits\HasDelegation;
use Spatie\Permission\Traits\HasRoles;

/**
 * Test fixture User model.
 */
class User extends Model implements DelegatableUserInterface
{
    use HasDelegation;
    use HasRoles;

    protected $guarded = [];

    protected $table = 'users';

    /**
     * @return BelongsToMany<\Spatie\Permission\Models\Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            \Spatie\Permission\Models\Role::class,
            'model_has_roles',
            'model_id',
            'role_id',
        );
    }

    /**
     * @return BelongsToMany<\Spatie\Permission\Models\Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            \Spatie\Permission\Models\Permission::class,
            'model_has_permissions',
            'model_id',
            'permission_id',
        );
    }
}
