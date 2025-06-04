<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsToMany, HasMany, HasOne};
use Illuminate\Support\Facades\{Cache, Log};
use Illuminate\Support\Collection;

class Role extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'color',
        'priority',
        'is_active',
        'metadata'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
        'metadata' => 'array'
    ];

    /**
     * System role constants
     */
    public const SUPER_ADMIN = 'super_admin';
    public const ADMIN = 'admin';
    public const SSL_MANAGER = 'ssl_manager';
    public const USER = 'user';
    public const SUPPORT = 'support';
    public const VIEWER = 'viewer';

    /**
     * Get users with this role
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')
                    ->withPivot(['assigned_at', 'expires_at', 'assigned_by', 'notes'])
                    ->withTimestamps();
    }

    /**
     * Get permissions for this role
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
                    ->withTimestamps();
    }

    /**
     * Get users who have this as primary role
     */
    public function primaryUsers(): HasMany
    {
        return $this->hasMany(User::class, 'primary_role_id');
    }

    /**
     * Check if role has specific permission
     */
    public function hasPermission(string $permission): bool
    {
        return $this->permissions()
                    ->where('name', $permission)
                    ->where('is_active', true)
                    ->exists();
    }

    /**
     * Check if role has permission by resource and action
     */
    public function hasResourcePermission(string $resource, string $action): bool
    {
        return $this->permissions()
                    ->where('resource', $resource)
                    ->where('action', $action)
                    ->where('is_active', true)
                    ->exists();
    }

    /**
     * Check if role can access admin panel
     */
    public function canAccessAdmin(): bool
    {
        return $this->hasPermission('admin.access') || 
               in_array($this->name, [self::SUPER_ADMIN, self::ADMIN]);
    }

    /**
     * Check if role can manage SSL certificates
     */
    public function canManageSSL(): bool
    {
        return $this->hasPermission('ssl.manage') ||
               $this->hasResourcePermission('certificates', 'manage') ||
               in_array($this->name, [self::SUPER_ADMIN, self::ADMIN, self::SSL_MANAGER]);
    }

    /**
     * Check if role can view all user data
     */
    public function canViewAllUsers(): bool
    {
        return $this->hasPermission('users.view_all') ||
               in_array($this->name, [self::SUPER_ADMIN, self::ADMIN]);
    }

    /**
     * Assign permission to role
     */
    public function assignPermission(Permission $permission): void
    {
        if (!$this->hasPermission($permission->name)) {
            $this->permissions()->attach($permission->id);
            $this->clearPermissionCache();
            
            Log::info('Permission assigned to role', [
                'role' => $this->name,
                'permission' => $permission->name
            ]);
        }
    }

    /**
     * Remove permission from role
     */
    public function removePermission(Permission $permission): void
    {
        if ($this->hasPermission($permission->name)) {
            $this->permissions()->detach($permission->id);
            $this->clearPermissionCache();
            
            Log::info('Permission removed from role', [
                'role' => $this->name,
                'permission' => $permission->name
            ]);
        }
    }

    /**
     * Sync permissions with role
     */
    public function syncPermissions(array $permissionIds): void
    {
        $this->permissions()->sync($permissionIds);
        $this->clearPermissionCache();
        
        Log::info('Role permissions synchronized', [
            'role' => $this->name,
            'permission_count' => count($permissionIds)
        ]);
    }

    /**
     * Get cached permissions for role
     */
    public function getCachedPermissions(): Collection
    {
        $cacheKey = "role_permissions_{$this->id}";
        
        return Cache::remember($cacheKey, now()->addHours(1), function () {
            return $this->permissions()->where('is_active', true)->get();
        });
    }

    /**
     * Clear permission cache
     */
    public function clearPermissionCache(): void
    {
        Cache::forget("role_permissions_{$this->id}");
        
        // Clear user permission cache for all users with this role
        $this->users()->chunk(100, function ($users) {
            foreach ($users as $user) {
                $user->clearPermissionCache();
            }
        });
    }

    /**
     * Check if role is system role (cannot be deleted)
     */
    public function isSystemRole(): bool
    {
        return in_array($this->name, [
            self::SUPER_ADMIN,
            self::ADMIN,
            self::USER
        ]);
    }

    /**
     * Check if role is higher priority than given role
     */
    public function hasHigherPriorityThan(Role $role): bool
    {
        return $this->priority < $role->priority;
    }

    /**
     * Get role display information
     */
    public function getDisplayInfo(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'description' => $this->description,
            'color' => $this->color,
            'priority' => $this->priority,
            'user_count' => $this->users()->count(),
            'permission_count' => $this->permissions()->count(),
            'is_system' => $this->isSystemRole(),
            'can_access_admin' => $this->canAccessAdmin(),
        ];
    }

    /**
     * Scope for active roles
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for roles by priority
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'asc');
    }

    /**
     * Scope for admin roles
     */
    public function scopeAdminRoles($query)
    {
        return $query->whereIn('name', [self::SUPER_ADMIN, self::ADMIN, self::SSL_MANAGER]);
    }

    /**
     * Create default system roles
     */
    public static function createDefaultRoles(): void
    {
        $defaultRoles = [
            [
                'name' => self::SUPER_ADMIN,
                'display_name' => 'Super Administrator',
                'description' => 'Full system access with all permissions',
                'color' => '#dc2626',
                'priority' => 1,
                'metadata' => ['system' => true, 'deletable' => false]
            ],
            [
                'name' => self::ADMIN,
                'display_name' => 'Administrator',
                'description' => 'Administrative access to manage system and users',
                'color' => '#ea580c',
                'priority' => 2,
                'metadata' => ['system' => true, 'deletable' => false]
            ],
            [
                'name' => self::SSL_MANAGER,
                'display_name' => 'SSL Manager',
                'description' => 'Manage SSL certificates and related services',
                'color' => '#059669',
                'priority' => 3,
                'metadata' => ['system' => false, 'deletable' => true]
            ],
            [
                'name' => self::SUPPORT,
                'display_name' => 'Support',
                'description' => 'Customer support with read access',
                'color' => '#0891b2',
                'priority' => 4,
                'metadata' => ['system' => false, 'deletable' => true]
            ],
            [
                'name' => self::VIEWER,
                'display_name' => 'Viewer',
                'description' => 'Read-only access to system information',
                'color' => '#6b7280',
                'priority' => 5,
                'metadata' => ['system' => false, 'deletable' => true]
            ],
            [
                'name' => self::USER,
                'display_name' => 'User',
                'description' => 'Standard user with basic permissions',
                'color' => '#3b82f6',
                'priority' => 6,
                'metadata' => ['system' => true, 'deletable' => false]
            ]
        ];

        foreach ($defaultRoles as $roleData) {
            self::firstOrCreate(
                ['name' => $roleData['name']],
                $roleData
            );
        }

        Log::info('Default roles created/updated');
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::updated(function ($role) {
            $role->clearPermissionCache();
        });

        static::deleted(function ($role) {
            $role->clearPermissionCache();
        });
    }
}