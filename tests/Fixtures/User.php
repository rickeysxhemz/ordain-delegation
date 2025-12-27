<?php

declare(strict_types=1);

namespace Ordain\Delegation\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Traits\HasDelegation;
use Spatie\Permission\Traits\HasRoles;

/**
 * Test fixture User model.
 */
class User extends Model implements Authenticatable, DelegatableUserInterface
{
    use HasDelegation;
    use HasRoles;

    /**
     * @var string|null
     */
    protected $rememberTokenName = 'remember_token';

    /**
     * Guard name for Spatie permission.
     */
    protected string $guard_name = 'web';

    protected $guarded = [];

    protected $table = 'users';

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return $this->password ?? '';
    }

    public function getRememberToken(): ?string
    {
        return $this->remember_token ?? null;
    }

    public function setRememberToken($value): void
    {
        $this->remember_token = $value;
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
