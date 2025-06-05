<x-layouts.admin :title="__('Role Management')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-6">
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="xl">{{ __('Role Management') }}</flux:heading>
                    <flux:subheading>{{ __('Manage user roles and permissions') }}</flux:subheading>
                </div>
                <button onclick="showCreateRoleModal()" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Create Role
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-2 rounded-lg text-blue-600 bg-blue-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Roles</p>
                        <p class="text-2xl font-bold text-gray-900" id="total-roles-count">Loading...</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-2 rounded-lg text-green-600 bg-green-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Active Roles</p>
                        <p class="text-2xl font-bold text-gray-900" id="active-roles-count">Loading...</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-2 rounded-lg text-purple-600 bg-purple-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Users</p>
                        <p class="text-2xl font-bold text-gray-900" id="total-users-count">Loading...</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-2 rounded-lg text-yellow-600 bg-yellow-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Permissions</p>
                        <p class="text-2xl font-bold text-gray-900" id="total-permissions-count">Loading...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
                <div class="flex items-center space-x-4">
                    <input type="text" 
                           id="search-roles" 
                           placeholder="Search roles..." 
                           class="border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <select id="filter-status" class="border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <select id="filter-type" class="border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All Types</option>
                        <option value="system">System Roles</option>
                        <option value="custom">Custom Roles</option>
                    </select>
                </div>
                <div class="flex items-center space-x-2">
                    <button onclick="showBulkActionModal()" 
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg flex items-center">
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                        Bulk Actions
                    </button>
                    <button onclick="refreshRoles()" 
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Roles Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">System Roles</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200" id="roles-table">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <input type="checkbox" id="select-all-roles" class="rounded">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Users</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Permissions</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="roles-tbody">
                        <!-- Roles will be loaded here via AJAX -->
                    </tbody>
                </table>
            </div>
            
            <!-- Loading State -->
            <div id="roles-loading" class="p-8 text-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                <p class="text-gray-500 mt-2">Loading roles...</p>
            </div>
            
            <!-- Empty State -->
            <div id="roles-empty" class="p-8 text-center hidden">
                <svg class="h-12 w-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No roles found</h3>
                <p class="text-gray-500">Create your first role to get started.</p>
            </div>
        </div>

        <!-- Pagination -->
        <div id="pagination-container" class="mt-6"></div>
    </div>

    <!-- Create Role Modal -->
    <div id="createRoleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Create New Role</h3>
                    <button onclick="closeCreateRoleModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>
                
                <form id="createRoleForm" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Role Name (System)</label>
                            <input type="text" 
                                   name="name" 
                                   id="role-name"
                                   placeholder="e.g., content_manager"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   pattern="^[a-z_]+$"
                                   required>
                            <p class="text-xs text-gray-500 mt-1">Lowercase letters and underscores only</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Display Name</label>
                            <input type="text" 
                                   name="display_name" 
                                   id="role-display-name"
                                   placeholder="e.g., Content Manager"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   required>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" 
                                  id="role-description"
                                  rows="3"
                                  placeholder="Brief description of this role..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Color</label>
                            <input type="color" 
                                   name="color" 
                                   id="role-color"
                                   value="#3b82f6"
                                   class="w-full h-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                            <input type="number" 
                                   name="priority" 
                                   id="role-priority"
                                   min="1" 
                                   max="999"
                                   value="10"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   required>
                            <p class="text-xs text-gray-500 mt-1">Lower numbers = higher priority</p>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-3">Permissions</label>
                        <div id="permissions-container" class="max-h-64 overflow-y-auto border border-gray-300 rounded-md p-4">
                            <!-- Permissions will be loaded here -->
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" 
                                onclick="closeCreateRoleModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md">
                            Create Role
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Role Modal -->
    <div id="editRoleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Edit Role</h3>
                    <button onclick="closeEditRoleModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>
                
                <form id="editRoleForm" class="space-y-6">
                    <input type="hidden" id="edit-role-id">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Display Name</label>
                            <input type="text" 
                                   name="display_name" 
                                   id="edit-role-display-name"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select name="is_active" 
                                    id="edit-role-status"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" 
                                  id="edit-role-description"
                                  rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Color</label>
                            <input type="color" 
                                   name="color" 
                                   id="edit-role-color"
                                   class="w-full h-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                            <input type="number" 
                                   name="priority" 
                                   id="edit-role-priority"
                                   min="1" 
                                   max="999"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   required>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-3">Permissions</label>
                        <div id="edit-permissions-container" class="max-h-64 overflow-y-auto border border-gray-300 rounded-md p-4">
                            <!-- Permissions will be loaded here -->
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" 
                                onclick="closeEditRoleModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md">
                            Update Role
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Role Modal -->
    <div id="viewRoleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-2/3 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Role Details</h3>
                    <button onclick="closeViewRoleModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>
                
                <div id="role-details-content">
                    <!-- Role details will be loaded here -->
                </div>
                
                <div class="flex justify-end mt-6">
                    <button onclick="closeViewRoleModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Actions Modal -->
    <div id="bulkActionsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Bulk Actions</h3>
                    <button onclick="closeBulkActionsModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>
                
                <form id="bulkActionsForm" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Action</label>
                        <select name="action" id="bulk-action" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required>
                            <option value="">Select an action</option>
                            <option value="activate">Activate Roles</option>
                            <option value="deactivate">Deactivate Roles</option>
                            <option value="sync_permissions">Sync Permissions</option>
                        </select>
                    </div>
                    
                    <div id="sync-permissions-selection" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Template Role</label>
                        <select name="template_role_id" id="bulk-template-role"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select template role</option>
                            <!-- Roles will be loaded here -->
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Selected roles will copy permissions from this template</p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-600">
                            <span id="selected-roles-count">0</span> roles selected
                        </p>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeBulkActionsModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md">
                            Apply Actions
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let currentPage = 1;
        let allRoles = [];
        let allPermissions = [];
        let filteredRoles = [];

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            loadRoles();
            loadPermissions();
            loadStatistics();
            setupEventListeners();
        });

        function setupEventListeners() {
            // Search functionality
            document.getElementById('search-roles').addEventListener('input', debounce(filterRoles, 300));
            document.getElementById('filter-status').addEventListener('change', filterRoles);
            document.getElementById('filter-type').addEventListener('change', filterRoles);
            
            // Select all functionality
            document.getElementById('select-all-roles').addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.role-checkbox');
                checkboxes.forEach(cb => cb.checked = this.checked);
                updateSelectedCount();
            });
            
            // Form submissions
            document.getElementById('createRoleForm').addEventListener('submit', handleCreateRole);
            document.getElementById('editRoleForm').addEventListener('submit', handleEditRole);
            document.getElementById('bulkActionsForm').addEventListener('submit', handleBulkActions);

            // Bulk action role selection
            document.getElementById('bulk-action').addEventListener('change', function() {
                const syncSelection = document.getElementById('sync-permissions-selection');
                if (this.value === 'sync_permissions') {
                    syncSelection.classList.remove('hidden');
                } else {
                    syncSelection.classList.add('hidden');
                }
            });
        }

        // Load roles data
        async function loadRoles(page = 1) {
            try {
                showLoading();
                const response = await fetch(`/admin/roles?page=${page}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                if (!response.ok) throw new Error('Failed to load roles');
                
                const data = await response.json();
                
                if (data.success) {
                    allRoles = data.data.roles?.data || data.data || [];
                    filteredRoles = [...allRoles];
                    renderRoles(filteredRoles);
                    
                    if (data.data.roles?.pagination) {
                        renderPagination(data.data.roles.pagination);
                    }
                } else {
                    throw new Error(data.message || 'Failed to load roles');
                }
                
                hideLoading();
            } catch (error) {
                console.error('Error loading roles:', error);
                showError('Failed to load roles: ' + error.message);
                hideLoading();
                showEmptyState();
            }
        }

        // Load permissions data
        async function loadPermissions() {
            try {
                const response = await fetch('/admin/roles?permissions=1', {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                if (!response.ok) throw new Error('Failed to load permissions');
                
                const data = await response.json();
                allPermissions = data.data.permissions || {};
                renderPermissions();
                populateTemplateRoles();
            } catch (error) {
                console.error('Error loading permissions:', error);
            }
        }

        // Load statistics
        async function loadStatistics() {
            try {
                const response = await fetch('/admin/roles-statistics', {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                if (!response.ok) throw new Error('Failed to load statistics');
                
                const data = await response.json();
                const stats = data.data;
                
                document.getElementById('total-roles-count').textContent = stats.total_roles || 0;
                document.getElementById('active-roles-count').textContent = stats.active_roles || 0;
                document.getElementById('total-users-count').textContent = stats.total_users || 0;
                document.getElementById('total-permissions-count').textContent = stats.total_permissions || 0;
            } catch (error) {
                console.error('Error loading statistics:', error);
                // Set default values
                document.getElementById('total-roles-count').textContent = '0';
                document.getElementById('active-roles-count').textContent = '0';
                document.getElementById('total-users-count').textContent = '0';
                document.getElementById('total-permissions-count').textContent = '0';
            }
        }

        // Render roles table
        function renderRoles(roles) {
            const tbody = document.getElementById('roles-tbody');
            
            if (!Array.isArray(roles) || roles.length === 0) {
                showEmptyState();
                return;
            }
            
            tbody.innerHTML = roles.map(role => `
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <input type="checkbox" class="role-checkbox rounded" value="${role.id}" onchange="updateSelectedCount()">
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="w-4 h-4 rounded-full mr-3" style="background-color: ${role.color || '#3b82f6'}"></div>
                            <div>
                                <div class="text-sm font-medium text-gray-900">${role.display_name}</div>
                                <div class="text-sm text-gray-500">${role.name}</div>
                                ${role.description ? `<div class="text-xs text-gray-400 mt-1">${role.description}</div>` : ''}
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${role.users_count || 0} users
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${role.permissions_count || 0} permissions
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                            role.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                        }">
                            ${role.is_active ? 'Active' : 'Inactive'}
                        </span>
                        ${role.metadata?.system ? '<span class="ml-2 text-xs text-blue-600">System</span>' : ''}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${role.priority}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <div class="flex space-x-2">
                            <button onclick="viewRole(${role.id})" 
                                    class="text-blue-600 hover:text-blue-900">View</button>
                            ${!role.metadata?.system ? `
                                <button onclick="editRole(${role.id})" 
                                        class="text-green-600 hover:text-green-900">Edit</button>
                                <button onclick="deleteRole(${role.id})" 
                                        class="text-red-600 hover:text-red-900">Delete</button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `).join('');

            document.getElementById('roles-table').classList.remove('hidden');
            document.getElementById('roles-empty').classList.add('hidden');
        }

        // Render permissions checkboxes
        function renderPermissions(containerId = 'permissions-container', selectedPermissions = []) {
            const container = document.getElementById(containerId);
            if (!container) return;
            
            // Group permissions by category
            const groupedPermissions = {};
            Object.values(allPermissions).forEach(permission => {
                const category = permission.category || 'other';
                if (!groupedPermissions[category]) {
                    groupedPermissions[category] = [];
                }
                groupedPermissions[category].push(permission);
            });
            
            container.innerHTML = Object.entries(groupedPermissions).map(([category, permissions]) => `
                <div class="mb-4">
                    <h4 class="text-sm font-medium text-gray-900 mb-2 capitalize">${category.replace('_', ' ')}</h4>
                    <div class="space-y-2">
                        ${permissions.map(permission => `
                            <label class="flex items-center">
                                <input type="checkbox" 
                                       name="permissions[]" 
                                       value="${permission.id}"
                                       ${selectedPermissions.includes(permission.id) ? 'checked' : ''}
                                       class="rounded mr-2">
                                <div>
                                    <span class="text-sm text-gray-900">${permission.display_name}</span>
                                    ${permission.description ? `<p class="text-xs text-gray-500">${permission.description}</p>` : ''}
                                </div>
                            </label>
                        `).join('')}
                    </div>
                </div>
            `).join('');
        }

        // Populate template roles for bulk actions
        function populateTemplateRoles() {
            const select = document.getElementById('bulk-template-role');
            if (!select) return;

            select.innerHTML = '<option value="">Select template role</option>';
            allRoles.forEach(role => {
                const option = document.createElement('option');
                option.value = role.id;
                option.textContent = role.display_name;
                select.appendChild(option);
            });
        }

        // Filter roles
        function filterRoles() {
            const search = document.getElementById('search-roles').value.toLowerCase();
            const status = document.getElementById('filter-status').value;
            const type = document.getElementById('filter-type').value;
            
            filteredRoles = allRoles.filter(role => {
                const matchesSearch = role.display_name.toLowerCase().includes(search) || 
                                    role.name.toLowerCase().includes(search);
                const matchesStatus = !status || 
                                    (status === 'active' && role.is_active) ||
                                    (status === 'inactive' && !role.is_active);
                const matchesType = !type ||
                                   (type === 'system' && role.metadata?.system) ||
                                   (type === 'custom' && !role.metadata?.system);
                
                return matchesSearch && matchesStatus && matchesType;
            });
            
            renderRoles(filteredRoles);
        }

        // View role details
        async function viewRole(roleId) {
            try {
                const response = await fetch(`/admin/roles/${roleId}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (!response.ok) throw new Error('Failed to load role details');

                const data = await response.json();

                if (data.success) {
                    renderRoleDetails(data.data.role);
                    document.getElementById('viewRoleModal').classList.remove('hidden');
                } else {
                    showError(data.message || 'Failed to load role details');
                }
            } catch (error) {
                console.error('Error loading role details:', error);
                showError('Failed to load role details: ' + error.message);
            }
        }

        // Render role details
        function renderRoleDetails(role) {
            const content = document.getElementById('role-details-content');
            content.innerHTML = `
                <div class="space-y-6">
                    <!-- Basic Info -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-medium text-gray-900 mb-3">Basic Information</h4>
                            <dl class="space-y-2">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Name</dt>
                                    <dd class="text-sm text-gray-900">${role.name}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Display Name</dt>
                                    <dd class="text-sm text-gray-900">${role.display_name}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Description</dt>
                                    <dd class="text-sm text-gray-900">${role.description || 'No description'}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Priority</dt>
                                    <dd class="text-sm text-gray-900">${role.priority}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                                    <dd class="text-sm">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                            role.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                        }">
                                            ${role.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                        ${role.metadata?.system ? '<span class="ml-2 text-xs text-blue-600">System Role</span>' : ''}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        
                        <div>
                            <h4 class="font-medium text-gray-900 mb-3">Statistics</h4>
                            <dl class="space-y-2">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Users with this role</dt>
                                    <dd class="text-sm text-gray-900">${(role.users || []).length}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Permissions count</dt>
                                    <dd class="text-sm text-gray-900">${(role.permissions || []).length}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Created</dt>
                                    <dd class="text-sm text-gray-900">${new Date(role.created_at).toLocaleString()}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Color</dt>
                                    <dd class="text-sm text-gray-900">
                                        <div class="flex items-center">
                                            <div class="w-4 h-4 rounded mr-2" style="background-color: ${role.color || '#3b82f6'}"></div>
                                            ${role.color || '#3b82f6'}
                                        </div>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Users -->
                    ${role.users && role.users.length > 0 ? `
                        <div>
                            <h4 class="font-medium text-gray-900 mb-3">Users with this role</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                ${role.users.map(user => `
                                    <div class="flex items-center p-2 bg-gray-50 rounded">
                                        <img class="h-8 w-8 rounded-full mr-2" 
                                             src="https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&size=32" 
                                             alt="${user.name}">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">${user.name}</div>
                                            <div class="text-xs text-gray-500">${user.email}</div>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    ` : ''}

                    <!-- Permissions -->
                    ${role.permissions && role.permissions.length > 0 ? `
                        <div>
                            <h4 class="font-medium text-gray-900 mb-3">Permissions (${role.permissions.length})</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                ${role.permissions.map(permission => `
                                    <div class="p-2 bg-gray-50 rounded">
                                        <div class="text-sm font-medium text-gray-900">${permission.display_name}</div>
                                        <div class="text-xs text-gray-500">${permission.category || 'general'}</div>
                                        ${permission.description ? `<div class="text-xs text-gray-400 mt-1">${permission.description}</div>` : ''}
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    ` : ''}
                </div>
            `;
        }

        // Edit role
        async function editRole(roleId) {
            try {
                const response = await fetch(`/admin/roles/${roleId}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                if (!response.ok) throw new Error('Failed to load role');
                
                const data = await response.json();
                
                if (data.success) {
                    const role = data.data.role;
                    
                    // Populate form
                    document.getElementById('edit-role-id').value = role.id;
                    document.getElementById('edit-role-display-name').value = role.display_name;
                    document.getElementById('edit-role-description').value = role.description || '';
                    document.getElementById('edit-role-color').value = role.color || '#3b82f6';
                    document.getElementById('edit-role-priority').value = role.priority;
                    document.getElementById('edit-role-status').value = role.is_active ? '1' : '0';
                    
                    // Render permissions with selected ones
                    const selectedPermissions = (role.permissions || []).map(p => p.id);
                    renderPermissions('edit-permissions-container', selectedPermissions);
                    
                    document.getElementById('editRoleModal').classList.remove('hidden');
                } else {
                    showError(data.message || 'Failed to load role');
                }
            } catch (error) {
                console.error('Error loading role:', error);
                showError('Failed to load role details: ' + error.message);
            }
        }

        // Handle create role form submission
        async function handleCreateRole(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const permissions = Array.from(document.querySelectorAll('#permissions-container input[name="permissions[]"]:checked'))
                                    .map(cb => parseInt(cb.value));
            
            const roleData = {
                name: formData.get('name'),
                display_name: formData.get('display_name'),
                description: formData.get('description'),
                color: formData.get('color'),
                priority: parseInt(formData.get('priority')),
                permissions: permissions
            };
            
            try {
                const response = await fetch('/admin/roles', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(roleData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showSuccess('Role created successfully');
                    closeCreateRoleModal();
                    loadRoles();
                    loadStatistics();
                } else {
                    if (result.errors) {
                        const errorMessages = Object.values(result.errors).flat().join(', ');
                        showError(errorMessages);
                    } else {
                        showError(result.message || 'Failed to create role');
                    }
                }
            } catch (error) {
                console.error('Error creating role:', error);
                showError('Failed to create role: ' + error.message);
            }
        }

        // Handle edit role form submission
        async function handleEditRole(event) {
            event.preventDefault();
            
            const roleId = document.getElementById('edit-role-id').value;
            const formData = new FormData(event.target);
            const permissions = Array.from(document.querySelectorAll('#edit-permissions-container input[name="permissions[]"]:checked'))
                                    .map(cb => parseInt(cb.value));
            
            const roleData = {
                display_name: formData.get('display_name'),
                description: formData.get('description'),
                color: formData.get('color'),
                priority: parseInt(formData.get('priority')),
                is_active: formData.get('is_active') === '1',
                permissions: permissions
            };
            
            try {
                const response = await fetch(`/admin/roles/${roleId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(roleData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showSuccess('Role updated successfully');
                    closeEditRoleModal();
                    loadRoles();
                    loadStatistics();
                } else {
                    if (result.errors) {
                        const errorMessages = Object.values(result.errors).flat().join(', ');
                        showError(errorMessages);
                    } else {
                        showError(result.message || 'Failed to update role');
                    }
                }
            } catch (error) {
                console.error('Error updating role:', error);
                showError('Failed to update role: ' + error.message);
            }
        }

        // Delete role
        async function deleteRole(roleId) {
            if (!confirm('Are you sure you want to delete this role? This action cannot be undone.')) {
                return;
            }
            
            try {
                const response = await fetch(`/admin/roles/${roleId}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showSuccess('Role deleted successfully');
                    loadRoles();
                    loadStatistics();
                } else {
                    showError(result.message || 'Failed to delete role');
                }
            } catch (error) {
                console.error('Error deleting role:', error);
                showError('Failed to delete role: ' + error.message);
            }
        }

        // Bulk actions
        function showBulkActionModal() {
            const checkedBoxes = document.querySelectorAll('.role-checkbox:checked');
            if (checkedBoxes.length === 0) {
                showError('Please select at least one role');
                return;
            }

            updateSelectedCount();
            document.getElementById('bulkActionsModal').classList.remove('hidden');
        }

        // Handle bulk actions form submission
        async function handleBulkActions(event) {
            event.preventDefault();

            const formData = new FormData(event.target);
            const checkedBoxes = document.querySelectorAll('.role-checkbox:checked');
            const roleIds = Array.from(checkedBoxes).map(cb => parseInt(cb.value));

            const bulkData = {
                role_ids: roleIds,
                action: formData.get('action')
            };

            if (formData.get('action') === 'sync_permissions') {
                bulkData.template_role_id = formData.get('template_role_id');
                if (!bulkData.template_role_id) {
                    showError('Please select a template role');
                    return;
                }
            }

            try {
                const response = await fetch('/admin/roles/bulk-action', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(bulkData)
                });

                const result = await response.json();

                if (result.success) {
                    showSuccess(result.message || 'Bulk action completed successfully');
                    closeBulkActionsModal();
                    loadRoles();
                    loadStatistics();
                } else {
                    showError(result.message || 'Bulk action failed');
                }
            } catch (error) {
                console.error('Error performing bulk action:', error);
                showError('Bulk action failed: ' + error.message);
            }
        }

        // Update selected count
        function updateSelectedCount() {
            const checkedBoxes = document.querySelectorAll('.role-checkbox:checked');
            const countElement = document.getElementById('selected-roles-count');
            if (countElement) {
                countElement.textContent = checkedBoxes.length;
            }
        }

        // Render pagination
        function renderPagination(pagination) {
            const container = document.getElementById('pagination-container');
            if (!container || !pagination) return;

            const {
                current_page,
                last_page,
                from,
                to,
                total
            } = pagination;

            if (last_page <= 1) {
                container.innerHTML = '';
                return;
            }

            container.innerHTML = `
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Showing ${from} to ${to} of ${total} results
                    </div>
                    <div class="flex space-x-2">
                        ${current_page > 1 ? `
                                <button onclick="loadRoles(${current_page - 1})" 
                                        class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                    Previous
                                </button>
                            ` : ''}
                        ${current_page < last_page ? `
                                <button onclick="loadRoles(${current_page + 1})" 
                                        class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                    Next
                                </button>
                            ` : ''}
                    </div>
                </div>
            `;
        }

        // Modal functions
        function showCreateRoleModal() {
            document.getElementById('createRoleModal').classList.remove('hidden');
            renderPermissions('permissions-container');
        }

        function closeCreateRoleModal() {
            document.getElementById('createRoleModal').classList.add('hidden');
            document.getElementById('createRoleForm').reset();
        }

        function closeEditRoleModal() {
            document.getElementById('editRoleModal').classList.add('hidden');
            document.getElementById('editRoleForm').reset();
        }

        function closeViewRoleModal() {
            document.getElementById('viewRoleModal').classList.add('hidden');
        }

        function closeBulkActionsModal() {
            document.getElementById('bulkActionsModal').classList.add('hidden');
            document.getElementById('bulkActionsForm').reset();
            document.getElementById('sync-permissions-selection').classList.add('hidden');
        }

        // Utility functions
        function refreshRoles() {
            loadRoles();
            loadStatistics();
        }

        function showLoading() {
            document.getElementById('roles-loading').classList.remove('hidden');
            document.getElementById('roles-table').classList.add('hidden');
            document.getElementById('roles-empty').classList.add('hidden');
        }

        function hideLoading() {
            document.getElementById('roles-loading').classList.add('hidden');
            document.getElementById('roles-table').classList.remove('hidden');
        }

        function showEmptyState() {
            document.getElementById('roles-empty').classList.remove('hidden');
            document.getElementById('roles-table').classList.add('hidden');
            document.getElementById('roles-loading').classList.add('hidden');
        }

        function showSuccess(message) {
            // Simple alert for now - you can implement toast notifications later
            alert(message);
        }

        function showError(message) {
            // Simple alert for now - you can implement toast notifications later
            alert('Error: ' + message);
        }

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    </script>
</x-layouts.admin>