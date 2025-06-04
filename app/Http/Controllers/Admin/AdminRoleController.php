<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AdminControllerBase;
use App\Models\{Role, Permission, User};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{Log, Auth, DB};
use Illuminate\Validation\Rule;

/**
 * Admin Role Management Controller
 */
class AdminRoleController extends AdminControllerBase
{
    /**
     * Display roles management page
     */
    public function index(Request $request)
    {
        $roles = Role::with(['permissions'])
                    ->withCount(['users', 'permissions'])
                    ->byPriority()
                    ->paginate(15);

        $permissions = Permission::active()
                                ->orderBy('category')
                                ->orderBy('display_name')
                                ->get()
                                ->groupBy('category');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'roles' => $roles,
                    'permissions' => $permissions
                ]
            ]);
        }

        return view('admin.roles.index', compact('roles', 'permissions'));
    }

    /**
     * Show specific role details
     */
    public function show(Role $role, Request $request): JsonResponse
    {
        $role->load(['permissions', 'users' => function ($query) {
            $query->select('id', 'name', 'email')->limit(10);
        }]);

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $role,
                'display_info' => $role->getDisplayInfo(),
                'recent_users' => $role->users,
                'total_users' => $role->users()->count()
            ]
        ]);
    }

    /**
     * Create new role
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('admin.roles.manage');

        $request->validate([
            'name' => 'required|string|max:50|unique:roles,name|regex:/^[a-z_]+$/',
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'priority' => 'required|integer|min:1|max:999',
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id'
        ]);

        try {
            $role = DB::transaction(function () use ($request) {
                $newRole = Role::create([
                    'name' => $request->name,
                    'display_name' => $request->display_name,
                    'description' => $request->description,
                    'color' => $request->color,
                    'priority' => $request->priority,
                    'is_active' => true,
                    'metadata' => [
                        'created_by' => Auth::id(),
                        'system' => false,
                        'deletable' => true
                    ]
                ]);

                if ($request->has('permissions')) {
                    $newRole->syncPermissions($request->permissions);
                }

                Log::info('Role created', [
                    'role_id' => $newRole->id,
                    'role_name' => $newRole->name,
                    'created_by' => Auth::id()
                ]);

                return $newRole;
            });

            return response()->json([
                'success' => true,
                'message' => 'Role created successfully',
                'data' => $role->fresh(['permissions'])
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create role', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update role
     */
    public function update(Role $role, Request $request): JsonResponse
    {
        $this->authorize('admin.roles.manage');

        // Prevent modification of system roles
        if ($role->isSystemRole()) {
            return response()->json([
                'success' => false,
                'message' => 'System roles cannot be modified'
            ], 403);
        }

        $request->validate([
            'display_name' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string|max:255',
            'color' => 'sometimes|required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'priority' => 'sometimes|required|integer|min:1|max:999',
            'is_active' => 'sometimes|boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id'
        ]);

        try {
            DB::transaction(function () use ($role, $request) {
                $role->update($request->only([
                    'display_name',
                    'description', 
                    'color',
                    'priority',
                    'is_active'
                ]));

                if ($request->has('permissions')) {
                    $role->syncPermissions($request->permissions);
                }

                Log::info('Role updated', [
                    'role_id' => $role->id,
                    'role_name' => $role->name,
                    'updated_by' => Auth::id()
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Role updated successfully',
                'data' => $role->fresh(['permissions'])
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update role', [
                'role_id' => $role->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete role
     */
    public function destroy(Role $role): JsonResponse
    {
        $this->authorize('admin.roles.manage');

        // Prevent deletion of system roles
        if ($role->isSystemRole()) {
            return response()->json([
                'success' => false,
                'message' => 'System roles cannot be deleted'
            ], 403);
        }

        // Check if role has users
        if ($role->users()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete role that has assigned users',
                'user_count' => $role->users()->count()
            ], 422);
        }

        try {
            DB::transaction(function () use ($role) {
                $roleName = $role->name;
                $role->delete();

                Log::info('Role deleted', [
                    'role_name' => $roleName,
                    'deleted_by' => Auth::id()
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete role', [
                'role_id' => $role->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign role to user
     */
    public function assignToUser(Role $role, Request $request): JsonResponse
    {
        $this->authorize('admin.users.manage');

        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'expires_at' => 'nullable|date|after:now',
            'notes' => 'nullable|string|max:255'
        ]);

        try {
            $user = User::findOrFail($request->user_id);

            // Check if user already has this role
            if ($user->hasRole($role)) {
                return response()->json([
                    'success' => false,
                    'message' => 'User already has this role'
                ], 422);
            }

            $pivotData = [
                'notes' => $request->notes
            ];

            if ($request->expires_at) {
                $pivotData['expires_at'] = $request->expires_at;
            }

            $user->assignRole($role, $pivotData);

            // Set as primary role if user doesn't have one
            if (!$user->primary_role_id) {
                $user->setPrimaryRole($role);
            }

            return response()->json([
                'success' => true,
                'message' => 'Role assigned to user successfully',
                'data' => [
                    'user' => $user->only(['id', 'name', 'email']),
                    'role' => $role->only(['id', 'name', 'display_name'])
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to assign role to user', [
                'role_id' => $role->id,
                'user_id' => $request->user_id,
                'error' => $e->getMessage(),
                'assigned_by' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign role to user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove role from user
     */
    public function removeFromUser(Role $role, Request $request): JsonResponse
    {
        $this->authorize('admin.users.manage');

        $request->validate([
            'user_id' => 'required|integer|exists:users,id'
        ]);

        try {
            $user = User::findOrFail($request->user_id);

            if (!$user->hasRole($role)) {
                return response()->json([
                    'success' => false,
                    'message' => 'User does not have this role'
                ], 422);
            }

            // Prevent removing the last role from user
            if ($user->roles()->count() === 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot remove the last role from user'
                ], 422);
            }

            $user->removeRole($role);

            // If this was the primary role, set a new primary role
            if ($user->primary_role_id === $role->id) {
                $newPrimaryRole = $user->getHighestPriorityRole();
                if ($newPrimaryRole) {
                    $user->setPrimaryRole($newPrimaryRole);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Role removed from user successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to remove role from user', [
                'role_id' => $role->id,
                'user_id' => $request->user_id,
                'error' => $e->getMessage(),
                'removed_by' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove role from user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get role statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_roles' => Role::count(),
                'active_roles' => Role::active()->count(),
                'system_roles' => Role::whereIn('name', [
                    Role::SUPER_ADMIN,
                    Role::ADMIN,
                    Role::USER
                ])->count(),
                'custom_roles' => Role::whereNotIn('name', [
                    Role::SUPER_ADMIN,
                    Role::ADMIN,
                    Role::USER
                ])->count(),
                'role_distribution' => Role::withCount('users')
                                         ->get()
                                         ->map(function ($role) {
                                             return [
                                                 'role' => $role->display_name,
                                                 'users' => $role->users_count,
                                                 'color' => $role->color
                                             ];
                                         }),
                'recent_assignments' => DB::table('user_roles')
                                         ->join('users', 'user_roles.user_id', '=', 'users.id')
                                         ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                                         ->select('users.name as user_name', 'roles.display_name as role_name', 'user_roles.assigned_at')
                                         ->orderBy('user_roles.assigned_at', 'desc')
                                         ->limit(10)
                                         ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get role statistics', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk assign roles to users
     */
    public function bulkAssign(Request $request): JsonResponse
    {
        $this->authorize('admin.users.manage');

        $request->validate([
            'user_ids' => 'required|array|max:100',
            'user_ids.*' => 'integer|exists:users,id',
            'role_id' => 'required|integer|exists:roles,id',
            'notes' => 'nullable|string|max:255'
        ]);

        try {
            $role = Role::findOrFail($request->role_id);
            $users = User::whereIn('id', $request->user_ids)->get();
            $results = [];

            DB::transaction(function () use ($users, $role, $request, &$results) {
                foreach ($users as $user) {
                    try {
                        if (!$user->hasRole($role)) {
                            $user->assignRole($role, [
                                'notes' => $request->notes
                            ]);
                            $results[] = [
                                'user_id' => $user->id,
                                'user_name' => $user->name,
                                'status' => 'assigned'
                            ];
                        } else {
                            $results[] = [
                                'user_id' => $user->id,
                                'user_name' => $user->name,
                                'status' => 'already_has_role'
                            ];
                        }
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

            $assigned = collect($results)->where('status', 'assigned')->count();
            $skipped = collect($results)->where('status', 'already_has_role')->count();
            $failed = collect($results)->where('status', 'failed')->count();

            Log::info('Bulk role assignment completed', [
                'role_id' => $role->id,
                'assigned' => $assigned,
                'skipped' => $skipped,
                'failed' => $failed,
                'performed_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => "Bulk assignment completed. Assigned: {$assigned}, Skipped: {$skipped}, Failed: {$failed}",
                'data' => [
                    'results' => $results,
                    'summary' => [
                        'assigned' => $assigned,
                        'skipped' => $skipped,
                        'failed' => $failed
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk role assignment failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bulk assignment failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if current user can perform action
     */
    protected function authorize(string $permission): void
    {
        parent::authorize($permission);
    }
}