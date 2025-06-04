<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AdminControllerBase;
use App\Models\{User, Role, Permission};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{Hash, Log, Auth, DB};
use Illuminate\Validation\Rules\Password;

/**
 * Admin User Management Controller
 */
class AdminUserController extends AdminControllerBase
{
    /**
     * Display users management page
     */
    public function index(Request $request)
    {
        $this->authorize('users.view_all');

        $query = User::with(['primaryRole', 'roles'])
                    ->withCount(['subscriptions', 'roles']);

        // Apply filters
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        if ($request->has('status')) {
            switch ($request->status) {
                case 'verified':
                    $query->whereNotNull('email_verified_at');
                    break;
                case 'unverified':
                    $query->whereNull('email_verified_at');
                    break;
                case 'active_subscription':
                    $query->whereHas('subscriptions', function ($q) {
                        $q->where('status', 'active');
                    });
                    break;
            }
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $users
            ]);
        }

        $roles = Role::active()->byPriority()->get();
        return view('admin.users.index', compact('users', 'roles'));
    }

    /**
     * Show specific user details
     */
    public function show(User $user, Request $request): JsonResponse
    {
        $this->authorize('users.view_all');

        $user->load([
            'roles.permissions',
            'permissions',
            'subscriptions.certificates',
            'primaryRole'
        ]);

        $userStats = [
            'total_subscriptions' => $user->subscriptions()->count(),
            'active_subscriptions' => $user->subscriptions()->where('status', 'active')->count(),
            'total_certificates' => $user->subscriptions()
                                        ->withCount('certificates')
                                        ->get()
                                        ->sum('certificates_count'),
            'last_login' => $user->last_login_at ?? 'Never',
            'account_age_days' => $user->created_at->diffInDays(now()),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'role_info' => $user->getRoleDisplayInfo(),
                'stats' => $userStats,
                'permissions' => $user->getAllPermissions()->map(function ($permission) {
                    return [
                        'name' => $permission->name,
                        'display_name' => $permission->display_name,
                        'category' => $permission->category,
                        'source' => 'role' // or 'direct' for direct assignments
                    ];
                })
            ]
        ]);
    }

    /**
     * Create new user
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('admin.users.manage');

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::defaults()],
            'role_id' => 'required|integer|exists:roles,id',
            'email_verified' => 'boolean'
        ]);

        try {
            $user = DB::transaction(function () use ($request) {
                $newUser = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'email_verified_at' => $request->boolean('email_verified') ? now() : null,
                ]);

                $role = Role::findOrFail($request->role_id);
                $newUser->assignRole($role);
                $newUser->setPrimaryRole($role);

                Log::info('User created by admin', [
                    'user_id' => $newUser->id,
                    'email' => $newUser->email,
                    'role' => $role->name,
                    'created_by' => Auth::id()
                ]);

                return $newUser;
            });

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => $user->fresh(['primaryRole', 'roles'])
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create user', [
                'error' => $e->getMessage(),
                'admin_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user
     */
    public function update(User $user, Request $request): JsonResponse
    {
        $this->authorize('users.edit');

        // Prevent self-modification of critical data
        if ($user->id === Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify your own account through admin panel'
            ], 403);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => ['sometimes', 'confirmed', Password::defaults()],
            'primary_role_id' => 'sometimes|integer|exists:roles,id',
            'email_verified' => 'sometimes|boolean'
        ]);

        try {
            DB::transaction(function () use ($user, $request) {
                $updateData = $request->only(['name', 'email']);

                if ($request->has('password')) {
                    $updateData['password'] = Hash::make($request->password);
                }

                if ($request->has('email_verified')) {
                    $updateData['email_verified_at'] = $request->boolean('email_verified') ? now() : null;
                }

                $user->update($updateData);

                // Update primary role if provided
                if ($request->has('primary_role_id')) {
                    $role = Role::findOrFail($request->primary_role_id);
                    $user->setPrimaryRole($role);
                    
                    // Ensure user has this role assigned
                    if (!$user->hasRole($role)) {
                        $user->assignRole($role);
                    }
                }

                Log::info('User updated by admin', [
                    'user_id' => $user->id,
                    'updated_fields' => array_keys($updateData),
                    'updated_by' => Auth::id()
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => $user->fresh(['primaryRole', 'roles'])
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'admin_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete user
     */
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('users.delete');

        // Prevent self-deletion
        if ($user->id === Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete your own account'
            ], 403);
        }

        // Prevent deletion of super admin
        if ($user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete super admin account'
            ], 403);
        }

        // Check for active subscriptions
        $activeSubscriptions = $user->subscriptions()->where('status', 'active')->count();
        if ($activeSubscriptions > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete user with {$activeSubscriptions} active subscription(s)",
                'active_subscriptions' => $activeSubscriptions
            ], 422);
        }

        try {
            DB::transaction(function () use ($user) {
                $userEmail = $user->email;
                
                // Archive user data before deletion
                $this->archiveUserData($user);
                
                $user->delete();

                Log::info('User deleted by admin', [
                    'deleted_user_email' => $userEmail,
                    'deleted_by' => Auth::id()
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'admin_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign role to user
     */
    public function assignRole(User $user, Request $request): JsonResponse
    {
        $this->authorize('admin.users.manage');

        $request->validate([
            'role_id' => 'required|integer|exists:roles,id',
            'set_as_primary' => 'boolean',
            'notes' => 'nullable|string|max:255'
        ]);

        try {
            $role = Role::findOrFail($request->role_id);

            if ($user->hasRole($role)) {
                return response()->json([
                    'success' => false,
                    'message' => 'User already has this role'
                ], 422);
            }

            $user->assignRole($role, [
                'notes' => $request->notes
            ]);

            if ($request->boolean('set_as_primary')) {
                $user->setPrimaryRole($role);
            }

            return response()->json([
                'success' => true,
                'message' => 'Role assigned successfully',
                'data' => $user->fresh(['roles', 'primaryRole'])
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to assign role to user', [
                'user_id' => $user->id,
                'role_id' => $request->role_id,
                'error' => $e->getMessage(),
                'admin_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove role from user
     */
    public function removeRole(User $user, Role $role): JsonResponse
    {
        $this->authorize('admin.users.manage');

        if (!$user->hasRole($role)) {
            return response()->json([
                'success' => false,
                'message' => 'User does not have this role'
            ], 422);
        }

        // Prevent removing the last role
        if ($user->roles()->count() === 1) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove the last role from user'
            ], 422);
        }

        try {
            $user->removeRole($role);

            // Update primary role if necessary
            if ($user->primary_role_id === $role->id) {
                $newPrimaryRole = $user->getHighestPriorityRole();
                if ($newPrimaryRole) {
                    $user->setPrimaryRole($newPrimaryRole);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Role removed successfully',
                'data' => $user->fresh(['roles', 'primaryRole'])
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to remove role from user', [
                'user_id' => $user->id,
                'role_id' => $role->id,
                'error' => $e->getMessage(),
                'admin_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_users' => User::count(),
                'verified_users' => User::whereNotNull('email_verified_at')->count(),
                'users_with_subscriptions' => User::whereHas('subscriptions')->count(),
                'users_with_active_subscriptions' => User::whereHas('subscriptions', function ($q) {
                    $q->where('status', 'active');
                })->count(),
                'recent_registrations' => User::where('created_at', '>=', now()->subDays(30))->count(),
                'role_distribution' => DB::table('user_roles')
                                        ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                                        ->select('roles.display_name as role', DB::raw('count(*) as count'))
                                        ->groupBy('roles.id', 'roles.display_name')
                                        ->orderBy('count', 'desc')
                                        ->get(),
                'recent_logins' => User::whereNotNull('last_login_at')
                                      ->where('last_login_at', '>=', now()->subDays(7))
                                      ->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get user statistics', [
                'error' => $e->getMessage(),
                'admin_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update users
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $this->authorize('admin.users.manage');

        $request->validate([
            'user_ids' => 'required|array|max:100',
            'user_ids.*' => 'integer|exists:users,id',
            'action' => 'required|in:verify_email,unverify_email,activate,deactivate',
            'role_id' => 'required_if:action,assign_role|integer|exists:roles,id'
        ]);

        try {
            $users = User::whereIn('id', $request->user_ids)->get();
            $results = [];

            DB::transaction(function () use ($users, $request, &$results) {
                foreach ($users as $user) {
                    // Skip self-modification
                    if ($user->id === Auth::id()) {
                        $results[] = [
                            'user_id' => $user->id,
                            'user_name' => $user->name,
                            'status' => 'skipped',
                            'reason' => 'Cannot modify own account'
                        ];
                        continue;
                    }

                    try {
                        switch ($request->action) {
                            case 'verify_email':
                                $user->update(['email_verified_at' => now()]);
                                break;
                            case 'unverify_email':
                                $user->update(['email_verified_at' => null]);
                                break;
                            case 'assign_role':
                                $role = Role::findOrFail($request->role_id);
                                if (!$user->hasRole($role)) {
                                    $user->assignRole($role);
                                }
                                break;
                        }

                        $results[] = [
                            'user_id' => $user->id,
                            'user_name' => $user->name,
                            'status' => 'success'
                        ];

                    } catch (\Exception $e) {
                        $results[] = [
                            'user_id' => $user->id,
                            'user_name' => $user->name,
                            'status' => 'failed',
                            'error' => $e->getMessage()
                        ];
                    }
                }
            });

            $successful = collect($results)->where('status', 'success')->count();
            $failed = collect($results)->where('status', 'failed')->count();
            $skipped = collect($results)->where('status', 'skipped')->count();

            Log::info('Bulk user update completed', [
                'action' => $request->action,
                'successful' => $successful,
                'failed' => $failed,
                'skipped' => $skipped,
                'admin_id' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => "Bulk update completed. Success: {$successful}, Failed: {$failed}, Skipped: {$skipped}",
                'data' => [
                    'results' => $results,
                    'summary' => [
                        'successful' => $successful,
                        'failed' => $failed,
                        'skipped' => $skipped
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk user update failed', [
                'error' => $e->getMessage(),
                'admin_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bulk update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Archive user data before deletion
     */
    private function archiveUserData(User $user): void
    {
        // Implementation would depend on your archival requirements
        // This is a placeholder for compliance/audit requirements
        
        Log::info('User data archived', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'subscriptions_count' => $user->subscriptions()->count(),
            'archived_by' => Auth::id()
        ]);
    }

    /**
     * Check if current user can perform action
     */
    protected function authorize(string $permission): void
    {
        parent::authorize($permission);
    }
}