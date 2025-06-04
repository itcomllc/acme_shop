<?php

namespace App\Models\Traits;

use App\Models\{Role, Permission};
use Illuminate\Database\Eloquent\Relations\{BelongsToMany, BelongsTo};
use Illuminate\Support\Facades\{Cache, Log};
use Illuminate\Support\Collection;

trait HasRoles
{
    /**
     * Get user's roles
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
                    ->withPivot(['assigned_at', 'expires_at', 'assigned_by', 'notes'])
                    ->withTimestamps();
    }

    /**
     * Get user's primary role
     */
    public function primaryRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'primary_role_id');
    }

    /**
     * Get user's direct permissions
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')
                    ->withPivot(['type', 'assigned_at', 'expires_at', 'assigned_by', 'notes'])
                    ->withTimestamps();
    }

    /**
     * Check if user has specific role
     */
    public function hasRole(string $role): bool
    {
        if ($role instanceof Role) {
            return $this->roles()->where('id', $role->id)->exists();
        }

        return $this->roles()->where('name', $role)->exists();
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()->whereIn('name', $roles)->exists();
    }

    /**
     * Check if user has all of the given roles
     */
    public function hasAllRoles(array $roles): bool
    {
        $userRoles = $this->roles()->pluck('name')->toArray();
        return count(array_intersect($roles, $userRoles)) === count($roles);
    }

    /**
     * Check if user has specific permission
     */
    public function hasPermission(string $permission): bool
    {
        // Check direct permissions first
        $directPermission = $this->permissions()
                                ->where('name', $permission)
                                ->where('is_active', true)
                                ->first();

        if ($directPermission) {
            // If explicitly denied, return false
            if ($directPermission->pivot->type === 'deny') {
                return false;
            }
            // If explicitly granted, return true
            if ($directPermission->pivot->type === 'grant') {
                return true;
            }
        }

        // Check permissions via roles
        return $this->getAllPermissions()->contains('name', $permission);
    }

    /**
     * Check if user has permission for resource and action
     */
    public function hasResourcePermission(string $resource, string $action): bool
    {
        // Check direct permissions first
        $directPermission = $this->permissions()
                                ->where('resource', $resource)
                                ->where('action', $action)
                                ->where('is_active', true)
                                ->first();

        if ($directPermission) {
            if ($directPermission->pivot->type === 'deny') {
                return false;
            }
            if ($directPermission->pivot->type === 'grant') {
                return true;
            }
        }

        // Check via roles
        return $this->getAllPermissions()
                    ->where('resource', $resource)
                    ->where('action', $action)
                    ->isNotEmpty();
    }

    /**
     * Get all permissions for user (via roles and direct assignments)
     */
    public function getAllPermissions(): Collection
    {
        $cacheKey = "user_permissions_{$this->id}";
        
        return Cache::remember($cacheKey, now()->addHours(1), function () {
            // Permissions via roles
            $rolePermissions = Permission::whereHas('roles.users', function ($query) {
                $query->where('users.id', $this->id);
            })->where('is_active', true)->get();

            // Direct permissions (granted only)
            $directPermissions = $this->permissions()
                                     ->where('is_active', true)
                                     ->wherePivot('type', 'grant')
                                     ->get();

            // Merge and remove duplicates
            return $rolePermissions->merge($directPermissions)->unique('id');
        });
    }

    /**
     * Assign role to user
     */
    public function assignRole($role, array $pivotData = []): void
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->firstOrFail();
        }

        if (!$this->hasRole($role)) {
            $defaultPivotData = [
                'assigned_at' => now(),
                'assigned_by' => auth()->id()
            ];

            $this->roles()->attach($role->id, array_merge($defaultPivotData, $pivotData));
            $this->clearPermissionCache();
            $this->updateLastRoleChange();

            Log::info('Role assigned to user', [
                'user_id' => $this->id,
                'role' => $role->name,
                'assigned_by' => auth()->id()
            ]);
        }
    }

    /**
     * Remove role from user
     */
    public function removeRole($role): void
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->firstOrFail();
        }

        if ($this->hasRole($role)) {
            $this->roles()->detach($role->id);
            $this->clearPermissionCache();
            $this->updateLastRoleChange();

            Log::info('Role removed from user', [
                'user_id' => $this->id,
                'role' => $role->name,
                'removed_by' => auth()->id()
            ]);
        }
    }

    /**
     * Sync user roles
     */
    public function syncRoles(array $roleIds): void
    {
        $this->roles()->sync($roleIds);
        $this->clearPermissionCache();
        $this->updateLastRoleChange();

        Log::info('User roles synchronized', [
            'user_id' => $this->id,
            'role_count' => count($roleIds),
            'updated_by' => auth()->id()
        ]);
    }

    /**
     * Set primary role for user
     */
    public function setPrimaryRole($role): void
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->firstOrFail();
        }

        $this->update(['primary_role_id' => $role->id]);
        $this->updateLastRoleChange();

        // Ensure user has this role assigned
        if (!$this->hasRole($role)) {
            $this->assignRole($role);
        }

        Log::info('Primary role set for user', [
            'user_id' => $this->id,
            'primary_role' => $role->name
        ]);
    }

    /**
     * Assign permission directly to user
     */
    public function assignPermission($permission, string $type = 'grant', array $pivotData = []): void
    {
        if (is_string($permission)) {
            $permission = Permission::where('name', $permission)->firstOrFail();
        }

        $defaultPivotData = [
            'type' => $type,
            'assigned_at' => now(),
            'assigned_by' => auth()->id()
        ];

        $this->permissions()->syncWithoutDetaching([
            $permission->id => array_merge($defaultPivotData, $pivotData)
        ]);

        $this->clearPermissionCache();

        Log::info('Permission assigned directly to user', [
            'user_id' => $this->id,
            'permission' => $permission->name,
            'type' => $type,
            'assigned_by' => auth()->id()
        ]);
    }

    /**
     * Remove permission from user
     */
    public function removePermission($permission): void
    {
        if (is_string($permission)) {
            $permission = Permission::where('name', $permission)->firstOrFail();
        }

        $this->permissions()->detach($permission->id);
        $this->clearPermissionCache();

        Log::info('Permission removed from user', [
            'user_id' => $this->id,
            'permission' => $permission->name,
            'removed_by' => auth()->id()
        ]);
    }

    /**
     * Check if user can access admin panel
     */
    public function canAccessAdmin(): bool
    {
        return $this->hasPermission('admin.access') || 
               $this->hasAnyRole([Role::SUPER_ADMIN, Role::ADMIN]);
    }

    /**
     * Check if user can manage SSL certificates
     */
    public function canManageSSL(): bool
    {
        return $this->hasPermission('ssl.certificates.manage') ||
               $this->hasAnyRole([Role::SUPER_ADMIN, Role::ADMIN, Role::SSL_MANAGER]);
    }

    /**
     * Check if user can view all user data
     */
    public function canViewAllUsers(): bool
    {
        return $this->hasPermission('users.view_all') ||
               $this->hasAnyRole([Role::SUPER_ADMIN, Role::ADMIN]);
    }

    /**
     * Get user's highest priority role
     */
    public function getHighestPriorityRole(): ?Role
    {
        return $this->roles()->active()->byPriority()->first();
    }

    /**
     * Get user role display information
     */
    public function getRoleDisplayInfo(): array
    {
        $primaryRole = $this->primaryRole;
        $allRoles = $this->roles()->active()->byPriority()->get();

        return [
            'primary_role' => $primaryRole ? [
                'name' => $primaryRole->name,
                'display_name' => $primaryRole->display_name,
                'color' => $primaryRole->color
            ] : null,
            'all_roles' => $allRoles->map(function ($role) {
                return [
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                    'color' => $role->color,
                    'assigned_at' => $role->pivot->assigned_at
                ];
            })->toArray(),
            'permission_count' => $this->getAllPermissions()->count()
        ];
    }

    /**
     * Clear permission cache for user
     */
    public function clearPermissionCache(): void
    {
        Cache::forget("user_permissions_{$this->id}");
    }

    /**
     * Update last role change timestamp
     */
    private function updateLastRoleChange(): void
    {
        $this->update(['last_role_change' => now()]);
    }

    /**
     * Check if user is super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SUPER_ADMIN);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->hasAnyRole([Role::SUPER_ADMIN, Role::ADMIN]);
    }

    /**
     * Boot trait
     */
    public static function bootHasRoles()
    {
        static::deleting(function ($user) {
            $user->roles()->detach();
            $user->permissions()->detach();
            $user->clearPermissionCache();
        });
    }
}