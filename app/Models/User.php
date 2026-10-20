<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Admin9\OidcServer\Contracts\OidcUserInterface;
use Admin9\OidcServer\Concerns\HasOidcClaims;

class User extends Authenticatable implements OidcUserInterface
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasOidcClaims, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'koha_patron_id',
        'department_id',
        'koha_cardnumber',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(TaskDelegation::class, 'assigned_to');
    }

    public function delegatedTasks(): HasMany
    {
        return $this->hasMany(TaskDelegation::class, 'assigned_by');
    }

    public function hasRole(string $role): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(fn ($r) => strcasecmp($r->name, $role) === 0);
        }

        return $this->roles()->whereRaw('LOWER(name) = ?', [strtolower($role)])->exists();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permission) {
                $query->whereRaw('LOWER(permissions.name) = ?', [strtolower($permission)]);
            })
            ->exists();
    }

    /** @return mixed */
    protected function resolveOidcClaim(string $claim): mixed
    {
        return match ($claim) {
            'preferred_username' => $this->koha_cardnumber,
            'email' => $this->email,
            default => parent::resolveOidcClaim($claim),
        };
    }
}
