<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\{DB, Log};
use App\Models\{Role, Permission, User};

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating roles and permissions...');

        DB::transaction(function () {
            // Create permissions first
            $this->createPermissions();
            $this->command->line('✓ Permissions created');

            // Create roles
            $this->createRoles();
            $this->command->line('✓ Roles created');

            // Assign permissions to roles
            $this->assignPermissionsToRoles();
            $this->command->line('✓ Permissions assigned to roles');

            // Assign default roles to existing users
            $this->assignDefaultRolesToUsers();
            $this->command->line('✓ Default roles assigned to users');
        });

        $this->command->info('Roles and permissions setup completed!');
    }

    /**
     * Create default permissions
     */
    private function createPermissions(): void
    {
        $permissions = [
            // Admin permissions
            [
                'name' => 'admin.access',
                'display_name' => 'Access Admin Panel',
                'description' => 'Access to administrative dashboard',
                'category' => 'admin',
                'resource' => 'admin',
                'action' => 'access'
            ],
            [
                'name' => 'admin.users.manage',
                'display_name' => 'Manage Users',
                'description' => 'Create, edit, and delete users',
                'category' => 'admin',
                'resource' => 'users',
                'action' => 'manage'
            ],
            [
                'name' => 'admin.roles.manage',
                'display_name' => 'Manage Roles',
                'description' => 'Create, edit, and delete user roles',
                'category' => 'admin',
                'resource' => 'roles',
                'action' => 'manage'
            ],
            [
                'name' => 'admin.permissions.manage',
                'display_name' => 'Manage Permissions',
                'description' => 'Assign and revoke permissions',
                'category' => 'admin',
                'resource' => 'permissions',
                'action' => 'manage'
            ],

            // SSL permissions
            [
                'name' => 'ssl.certificates.view',
                'display_name' => 'View Certificates',
                'description' => 'View SSL certificates',
                'category' => 'ssl',
                'resource' => 'certificates',
                'action' => 'view'
            ],
            [
                'name' => 'ssl.certificates.create',
                'display_name' => 'Create Certificates',
                'description' => 'Issue new SSL certificates',
                'category' => 'ssl',
                'resource' => 'certificates',
                'action' => 'create'
            ],
            [
                'name' => 'ssl.certificates.manage',
                'display_name' => 'Manage Certificates',
                'description' => 'Full certificate management (renew, revoke)',
                'category' => 'ssl',
                'resource' => 'certificates',
                'action' => 'manage'
            ],
            [
                'name' => 'ssl.certificates.view_all',
                'display_name' => 'View All Certificates',
                'description' => 'View certificates from all users',
                'category' => 'ssl',
                'resource' => 'certificates',
                'action' => 'view_all'
            ],
            [
                'name' => 'ssl.subscriptions.view',
                'display_name' => 'View Subscriptions',
                'description' => 'View SSL subscriptions',
                'category' => 'ssl',
                'resource' => 'subscriptions',
                'action' => 'view'
            ],
            [
                'name' => 'ssl.subscriptions.manage',
                'display_name' => 'Manage Subscriptions',
                'description' => 'Create and manage SSL subscriptions',
                'category' => 'ssl',
                'resource' => 'subscriptions',
                'action' => 'manage'
            ],
            [
                'name' => 'ssl.providers.manage',
                'display_name' => 'Manage SSL Providers',
                'description' => 'Configure and manage SSL certificate providers',
                'category' => 'ssl',
                'resource' => 'providers',
                'action' => 'manage'
            ],

            // User permissions
            [
                'name' => 'users.view',
                'display_name' => 'View Users',
                'description' => 'View user information',
                'category' => 'user',
                'resource' => 'users',
                'action' => 'view'
            ],
            [
                'name' => 'users.view_all',
                'display_name' => 'View All Users',
                'description' => 'View all user accounts',
                'category' => 'user',
                'resource' => 'users',
                'action' => 'view_all'
            ],
            [
                'name' => 'users.edit',
                'display_name' => 'Edit Users',
                'description' => 'Edit user profiles',
                'category' => 'user',
                'resource' => 'users',
                'action' => 'edit'
            ],
            [
                'name' => 'users.delete',
                'display_name' => 'Delete Users',
                'description' => 'Delete user accounts',
                'category' => 'user',
                'resource' => 'users',
                'action' => 'delete'
            ],

            // Billing permissions
            [
                'name' => 'billing.view',
                'display_name' => 'View Billing',
                'description' => 'View billing information',
                'category' => 'billing',
                'resource' => 'billing',
                'action' => 'view'
            ],
            [
                'name' => 'billing.manage',
                'display_name' => 'Manage Billing',
                'description' => 'Manage billing and payments',
                'category' => 'billing',
                'resource' => 'billing',
                'action' => 'manage'
            ],
            [
                'name' => 'billing.view_all',
                'display_name' => 'View All Billing',
                'description' => 'View billing for all users',
                'category' => 'billing',
                'resource' => 'billing',
                'action' => 'view_all'
            ],

            // System permissions
            [
                'name' => 'system.health.view',
                'display_name' => 'View System Health',
                'description' => 'View system health and monitoring',
                'category' => 'system',
                'resource' => 'health',
                'action' => 'view'
            ],
            [
                'name' => 'system.diagnostics.run',
                'display_name' => 'Run Diagnostics',
                'description' => 'Run system diagnostic tests',
                'category' => 'system',
                'resource' => 'diagnostics',
                'action' => 'run'
            ],
            [
                'name' => 'system.logs.view',
                'display_name' => 'View System Logs',
                'description' => 'Access system logs and audit trails',
                'category' => 'system',
                'resource' => 'logs',
                'action' => 'view'
            ],
        ];

        foreach ($permissions as $permissionData) {
            Permission::firstOrCreate(
                ['name' => $permissionData['name']],
                array_merge($permissionData, ['is_active' => true])
            );
        }
    }

    /**
     * Create default roles
     */
    private function createRoles(): void
    {
        $roles = [
            [
                'name' => 'super_admin',
                'display_name' => 'Super Administrator',
                'description' => 'Full system access with all permissions',
                'color' => '#dc2626',
                'priority' => 1,
                'is_active' => true,
                'metadata' => ['system' => true, 'deletable' => false]
            ],
            [
                'name' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'Administrative access to manage system and users',
                'color' => '#ea580c',
                'priority' => 2,
                'is_active' => true,
                'metadata' => ['system' => true, 'deletable' => false]
            ],
            [
                'name' => 'ssl_manager',
                'display_name' => 'SSL Manager',
                'description' => 'Manage SSL certificates and related services',
                'color' => '#059669',
                'priority' => 3,
                'is_active' => true,
                'metadata' => ['system' => false, 'deletable' => true]
            ],
            [
                'name' => 'support',
                'display_name' => 'Support',
                'description' => 'Customer support with read access',
                'color' => '#0891b2',
                'priority' => 4,
                'is_active' => true,
                'metadata' => ['system' => false, 'deletable' => true]
            ],
            [
                'name' => 'viewer',
                'display_name' => 'Viewer',
                'description' => 'Read-only access to system information',
                'color' => '#6b7280',
                'priority' => 5,
                'is_active' => true,
                'metadata' => ['system' => false, 'deletable' => true]
            ],
            [
                'name' => 'user',
                'display_name' => 'User',
                'description' => 'Standard user with basic permissions',
                'color' => '#3b82f6',
                'priority' => 6,
                'is_active' => true,
                'metadata' => ['system' => true, 'deletable' => false]
            ]
        ];

        foreach ($roles as $roleData) {
            Role::firstOrCreate(
                ['name' => $roleData['name']],
                $roleData
            );
        }
    }

    /**
     * Assign permissions to roles
     */
    private function assignPermissionsToRoles(): void
    {
        // Super Admin gets all permissions
        $superAdminRole = Role::where('name', 'super_admin')->first();
        if ($superAdminRole) {
            $allPermissions = Permission::where('is_active', true)->pluck('id');
            $superAdminRole->permissions()->sync($allPermissions);
        }

        // Admin gets most permissions except super admin specific ones
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminPermissions = Permission::where('is_active', true)
                ->whereNotIn('name', [
                    'admin.roles.manage', // Only super admin can manage roles
                    'system.diagnostics.run' // Only super admin can run diagnostics
                ])
                ->pluck('id');
            $adminRole->permissions()->sync($adminPermissions);
        }

        // SSL Manager gets SSL-related permissions
        $sslManagerRole = Role::where('name', 'ssl_manager')->first();
        if ($sslManagerRole) {
            $sslPermissions = Permission::where('is_active', true)
                ->where(function ($query) {
                    $query->where('category', 'ssl')
                          ->orWhereIn('name', [
                              'users.view',
                              'billing.view',
                              'system.health.view'
                          ]);
                })
                ->pluck('id');
            $sslManagerRole->permissions()->sync($sslPermissions);
        }

        // Support gets read-only permissions
        $supportRole = Role::where('name', 'support')->first();
        if ($supportRole) {
            $supportPermissions = Permission::where('is_active', true)
                ->whereIn('action', ['view', 'view_all'])
                ->whereNotIn('name', [
                    'admin.access', // No admin access
                    'system.logs.view' // No system logs
                ])
                ->pluck('id');
            $supportRole->permissions()->sync($supportPermissions);
        }

        // Viewer gets very limited read permissions
        $viewerRole = Role::where('name', 'viewer')->first();
        if ($viewerRole) {
            $viewerPermissions = Permission::where('is_active', true)
                ->whereIn('name', [
                    'ssl.certificates.view',
                    'ssl.subscriptions.view',
                    'billing.view'
                ])
                ->pluck('id');
            $viewerRole->permissions()->sync($viewerPermissions);
        }

        // User gets basic permissions
        $userRole = Role::where('name', 'user')->first();
        if ($userRole) {
            $userPermissions = Permission::where('is_active', true)
                ->whereIn('name', [
                    'ssl.certificates.view',
                    'ssl.certificates.create',
                    'ssl.subscriptions.view',
                    'ssl.subscriptions.manage',
                    'billing.view',
                    'users.view'
                ])
                ->pluck('id');
            $userRole->permissions()->sync($userPermissions);
        }
    }

    /**
     * Assign default roles to existing users
     */
    private function assignDefaultRolesToUsers(): void
    {
        $userRole = Role::where('name', 'user')->first();
        
        if (!$userRole) {
            return;
        }

        // Get users without any roles
        $usersWithoutRoles = User::whereDoesntHave('roles')->get();

        foreach ($usersWithoutRoles as $user) {
            try {
                // Assign basic user role
                $user->roles()->attach($userRole->id, [
                    'assigned_at' => now(),
                    'assigned_by' => 1 // System assignment
                ]);

                // Set as primary role
                $user->update(['primary_role_id' => $userRole->id]);

                Log::info('Default role assigned to user', [
                    'user_id' => $user->id,
                    'role' => $userRole->name
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to assign default role to user', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Assign super admin role to first user (if exists)
        $firstUser = User::first();
        if ($firstUser && !$firstUser->hasRole('super_admin')) {
            $superAdminRole = Role::where('name', 'super_admin')->first();
            if ($superAdminRole) {
                try {
                    $firstUser->roles()->attach($superAdminRole->id, [
                        'assigned_at' => now(),
                        'assigned_by' => 1 // System assignment
                    ]);
                    $firstUser->update(['primary_role_id' => $superAdminRole->id]);

                    Log::info('Super admin role assigned to first user', [
                        'user_id' => $firstUser->id
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to assign super admin role', [
                        'user_id' => $firstUser->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }
}