<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{Role, Permission, User};
use Illuminate\Support\Facades\{Hash, Log};

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating roles and permissions...');

        // Create permissions first
        Permission::createDefaultPermissions();
        $this->command->info('✓ Permissions created');

        // Create roles
        Role::createDefaultRoles();
        $this->command->info('✓ Roles created');

        // Assign permissions to roles
        Permission::assignDefaultPermissions();
        $this->command->info('✓ Permissions assigned to roles');

        // Create default super admin user if it doesn't exist
        $this->createDefaultSuperAdmin();
        $this->command->info('✓ Default super admin created');

        // Update existing users to have basic user role
        $this->assignDefaultUserRoles();
        $this->command->info('✓ Existing users updated with default roles');

        $this->command->info('Roles and permissions setup complete!');
    }

    /**
     * Create default super admin user
     */
    private function createDefaultSuperAdmin(): void
    {
        $superAdminRole = Role::where('name', Role::SUPER_ADMIN)->first();
        
        if (!$superAdminRole) {
            $this->command->error('Super admin role not found!');
            return;
        }

        // Check if super admin already exists
        $existingSuperAdmin = User::whereHas('roles', function ($query) {
            $query->where('name', Role::SUPER_ADMIN);
        })->first();

        if ($existingSuperAdmin) {
            $this->command->info("Super admin already exists: {$existingSuperAdmin->email}");
            return;
        }

        // Create super admin user
        $superAdmin = User::create([
            'name' => 'Super Administrator',
            'email' => 'admin@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password123'), // Change this in production!
            'primary_role_id' => $superAdminRole->id,
        ]);

        $superAdmin->assignRole($superAdminRole);

        $this->command->warn('Default super admin created:');
        $this->command->warn("Email: {$superAdmin->email}");
        $this->command->warn("Password: password123");
        $this->command->warn('⚠️  Please change this password immediately!');

        Log::info('Default super admin user created', [
            'user_id' => $superAdmin->id,
            'email' => $superAdmin->email
        ]);
    }

    /**
     * Assign default roles to existing users
     */
    private function assignDefaultUserRoles(): void
    {
        $userRole = Role::where('name', Role::USER)->first();
        
        if (!$userRole) {
            $this->command->error('User role not found!');
            return;
        }

        // Get users without any roles
        $usersWithoutRoles = User::whereDoesntHave('roles')->get();

        foreach ($usersWithoutRoles as $user) {
            $user->assignRole($userRole);
            $user->setPrimaryRole($userRole);
            
            $this->command->info("Assigned user role to: {$user->email}");
        }

        // Set primary role for users who don't have one
        $usersWithoutPrimaryRole = User::whereNull('primary_role_id')
                                      ->whereHas('roles')
                                      ->get();

        foreach ($usersWithoutPrimaryRole as $user) {
            $highestRole = $user->getHighestPriorityRole();
            if ($highestRole) {
                $user->setPrimaryRole($highestRole);
                $this->command->info("Set primary role for: {$user->email} -> {$highestRole->display_name}");
            }
        }
    }
}