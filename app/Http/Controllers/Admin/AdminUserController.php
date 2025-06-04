<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AdminControllerBase;
use App\Models\{User, Role, Permission};
use Illuminate\Http\{Request, JsonResponse, RedirectResponse};
use Illuminate\Support\Facades\{Hash, Log, Auth, DB};
use Illuminate\Validation\Rules\Password;
use Illuminate\Routing\Controllers\{HasMiddleware, Middleware};

/**
 * Admin User Management Controller
 */
class AdminUserController extends AdminControllerBase implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            ...parent::middleware(),
            new Middleware('permission:users.view_all', only: ['index', 'show']),
            new Middleware('permission:admin.users.manage', only: ['store', 'update', 'destroy', 'assignRole', 'removeRole', 'bulkUpdate']),
        ];
    }

    /**
     * Display users management page
     */
    public function index(Request $request)
    {
        try {
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
                // Transform the data for frontend consumption
                $transformedUsers = $users->getCollection()->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'email_verified_at' => $user->email_verified_at,
                        'created_at' => $user->created_at,
                        'subscriptions_count' => $user->subscriptions_count ?? 0,
                        'roles_count' => $user->roles_count ?? 0,
                        'primary_role' => $user->primaryRole ? [
                            'id' => $user->primaryRole->id,
                            'name' => $user->primaryRole->name,
                            'display_name' => $user->primaryRole->display_name,
                            'color' => $user->primaryRole->color ?? '#3b82f6'
                        ] : null,
                        'roles' => $user->roles->map(function ($role) {
                            return [
                                'id' => $role->id,
                                'name' => $role->name,
                                'display_name' => $role->display_name,
                                'color' => $role->color ?? '#3b82f6'
                            ];
                        })
                    ];
                });

                return response()->json([
                    'success' => true,
                    'data' => $transformedUsers,
                    'pagination' => [
                        'current_page' => $users->currentPage(),
                        'last_page' => $users->lastPage(),
                        'per_page' => $users->perPage(),
                        'total' => $users->total(),
                        'from' => $users->firstItem(),
                        'to' => $users->lastItem()
                    ]
                ]);
            }

            $roles = Role::active()->byPriority()->get();
            return view('admin.users.index', compact('users', 'roles'));
        } catch (\Exception $e) {
            Log::error('Failed to load users in admin panel', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to load users',
                    'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
                ], 500);
            }

            return back()->withErrors(['error' => 'Failed to load users: ' . $e->getMessage()]);
        }
    }

    /**
     * Show the form for creating a new user
     */
    public function create()
    {
        $roles = Role::active()->byPriority()->get();
        return view('admin.users.create', compact('roles'));
    }

    /**
     * Show specific user details
     */
    public function show(User $user, Request $request)
    {
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

        if ($request->expectsJson()) {
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

        return view('admin.users.show', compact('user', 'userStats'));
    }


    /**
     * Store a newly created user
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::defaults()],
            'role_id' => 'required|integer|exists:roles,id',
            'email_verified' => 'boolean'
        ]);

        try {
            $user = DB::transaction(function () use ($request) {
                $userData = [
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                ];

                $isEmailVerified = $request->boolean('email_verified');

                // email_verified_atの設定
                if ($isEmailVerified) {
                    $userData['email_verified_at'] = now();
                }

                $newUser = User::create($userData);

                $role = Role::findOrFail($request->role_id);
                $newUser->assignRole($role);
                $newUser->setPrimaryRole($role);

                // Registeredイベントを条件付きで発火
                if (!$isEmailVerified) {
                    // メール認証が必要な場合のみRegisteredイベントを発火
                    event(new \Illuminate\Auth\Events\Registered($newUser));

                    Log::info('Registered event fired for admin-created user', [
                        'user_id' => $newUser->id,
                        'email' => $newUser->email,
                        'reason' => 'email_verification_required'
                    ]);
                } else {
                    // メール認証済みの場合は、Registeredイベントをスキップしてウェルカムメール送信
                    \App\Jobs\SendUserWelcomeEmail::dispatch($newUser, true); // true = created by admin

                    Log::info('User created with verified email, skipped Registered event', [
                        'user_id' => $newUser->id,
                        'email' => $newUser->email,
                        'reason' => 'email_pre_verified'
                    ]);
                }

                Log::info('User created by admin', [
                    'user_id' => $newUser->id,
                    'email' => $newUser->email,
                    'role' => $role->name,
                    'created_by' => Auth::id(),
                    'email_verified' => $isEmailVerified,
                    'email_verified_at' => $newUser->email_verified_at,
                    'registered_event_fired' => !$isEmailVerified
                ]);

                return $newUser;
            });

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'User created successfully',
                    'data' => $user->fresh(['primaryRole', 'roles'])
                ], 201);
            }

            return redirect()->route('admin.users.index')
                ->with('success', 'User created successfully');
        } catch (\Exception $e) {
            Log::error('Failed to create user', [
                'error' => $e->getMessage(),
                'admin_id' => Auth::id()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create user',
                    'error' => $e->getMessage()
                ], 500);
            }

            return back()->withErrors(['error' => 'Failed to create user: ' . $e->getMessage()]);
        }
    }

    /**
     * Show the form for editing the specified user
     */
    public function edit(User $user)
    {
        $roles = Role::active()->byPriority()->get();
        return view('admin.users.edit', compact('user', 'roles'));
    }

    /**
     * Update user
     */
    public function update(User $user, Request $request)
    {
        // Prevent self-modification of critical data
        if ($user->id === Auth::id()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot modify your own account through admin panel'
                ], 403);
            }
            return back()->withErrors(['error' => 'Cannot modify your own account through admin panel']);
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

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'User updated successfully',
                    'data' => $user->fresh(['primaryRole', 'roles'])
                ]);
            }

            return redirect()->route('admin.users.index')
                ->with('success', 'User updated successfully');
        } catch (\Exception $e) {
            Log::error('Failed to update user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'admin_id' => Auth::id()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update user',
                    'error' => $e->getMessage()
                ], 500);
            }

            return back()->withErrors(['error' => 'Failed to update user: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete user
     */
    public function destroy(User $user, Request $request)
    {
        // Prevent self-deletion
        if ($user->id === Auth::id()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete your own account'
                ], 403);
            }
            return back()->withErrors(['error' => 'Cannot delete your own account']);
        }

        // Prevent deletion of super admin
        if ($user->isSuperAdmin()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete super admin account'
                ], 403);
            }
            return back()->withErrors(['error' => 'Cannot delete super admin account']);
        }

        // Check for active subscriptions
        $activeSubscriptions = $user->subscriptions()->where('status', 'active')->count();
        if ($activeSubscriptions > 0) {
            $message = "Cannot delete user with {$activeSubscriptions} active subscription(s)";
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'active_subscriptions' => $activeSubscriptions
                ], 422);
            }
            return back()->withErrors(['error' => $message]);
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

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'User deleted successfully'
                ]);
            }

            return redirect()->route('admin.users.index')
                ->with('success', 'User deleted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to delete user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'admin_id' => Auth::id()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete user',
                    'error' => $e->getMessage()
                ], 500);
            }

            return back()->withErrors(['error' => 'Failed to delete user: ' . $e->getMessage()]);
        }
    }

    /**
     * Assign role to user
     */
    public function assignRole(User $user, Request $request): JsonResponse
    {
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
            // Get basic user counts
            $totalUsers = User::count();
            $verifiedUsers = User::whereNotNull('email_verified_at')->count();

            // Get subscription-related counts (handle case where subscriptions table doesn't exist)
            $usersWithSubscriptions = 0;
            $usersWithActiveSubscriptions = 0;

            try {
                $usersWithSubscriptions = User::whereHas('subscriptions')->count();
                $usersWithActiveSubscriptions = User::whereHas('subscriptions', function ($q) {
                    $q->where('status', 'active');
                })->count();
            } catch (\Exception $e) {
                // Subscriptions table might not exist yet
                Log::info('Subscriptions table not available for statistics', [
                    'error' => $e->getMessage()
                ]);
            }

            // Get recent registrations
            $recentRegistrations = User::where('created_at', '>=', now()->subDays(30))->count();

            // Get role distribution
            $roleDistribution = collect();
            try {
                $roleDistribution = DB::table('user_roles')
                    ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                    ->select('roles.display_name as role', DB::raw('count(*) as count'))
                    ->groupBy('roles.id', 'roles.display_name')
                    ->orderBy('count', 'desc')
                    ->get();
            } catch (\Exception $e) {
                Log::info('Role distribution query failed', [
                    'error' => $e->getMessage()
                ]);
            }

            // Get recent logins (handle case where last_login_at column doesn't exist)
            $recentLogins = 0;
            try {
                $recentLogins = User::whereNotNull('last_login_at')
                    ->where('last_login_at', '>=', now()->subDays(7))
                    ->count();
            } catch (\Exception $e) {
                // last_login_at column might not exist
                Log::info('last_login_at column not available', [
                    'error' => $e->getMessage()
                ]);
            }

            $stats = [
                'total_users' => $totalUsers,
                'verified_users' => $verifiedUsers,
                'users_with_subscriptions' => $usersWithSubscriptions,
                'users_with_active_subscriptions' => $usersWithActiveSubscriptions,
                'recent_registrations' => $recentRegistrations,
                'role_distribution' => $roleDistribution,
                'recent_logins' => $recentLogins
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get user statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'admin_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Bulk update users
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'user_ids' => 'required|array|max:100',
            'user_ids.*' => 'integer|exists:users,id',
            'action' => 'required|in:verify_email,unverify_email,activate,deactivate,resend_verification,assign_role', // activate,deactivate実装されていないが、将来の拡張のためかもしれない
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
                            case 'resend_verification':
                                if (!$user->hasVerifiedEmail()) {
                                    $this->resendVerification($user, request());
                                }
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
     * Resend email verification notification
     */
    public function resendVerification(User $user, Request $request)
    {
        if ($user->hasVerifiedEmail()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email is already verified'
                ], 422);
            }
            return back()->withErrors(['error' => 'Email is already verified']);
        }

        try {
            $user->sendEmailVerificationNotification();

            Log::info('Email verification resent by admin', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'admin_id' => Auth::id()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Verification email sent successfully'
                ]);
            }

            return back()->with('success', 'Verification email sent successfully');
        } catch (\Exception $e) {
            Log::error('Failed to resend verification email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'admin_id' => Auth::id()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send verification email',
                    'error' => $e->getMessage()
                ], 500);
            }

            return back()->withErrors(['error' => 'Failed to send verification email: ' . $e->getMessage()]);
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
}
