<?php

namespace App\Models\Traits;

use App\Models\{Role, Permission};
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\{Cache, Log};
use Illuminate\Support\Collection;

/**
 * HasRoles Trait
 * ユーザーモデルにロールと権限の機能を追加するトレイト
 */
trait HasRoles
{
    /**
     * Boot the trait
     */
    protected static function bootHasRoles(): void
    {
        static::updated(function ($model) {
            if ($model->isDirty('primary_role_id')) {
                $model->clearPermissionCache();
            }
        });

        static::deleted(function ($model) {
            $model->clearPermissionCache();
        });
    }

    /**
     * User's roles relationship
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
                    ->withPivot(['assigned_at', 'expires_at', 'assigned_by', 'notes'])
                    ->withTimestamps();
    }

    /**
     * User's direct permissions relationship
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')
                    ->withPivot(['type', 'assigned_at', 'expires_at', 'assigned_by', 'notes'])
                    ->withTimestamps();
    }

    /**
     * Primary role relationship
     */
    public function primaryRole()
    {
        return $this->belongsTo(Role::class, 'primary_role_id');
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string|Role $role): bool
    {
        if ($role instanceof Role) {
            $roleName = $role->name;
        } else {
            $roleName = $role;
        }

        // Check primary role
        if ($this->primaryRole && $this->primaryRole->name === $roleName) {
            return true;
        }

        // Check additional roles
        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user has all of the given roles
     */
    public function hasAllRoles(array $roles): bool
    {
        foreach ($roles as $role) {
            if (!$this->hasRole($role)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        return $this->getAllPermissions()->contains('name', $permission);
    }

    /**
     * Check if user has any of the given permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        $userPermissions = $this->getAllPermissions()->pluck('name');
        
        foreach ($permissions as $permission) {
            if ($userPermissions->contains($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user has all of the given permissions
     */
    public function hasAllPermissions(array $permissions): bool
    {
        $userPermissions = $this->getAllPermissions()->pluck('name');
        
        foreach ($permissions as $permission) {
            if (!$userPermissions->contains($permission)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get all permissions for the user (from roles and direct assignments)
     */
    public function getAllPermissions(): Collection
    {
        $cacheKey = "user_permissions_{$this->id}";
        
        return Cache::remember($cacheKey, now()->addHours(1), function () {
            $permissions = collect();

            // Get permissions from primary role
            if ($this->primaryRole) {
                $permissions = $permissions->merge($this->primaryRole->permissions);
            }

            // Get permissions from additional roles
            $this->roles->each(function ($role) use (&$permissions) {
                $permissions = $permissions->merge($role->permissions);
            });

            // Get direct permissions (grants only, not denies)
            $directPermissions = $this->permissions()
                                     ->wherePivot('type', 'grant')
                                     ->where(function ($query) {
                                         $query->whereNull('user_permissions.expires_at')
                                               ->orWhere('user_permissions.expires_at', '>', now());
                                     })
                                     ->get();
            
            $permissions = $permissions->merge($directPermissions);

            // Remove duplicates and sort
            return $permissions->unique('id')->sortBy('name');
        });
    }

    /**
     * Assign a role to the user
     */
    public function assignRole(Role|string $role, array $pivotData = []): void
    {
        if ($role instanceof Role) {
            $roleModel = $role;
        } else {
            $roleModel = Role::where('name', $role)->firstOrFail();
        }

        if ($this->hasRole($roleModel)) {
            return; // Already has this role
        }

        $defaultPivotData = [
            'assigned_at' => now(),
            'assigned_by' => auth()->id()
        ];

        $this->roles()->attach($roleModel->id, array_merge($defaultPivotData, $pivotData));
        $this->clearPermissionCache();

        Log::info('Role assigned to user', [
            'user_id' => $this->id,
            'role_id' => $roleModel->id,
            'role_name' => $roleModel->name,
            'assigned_by' => auth()->id()
        ]);
    }

    /**
     * Remove a role from the user
     */
    public function removeRole(Role|string $role): void
    {
        if ($role instanceof Role) {
            $roleModel = $role;
        } else {
            $roleModel = Role::where('name', $role)->firstOrFail();
        }

        // Remove from additional roles
        $this->roles()->detach($roleModel->id);

        // Clear primary role if it matches
        if ($this->primary_role_id === $roleModel->id) {
            $this->update(['primary_role_id' => null]);
        }

        $this->clearPermissionCache();

        Log::info('Role removed from user', [
            'user_id' => $this->id,
            'role_id' => $roleModel->id,
            'role_name' => $roleModel->name,
            'removed_by' => auth()->id()
        ]);
    }

    /**
     * Sync user roles
     */
    public function syncRoles(array $roles): void
    {
        $roleIds = [];
        
        foreach ($roles as $role) {
            if ($role instanceof Role) {
                $roleIds[] = $role->id;
            } else {
                $roleModel = Role::where('name', $role)->first();
                if ($roleModel) {
                    $roleIds[] = $roleModel->id;
                }
            }
        }

        $this->roles()->sync($roleIds);
        $this->clearPermissionCache();

        Log::info('User roles synchronized', [
            'user_id' => $this->id,
            'role_count' => count($roleIds),
            'synced_by' => auth()->id()
        ]);
    }

    /**
     * Set primary role for the user
     */
    public function setPrimaryRole(Role|string $role): void
    {
        if ($role instanceof Role) {
            $roleModel = $role;
        } else {
            $roleModel = Role::where('name', $role)->firstOrFail();
        }

        $this->update([
            'primary_role_id' => $roleModel->id,
            'last_role_change' => now()
        ]);

        // Ensure user has this role assigned
        if (!$this->hasRole($roleModel)) {
            $this->assignRole($roleModel);
        }

        $this->clearPermissionCache();

        Log::info('Primary role set for user', [
            'user_id' => $this->id,
            'role_id' => $roleModel->id,
            'role_name' => $roleModel->name,
            'set_by' => auth()->id()
        ]);
    }

    /**
     * Get the highest priority role for the user
     */
    public function getHighestPriorityRole(): ?Role
    {
        return $this->roles()
                   ->orderBy('priority', 'asc')
                   ->first();
    }

    /**
     * Check if user can access admin panel
     */
    public function canAccessAdmin(): bool
    {
        return $this->hasPermission('admin.access') || 
               $this->hasAnyRole(['super_admin', 'admin']);
    }

    /**
     * Check if user is super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['super_admin', 'admin']);
    }

    /**
     * Get role display information
     */
    public function getRoleDisplayInfo(): array
    {
        $primaryRole = $this->primaryRole;
        $allRoles = $this->roles;
        
        return [
            'primary_role' => $primaryRole ? [
                'id' => $primaryRole->id,
                'name' => $primaryRole->name,
                'display_name' => $primaryRole->display_name,
                'color' => $primaryRole->color,
                'priority' => $primaryRole->priority
            ] : null,
            'all_roles' => $allRoles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                    'color' => $role->color,
                    'priority' => $role->priority
                ];
            }),
            'role_count' => $allRoles->count(),
            'permission_count' => $this->getAllPermissions()->count(),
            'has_admin_access' => $this->canAccessAdmin()
        ];
    }

    /**
     * Grant a permission directly to the user
     */
    public function grantPermission(Permission|string $permission, array $pivotData = []): void
    {
        if ($permission instanceof Permission) {
            $permissionModel = $permission;
        } else {
            $permissionModel = Permission::where('name', $permission)->firstOrFail();
        }

        $defaultPivotData = [
            'type' => 'grant',
            'assigned_at' => now(),
            'assigned_by' => auth()->id()
        ];

        $this->permissions()->syncWithoutDetaching([
            $permissionModel->id => array_merge($defaultPivotData, $pivotData)
        ]);

        $this->clearPermissionCache();

        Log::info('Permission granted to user', [
            'user_id' => $this->id,
            'permission_id' => $permissionModel->id,
            'permission_name' => $permissionModel->name,
            'granted_by' => auth()->id()
        ]);
    }

    /**
     * Revoke a permission from the user
     */
    public function revokePermission(Permission|string $permission): void
    {
        if ($permission instanceof Permission) {
            $permissionModel = $permission;
        } else {
            $permissionModel = Permission::where('name', $permission)->firstOrFail();
        }

        $this->permissions()->detach($permissionModel->id);
        $this->clearPermissionCache();

        Log::info('Permission revoked from user', [
            'user_id' => $this->id,
            'permission_id' => $permissionModel->id,
            'permission_name' => $permissionModel->name,
            'revoked_by' => auth()->id()
        ]);
    }

    /**
     * Clear permission cache for this user
     */
    public function clearPermissionCache(): void
    {
        Cache::forget("user_permissions_{$this->id}");
        
        // Also clear role permission caches if user has roles
        $this->roles->each(function ($role) {
            $role->clearPermissionCache();
        });

        if ($this->primaryRole) {
            $this->primaryRole->clearPermissionCache();
        }
    }

    /**
     * Scope to filter users by role
     */
    public function scopeWithRole($query, string $role)
    {
        return $query->whereHas('roles', function ($q) use ($role) {
            $q->where('name', $role);
        })->orWhereHas('primaryRole', function ($q) use ($role) {
            $q->where('name', $role);
        });
    }

    /**
     * Scope to filter users by permission
     */
    public function scopeWithPermission($query, string $permission)
    {
        return $query->whereHas('roles.permissions', function ($q) use ($permission) {
            $q->where('name', $permission);
        })->orWhereHas('permissions', function ($q) use ($permission) {
            $q->where('name', $permission)
              ->where('user_permissions.type', 'grant');
        });
    }

    /**
     * Scope to filter users by direct permission assignment
     */
    public function scopeWithDirectPermission($query, string $permission)
    {
        return $query->whereHas('permissions', function ($q) use ($permission) {
            $q->where('name', $permission)
              ->where('user_permissions.type', 'grant');
        });
    }
}