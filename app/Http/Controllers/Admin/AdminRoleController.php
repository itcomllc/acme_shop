<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AdminControllerBase;
use App\Models\{Role, Permission, User};
use Illuminate\Http\{Request, JsonResponse, RedirectResponse};
use Illuminate\Routing\Controllers\{HasMiddleware, Middleware};
use Illuminate\View\View;
use Illuminate\Support\Facades\{DB, Log, Validator, Auth};
use Illuminate\Validation\Rule;

class AdminRoleController extends AdminControllerBase implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            ...parent::middleware(),
            new Middleware('permission:admin.roles.manage'),
        ];
    }

    /**
     * Display a listing of roles
     */
    public function index(Request $request): View|JsonResponse|RedirectResponse
    {
        try {
            $query = Role::with(['permissions', 'users']);

            // Search functionality
            if ($request->filled('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('display_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Permission filter
            if ($request->filled('permission')) {
                $query->whereHas('permissions', function ($q) use ($request) {
                    $q->where('name', $request->get('permission'));
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'priority');
            $sortDirection = $request->get('sort_direction', 'asc');
            $query->orderBy($sortBy, $sortDirection);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $roles = $query->paginate($perPage);

            // Add user counts
            $roles->getCollection()->transform(function ($role) {
                $role->users_count = $role->users->count();
                $role->permissions_count = $role->permissions->count();
                return $role;
            });

            // Return JSON for AJAX requests
            if ($request->expectsJson()) {
                // Check if this is a request for permissions
                if ($request->get('permissions') === '1') {
                    $permissions = Permission::all();
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'roles' => [
                                'data' => $roles->items(),
                                'pagination' => [
                                    'current_page' => $roles->currentPage(),
                                    'last_page' => $roles->lastPage(),
                                    'per_page' => $roles->perPage(),
                                    'total' => $roles->total(),
                                ]
                            ],
                            'permissions' => $permissions->keyBy('id')
                        ]
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'roles' => [
                            'data' => $roles->items(),
                            'pagination' => [
                                'current_page' => $roles->currentPage(),
                                'last_page' => $roles->lastPage(),
                                'per_page' => $roles->perPage(),
                                'total' => $roles->total(),
                            ]
                        ]
                    ]
                ]);
            }

            $permissions = Permission::all();

            return view('admin.roles.index', compact('roles', 'permissions'));
        } catch (\Exception $e) {
            Log::error('Failed to load roles in admin panel', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to load roles',
                    'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
                ], 500);
            }

            return back()->withErrors(['error' => 'Failed to load roles: ' . $e->getMessage()]);
        }
    }

    /**
     * Show the form for creating a new role
     */
    public function create(): View
    {
        $permissions = Permission::all()->groupBy('category');
        return view('admin.roles.create', compact('permissions'));
    }

    /**
     * Store a newly created role
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'unique:roles', 'regex:/^[a-z_]+$/'],
            'display_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'priority' => ['required', 'integer', 'min:1', 'max:999'],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            /** @var Role $role */
            $role = Role::create([
                'name' => $request->name,
                'display_name' => $request->display_name,
                'description' => $request->description,
                'color' => $request->color ?? '#3b82f6',
                'priority' => $request->priority,
                'is_active' => true,
            ]);

            // Attach permissions
            if ($request->filled('permissions')) {
                $role->permissions()->attach($request->permissions);
            }

            DB::commit();

            Log::info('Role created by admin', [
                'admin_id' => Auth::id(),
                'role_id' => $role->id,
                'role_name' => $role->name,
                'permissions_count' => count($request->permissions ?? [])
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Role created successfully',
                    'data' => $role->load('permissions')
                ]);
            }

            return redirect()->route('admin.roles.index')
                ->with('success', 'Role created successfully');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create role', [
                'admin_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create role'
                ], 500);
            }

            return back()->with('error', 'Failed to create role')->withInput();
        }
    }

    /**
     * Display the specified role
     */
    public function show(Role $role, Request $request): View|JsonResponse|RedirectResponse
    {
        try {
            // Load relationships with proper select to avoid ambiguous column issues
            $role->load([
                'permissions' => function ($query) {
                    $query->select('permissions.*');
                },
                'users' => function ($query) {
                    $query->select('users.id', 'users.name', 'users.email', 'users.created_at');
                }
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'role' => $role
                    ]
                ]);
            }

            return view('admin.roles.show', compact('role'));
        } catch (\Exception $e) {
            Log::error('Failed to load role details', [
                'role_id' => $role->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to load role details',
                    'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
                ], 500);
            }

            return back()->withErrors(['error' => 'Failed to load role details']);
        }
    }

    /**
     * Show the form for editing the role
     */
    public function edit(Role $role): View
    {
        // Prevent editing system roles
        if ($role->isSystemRole()) {
            abort(403, 'System roles cannot be edited');
        }

        $role->load('permissions');
        $permissions = Permission::all()->groupBy('category');

        return view('admin.roles.edit', compact('role', 'permissions'));
    }

    /**
     * Update the specified role
     */
    public function update(Request $request, Role $role): RedirectResponse|JsonResponse
    {
        // Prevent updating system roles
        if ($role->isSystemRole()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'System roles cannot be updated'
                ], 403);
            }
            return back()->with('error', 'System roles cannot be updated');
        }

        $validator = Validator::make($request->all(), [
            'display_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'priority' => ['required', 'integer', 'min:1', 'max:999'],
            'is_active' => ['boolean'],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $role->update([
                'display_name' => $request->display_name,
                'description' => $request->description,
                'color' => $request->color ?? $role->color,
                'priority' => $request->priority,
                'is_active' => $request->boolean('is_active', true),
            ]);

            // Sync permissions
            $role->permissions()->sync($request->permissions ?? []);

            DB::commit();

            Log::info('Role updated by admin', [
                'admin_id' => Auth::id(),
                'role_id' => $role->id,
                'role_name' => $role->name,
                'permissions_count' => count($request->permissions ?? [])
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Role updated successfully',
                    'data' => $role->fresh(['permissions'])
                ]);
            }

            return redirect()->route('admin.roles.index')
                ->with('success', 'Role updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update role', [
                'admin_id' => Auth::id(),
                'role_id' => $role->id,
                'error' => $e->getMessage()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update role'
                ], 500);
            }

            return back()->with('error', 'Failed to update role')->withInput();
        }
    }

    /**
     * Remove the specified role
     */
    public function destroy(Role $role): RedirectResponse|JsonResponse
    {
        // Prevent deletion of system roles
        if ($role->isSystemRole()) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'System roles cannot be deleted'
                ], 403);
            }
            return back()->with('error', 'System roles cannot be deleted');
        }

        // Check if role is assigned to users
        if ($role->users()->count() > 0) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete role that is assigned to users'
                ], 422);
            }
            return back()->with('error', 'Cannot delete role that is assigned to users');
        }

        try {
            DB::beginTransaction();

            $roleId = $role->id;
            $roleName = $role->name;

            // Detach all permissions
            $role->permissions()->detach();

            // Delete the role
            $role->delete();

            DB::commit();

            Log::info('Role deleted by admin', [
                'admin_id' => Auth::id(),
                'deleted_role_id' => $roleId,
                'deleted_role_name' => $roleName
            ]);

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Role deleted successfully'
                ]);
            }

            return redirect()->route('admin.roles.index')
                ->with('success', 'Role deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete role', [
                'admin_id' => Auth::id(),
                'role_id' => $role->id,
                'error' => $e->getMessage()
            ]);

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete role'
                ], 500);
            }

            return back()->with('error', 'Failed to delete role');
        }
    }

    /**
     * Assign role to user
     */
    public function assignToUser(Request $request, Role $role): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'exists:users,id'],
            'is_primary' => ['boolean']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            /** @var User $user */
            $user = User::findOrFail($request->user_id);

            if ($request->boolean('is_primary')) {
                $user->update(['primary_role_id' => $role->id]);
            } else {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }

            DB::commit();

            Log::info('Role assigned to user by admin', [
                'admin_id' => Auth::id(),
                'user_id' => $user->id,
                'role_id' => $role->id,
                'is_primary' => $request->boolean('is_primary')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Role assigned successfully',
                'data' => [
                    'user' => $user->fresh(['primaryRole', 'roles']),
                    'role' => $role
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to assign role to user', [
                'admin_id' => Auth::id(),
                'user_id' => $request->user_id,
                'role_id' => $role->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign role'
            ], 500);
        }
    }

    /**
     * Remove role from user
     */
    public function removeFromUser(Request $request, Role $role): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'exists:users,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            /** @var User $user */
            $user = User::findOrFail($request->user_id);

            // If it's the primary role, clear it
            if ($user->primary_role_id === $role->id) {
                $user->update(['primary_role_id' => null]);
            }

            // Remove from additional roles
            $user->roles()->detach($role->id);

            DB::commit();

            Log::info('Role removed from user by admin', [
                'admin_id' => Auth::id(),
                'user_id' => $user->id,
                'role_id' => $role->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Role removed successfully',
                'data' => [
                    'user' => $user->fresh(['primaryRole', 'roles']),
                    'role' => $role
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to remove role from user', [
                'admin_id' => Auth::id(),
                'user_id' => $request->user_id,
                'role_id' => $role->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove role'
            ], 500);
        }
    }

    /**
     * Bulk assign roles
     */
    public function bulkAssign(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'role_id' => ['required', 'exists:roles,id'],
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['exists:users,id'],
            'is_primary' => ['boolean']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $role = Role::findOrFail($request->role_id);
            $userIds = $request->user_ids;
            $isPrimary = $request->boolean('is_primary');
            $updated = 0;

            foreach ($userIds as $userId) {
                $user = User::find($userId);
                if (!$user) continue;

                if ($isPrimary) {
                    $user->update(['primary_role_id' => $role->id]);
                } else {
                    $user->roles()->syncWithoutDetaching([$role->id]);
                }

                $updated++;
            }

            DB::commit();

            Log::info('Bulk role assignment by admin', [
                'admin_id' => Auth::id(),
                'role_id' => $role->id,
                'user_count' => $updated,
                'is_primary' => $isPrimary
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully assigned role to {$updated} users",
                'updated_count' => $updated
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Bulk role assignment failed', [
                'admin_id' => Auth::id(),
                'role_id' => $request->role_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bulk assignment failed'
            ], 500);
        }
    }

    /**
     * Get role statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $stats = [
                'total_roles' => Role::count(),
                'active_roles' => Role::where('is_active', true)->count(),
                'system_roles' => Role::where('metadata->system', true)->count(),
                'custom_roles' => Role::where('metadata->system', '!=', true)->orWhereNull('metadata->system')->count(),
                'total_users' => User::count(),
                'total_permissions' => Permission::count(),
                'role_usage' => $this->getRoleUsage(),
                'permission_distribution' => $this->getPermissionDistribution(),
                'most_assigned_roles' => $this->getMostAssignedRoles(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load role statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get role usage statistics
     */
    private function getRoleUsage(): array
    {
        try {
            return DB::table('roles')
                ->select('roles.display_name as role')
                ->selectRaw('COUNT(DISTINCT COALESCE(users.id, user_roles.user_id)) as user_count')
                ->leftJoin('users', 'users.primary_role_id', '=', 'roles.id')
                ->leftJoin('user_roles', 'user_roles.role_id', '=', 'roles.id')
                ->groupBy('roles.id', 'roles.display_name')
                ->orderBy('user_count', 'desc')
                ->pluck('user_count', 'role')
                ->toArray();
        } catch (\Exception $e) {
            Log::warning('Failed to get role usage statistics', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get permission distribution
     */
    private function getPermissionDistribution(): array
    {
        try {
            return DB::table('permissions')
                ->select('permissions.category')
                ->selectRaw('COUNT(DISTINCT role_permissions.role_id) as role_count')
                ->leftJoin('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->groupBy('permissions.category')
                ->orderBy('role_count', 'desc')
                ->pluck('role_count', 'category')
                ->toArray();
        } catch (\Exception $e) {
            Log::warning('Failed to get permission distribution', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get most assigned roles
     */
    private function getMostAssignedRoles(): array
    {
        try {
            return DB::table('roles')
                ->select('roles.display_name as role')
                ->selectRaw('COUNT(DISTINCT COALESCE(users.id, user_roles.user_id)) as assignment_count')
                ->leftJoin('users', 'users.primary_role_id', '=', 'roles.id')
                ->leftJoin('user_roles', 'user_roles.role_id', '=', 'roles.id')
                ->groupBy('roles.id', 'roles.display_name')
                ->orderBy('assignment_count', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'role' => $item->role,
                        'count' => $item->assignment_count
                    ];
                })
                ->toArray();
        } catch (\Exception $e) {
            Log::warning('Failed to get most assigned roles', ['error' => $e->getMessage()]);
            return [];
        }
    }
}