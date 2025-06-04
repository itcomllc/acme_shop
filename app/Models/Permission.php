<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsToMany, HasMany};
use Illuminate\Support\Facades\Log;

class Permission extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'category',
        'resource',
        'action',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    /**
     * Permission categories
     */
    public const CATEGORY_ADMIN = 'admin';
    public const CATEGORY_SSL = 'ssl';
    public const CATEGORY_USER = 'user';
    public const CATEGORY_BILLING = 'billing';
    public const CATEGORY_SYSTEM = 'system';

    /**
     * Get roles that have this permission
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions')
                    ->withTimestamps();
    }

    /**
     * Get users who have this permission directly assigned
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_permissions')
                    ->withPivot(['type', 'assigned_at', 'expires_at', 'assigned_by', 'notes'])
                    ->withTimestamps();
    }

    /**
     * Get all users who have this permission (via roles or direct assignment)
     */
    public function getAllUsersWithPermission()
    {
        // Users with permission via roles
        $usersViaRoles = User::whereHas('roles.permissions', function ($query) {
            $query->where('permissions.id', $this->id);
        });

        // Users with direct permission assignment
        $usersViaPermissions = User::whereHas('permissions', function ($query) {
            $query->where('permissions.id', $this->id)
                  ->where('user_permissions.type', 'grant');
        });

        return $usersViaRoles->union($usersViaPermissions);
    }

    /**
     * Check if permission is system permission (cannot be deleted)
     */
    public function isSystemPermission(): bool
    {
        return $this->category === self::CATEGORY_SYSTEM ||
               str_starts_with($this->name, 'system.');
    }

    /**
     * Get permission display information
     */
    public function getDisplayInfo(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'description' => $this->description,
            'category' => $this->category,
            'resource' => $this->resource,
            'action' => $this->action,
            'role_count' => $this->roles()->count(),
            'user_count' => $this->users()->count(),
            'is_system' => $this->isSystemPermission(),
        ];
    }

    /**
     * Scope for active permissions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for permissions by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for permissions by resource
     */
    public function scopeByResource($query, string $resource)
    {
        return $query->where('resource', $resource);
    }

    /**
     * Create default system permissions
     */
    public static function createDefaultPermissions(): void
    {
        $defaultPermissions = [
            // Admin permissions
            [
                'name' => 'admin.access',
                'display_name' => 'Access Admin Panel',
                'description' => 'Access to administrative dashboard',
                'category' => self::CATEGORY_ADMIN,
                'resource' => 'admin',
                'action' => 'access'
            ],
            [
                'name' => 'admin.users.manage',
                'display_name' => 'Manage Users',
                'description' => 'Create, edit, and delete users',
                'category' => self::CATEGORY_ADMIN,
                'resource' => 'users',
                'action' => 'manage'
            ],
            [
                'name' => 'admin.roles.manage',
                'display_name' => 'Manage Roles',
                'description' => 'Create, edit, and delete user roles',
                'category' => self::CATEGORY_ADMIN,
                'resource' => 'roles',
                'action' => 'manage'
            ],
            [
                'name' => 'admin.permissions.manage',
                'display_name' => 'Manage Permissions',
                'description' => 'Assign and revoke permissions',
                'category' => self::CATEGORY_ADMIN,
                'resource' => 'permissions',
                'action' => 'manage'
            ],

            // SSL permissions
            [
                'name' => 'ssl.certificates.view',
                'display_name' => 'View Certificates',
                'description' => 'View SSL certificates',
                'category' => self::CATEGORY_SSL,
                'resource' => 'certificates',
                'action' => 'view'
            ],
            [
                'name' => 'ssl.certificates.create',
                'display_name' => 'Create Certificates',
                'description' => 'Issue new SSL certificates',
                'category' => self::CATEGORY_SSL,
                'resource' => 'certificates',
                'action' => 'create'
            ],
            [
                'name' => 'ssl.certificates.manage',
                'display_name' => 'Manage Certificates',
                'description' => 'Full certificate management (renew, revoke)',
                'category' => self::CATEGORY_SSL,
                'resource' => 'certificates',
                'action' => 'manage'
            ],
            [
                'name' => 'ssl.certificates.view_all',
                'display_name' => 'View All Certificates',
                'description' => 'View certificates from all users',
                'category' => self::CATEGORY_SSL,
                'resource' => 'certificates',
                'action' => 'view_all'
            ],
            [
                'name' => 'ssl.subscriptions.view',
                'display_name' => 'View Subscriptions',
                'description' => 'View SSL subscriptions',
                'category' => self::CATEGORY_SSL,
                'resource' => 'subscriptions',
                'action' => 'view'
            ],
            [
                'name' => 'ssl.subscriptions.manage',
                'display_name' => 'Manage Subscriptions',
                'description' => 'Create and manage SSL subscriptions',
                'category' => self::CATEGORY_SSL,
                'resource' => 'subscriptions',
                'action' => 'manage'
            ],
            [
                'name' => 'ssl.providers.manage',
                'display_name' => 'Manage SSL Providers',
                'description' => 'Configure and manage SSL certificate providers',
                'category' => self::CATEGORY_SSL,
                'resource' => 'providers',
                'action' => 'manage'
            ],

            // User permissions
            [
                'name' => 'users.view',
                'display_name' => 'View Users',
                'description' => 'View user information',
                'category' => self::CATEGORY_USER,
                'resource' => 'users',
                'action' => 'view'
            ],
            [
                'name' => 'users.view_all',
                'display_name' => 'View All Users',
                'description' => 'View all user accounts',
                'category' => self::CATEGORY_USER,
                'resource' => 'users',
                'action' => 'view_all'
            ],
            [
                'name' => 'users.edit',
                'display_name' => 'Edit Users',
                'description' => 'Edit user profiles',
                'category' => self::CATEGORY_USER,
                'resource' => 'users',
                'action' => 'edit'
            ],
            [
                'name' => 'users.delete',
                'display_name' => 'Delete Users',
                'description' => 'Delete user accounts',
                'category' => self::CATEGORY_USER,
                'resource' => 'users',
                'action' => 'delete'
            ],

            // Billing permissions
            [
                'name' => 'billing.view',
                'display_name' => 'View Billing',
                'description' => 'View billing information',
                'category' => self::CATEGORY_BILLING,
                'resource' => 'billing',
                'action' => 'view'
            ],
            [
                'name' => 'billing.manage',
                'display_name' => 'Manage Billing',
                'description' => 'Manage billing and payments',
                'category' => self::CATEGORY_BILLING,
                'resource' => 'billing',
                'action' => 'manage'
            ],
            [
                'name' => 'billing.view_all',
                'display_name' => 'View All Billing',
                'description' => 'View billing for all users',
                'category' => self::CATEGORY_BILLING,
                'resource' => 'billing',
                'action' => 'view_all'
            ],

            // System permissions
            [
                'name' => 'system.health.view',
                'display_name' => 'View System Health',
                'description' => 'View system health and monitoring',
                'category' => self::CATEGORY_SYSTEM,
                'resource' => 'health',
                'action' => 'view'
            ],
            [
                'name' => 'system.diagnostics.run',
                'display_name' => 'Run Diagnostics',
                'description' => 'Run system diagnostic tests',
                'category' => self::CATEGORY_SYSTEM,
                'resource' => 'diagnostics',
                'action' => 'run'
            ],
            [
                'name' => 'system.logs.view',
                'display_name' => 'View System Logs',
                'description' => 'Access system logs and audit trails',
                'category' => self::CATEGORY_SYSTEM,
                'resource' => 'logs',
                'action' => 'view'
            ],
        ];

        foreach ($defaultPermissions as $permissionData) {
            self::firstOrCreate(
                ['name' => $permissionData['name']],
                $permissionData
            );
        }

        Log::info('Default permissions created/updated');
    }

    /**
     * Assign default permissions to roles
     */
    public static function assignDefaultPermissions(): void
    {
        // Super Admin gets all permissions
        $superAdminRole = Role::where('name', Role::SUPER_ADMIN)->first();
        if ($superAdminRole) {
            $allPermissions = self::active()->pluck('id');
            $superAdminRole->syncPermissions($allPermissions->toArray());
        }

        // Admin gets most permissions except super admin specific ones
        $adminRole = Role::where('name', Role::ADMIN)->first();
        if ($adminRole) {
            $adminPermissions = self::active()
                ->whereNotIn('name', [
                    'admin.roles.manage', // Only super admin can manage roles
                    'system.diagnostics.run' // Only super admin can run diagnostics
                ])
                ->pluck('id');
            $adminRole->syncPermissions($adminPermissions->toArray());
        }

        // SSL Manager gets SSL-related permissions
        $sslManagerRole = Role::where('name', Role::SSL_MANAGER)->first();
        if ($sslManagerRole) {
            $sslPermissions = self::active()
                ->whereIn('category', [self::CATEGORY_SSL])
                ->orWhereIn('name', [
                    'users.view',
                    'billing.view',
                    'system.health.view'
                ])
                ->pluck('id');
            $sslManagerRole->syncPermissions($sslPermissions->toArray());
        }

        // Support gets read-only permissions
        $supportRole = Role::where('name', Role::SUPPORT)->first();
        if ($supportRole) {
            $supportPermissions = self::active()
                ->whereIn('action', ['view', 'view_all'])
                ->whereNotIn('name', [
                    'admin.access', // No admin access
                    'system.logs.view' // No system logs
                ])
                ->pluck('id');
            $supportRole->syncPermissions($supportPermissions->toArray());
        }

        // Viewer gets very limited read permissions
        $viewerRole = Role::where('name', Role::VIEWER)->first();
        if ($viewerRole) {
            $viewerPermissions = self::active()
                ->whereIn('name', [
                    'ssl.certificates.view',
                    'ssl.subscriptions.view',
                    'billing.view'
                ])
                ->pluck('id');
            $viewerRole->syncPermissions($viewerPermissions->toArray());
        }

        // User gets basic permissions
        $userRole = Role::where('name', Role::USER)->first();
        if ($userRole) {
            $userPermissions = self::active()
                ->whereIn('name', [
                    'ssl.certificates.view',
                    'ssl.certificates.create',
                    'ssl.subscriptions.view',
                    'ssl.subscriptions.manage',
                    'billing.view',
                    'users.view'
                ])
                ->pluck('id');
            $userRole->syncPermissions($userPermissions->toArray());
        }

        Log::info('Default permissions assigned to roles');
    }
}