<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'name',
        'email',
        'login_id',
        'password',
        'must_change_password',
        'role',
        'is_active',
        'active_mobile_session_id',
        'active_mobile_device_id',
        'active_mobile_token_id',
        'active_mobile_login_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'is_active' => 'boolean',
            'active_mobile_login_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function directedProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'director_project_assignments')
            ->withTimestamps();
    }

    public function headedProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_head_assignments')
            ->withTimestamps();
    }

    public function managedCenters(): BelongsToMany
    {
        return $this->belongsToMany(Center::class, 'center_manager_assignments')
            ->withTimestamps();
    }

    public function roleEnum(): UserRole
    {
        return UserRole::tryFromMixed($this->role);
    }

    public function hasRole(UserRole|string $role): bool
    {
        $expected = $role instanceof UserRole ? $role->value : $role;

        return $this->role === $expected;
    }

    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::Admin);
    }

    public function isDirector(): bool
    {
        return $this->hasRole(UserRole::Director);
    }

    public function isAdminOrDirector(): bool
    {
        return $this->isAdmin() || $this->isDirector();
    }

    public function isProjectHead(): bool
    {
        return $this->hasRole(UserRole::ProjectHead);
    }

    public function isCenterManager(): bool
    {
        return $this->hasRole(UserRole::CenterManager);
    }

    public function isEmployeeUser(): bool
    {
        return $this->hasRole(UserRole::Employee);
    }

    /**
     * Mobile roles that bind to a single registered device.
     * Admin web login is never device-locked.
     */
    public function shouldLockMobileDevice(): bool
    {
        return $this->isDirector()
            || $this->isProjectHead()
            || $this->isCenterManager()
            || $this->isEmployeeUser();
    }

    public function hasRegisteredMobileDevice(): bool
    {
        return filled($this->active_mobile_device_id);
    }

    public function canLoginToMobile(): bool
    {
        if ($this->is_active !== true) {
            return false;
        }

        if (! in_array($this->role, UserRole::mobileValues(), true)) {
            return false;
        }

        if ($this->isEmployeeUser()) {
            return $this->employee !== null && $this->employee->status === true;
        }

        return true;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'admin') {
            return false;
        }

        if ($this->is_active !== true) {
            return false;
        }

        return $this->roleEnum()->canAccessWeb();
    }
}
