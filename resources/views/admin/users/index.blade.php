<x-layouts.admin :title="__('User Management')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-6">
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="xl">{{ __('User Management') }}</flux:heading>
                    <flux:subheading>{{ __('Manage user accounts and permissions') }}</flux:subheading>
                </div>
                <button onclick="showCreateUserModal()"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Create User
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-2 rounded-lg text-blue-600 bg-blue-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z">
                            </path>
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
                    <div class="p-2 rounded-lg text-green-600 bg-green-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Verified Users</p>
                        <p class="text-2xl font-bold text-gray-900" id="verified-users-count">Loading...</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-2 rounded-lg text-purple-600 bg-purple-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z">
                            </path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">With Subscriptions</p>
                        <p class="text-2xl font-bold text-gray-900" id="subscription-users-count">Loading...</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-2 rounded-lg text-yellow-600 bg-yellow-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">New This Month</p>
                        <p class="text-2xl font-bold text-gray-900" id="new-users-count">Loading...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
                <div class="flex items-center space-x-4">
                    <input type="text" id="search-users" placeholder="Search users..."
                        class="border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <select id="filter-role"
                        class="border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All Roles</option>
                        <!-- Roles will be loaded here -->
                    </select>
                    <select id="filter-status"
                        class="border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All Users</option>
                        <option value="verified">Verified</option>
                        <option value="unverified">Unverified</option>
                        <option value="active_subscription">Active Subscription</option>
                    </select>
                </div>
                <div class="flex items-center space-x-2">
                    <button onclick="bulkActionModal()"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg flex items-center">
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                        Bulk Actions
                    </button>
                    <button onclick="refreshUsers()"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                            </path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">System Users</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200" id="users-table">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <input type="checkbox" id="select-all-users" class="rounded">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Subscriptions</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Created</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="users-tbody">
                        <!-- Users will be loaded here via AJAX -->
                    </tbody>
                </table>
            </div>

            <!-- Loading State -->
            <div id="users-loading" class="p-8 text-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                <p class="text-gray-500 mt-2">Loading users...</p>
            </div>

            <!-- Empty State -->
            <div id="users-empty" class="p-8 text-center hidden">
                <svg class="h-12 w-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z">
                    </path>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No users found</h3>
                <p class="text-gray-500">Create your first user to get started.</p>
            </div>
        </div>

        <!-- Pagination -->
        <div id="pagination-container" class="mt-6"></div>
    </div>

    <!-- Create User Modal -->
    <div id="createUserModal"
        class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Create New User</h3>
                    <button onclick="closeCreateUserModal()"
                        class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>

                <form id="createUserForm" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Name</label>
                            <input type="text" name="name" id="user-name" placeholder="Full name"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" name="email" id="user-email" placeholder="user@example.com"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                            <input type="password" name="password" id="user-password" placeholder="Password"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
                            <input type="password" name="password_confirmation" id="user-password-confirmation"
                                placeholder="Confirm password"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Primary Role</label>
                            <select name="role_id" id="user-role"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required>
                                <option value="">Select a role</option>
                                <!-- Roles will be loaded here -->
                            </select>
                        </div>
                        <div>
                            <label class="flex items-center mt-6">
                                <input type="checkbox" name="email_verified" id="user-email-verified"
                                    class="rounded mr-2">
                                <span class="text-sm text-gray-900">Email verified</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeCreateUserModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md">
                            Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- User Details Modal -->
    <div id="userDetailsModal"
        class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-2/3 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">User Details</h3>
                    <button onclick="closeUserDetailsModal()"
                        class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>

                <div id="user-details-content">
                    <!-- User details will be loaded here -->
                </div>

                <div class="flex justify-end mt-6">
                    <button onclick="closeUserDetailsModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let currentPage = 1;
        let allUsers = [];
        let allRoles = [];

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            loadUsers();
            loadRoles();
            loadStatistics();

            // Setup event listeners
            setupEventListeners();
        });

        function setupEventListeners() {
            // Search functionality
            document.getElementById('search-users').addEventListener('input', debounce(filterUsers, 300));
            document.getElementById('filter-role').addEventListener('change', filterUsers);
            document.getElementById('filter-status').addEventListener('change', filterUsers);

            // Select all functionality
            document.getElementById('select-all-users').addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.user-checkbox');
                checkboxes.forEach(cb => cb.checked = this.checked);
            });

            // Form submissions
            document.getElementById('createUserForm').addEventListener('submit', handleCreateUser);
        }

        // Load users data
        async function loadUsers(page = 1) {
            try {
                showLoading();
                const response = await fetch(`/admin/users?page=${page}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Failed to load users');
                }

                // Check if data is an array
                if (Array.isArray(data.data)) {
                    allUsers = data.data;
                } else {
                    console.error('Expected array but got:', typeof data.data, data.data);
                    throw new Error('Invalid data format received from server');
                }

                renderUsers(allUsers);

                // Handle pagination if present
                if (data.pagination) {
                    renderPagination(data.pagination);
                }

                hideLoading();
            } catch (error) {
                console.error('Error loading users:', error);
                showError('Failed to load users: ' + error.message);
                hideLoading();
                showEmptyState();
            }
        }

        // Load roles data
        async function loadRoles() {
            try {
                const response = await fetch('/admin/roles', {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Failed to load roles');
                }

                // Handle different response structures
                if (Array.isArray(data.data)) {
                    allRoles = data.data;
                } else if (data.data && Array.isArray(data.data.data)) {
                    allRoles = data.data.data;
                } else if (data.data && data.data.roles && Array.isArray(data.data.roles.data)) {
                    allRoles = data.data.roles.data;
                } else {
                    console.error('Unexpected roles data structure:', data.data);
                    allRoles = [];
                }

                populateRoleSelects();
            } catch (error) {
                console.error('Error loading roles:', error);
                allRoles = [];
                populateRoleSelects(); // Still populate with empty array
            }
        }

        // Load statistics
        async function loadStatistics() {
            try {
                const response = await fetch('/admin/users-statistics', {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Failed to load statistics');
                }

                const stats = data.data;

                document.getElementById('total-users-count').textContent = stats.total_users || 0;
                document.getElementById('verified-users-count').textContent = stats.verified_users || 0;
                document.getElementById('subscription-users-count').textContent = stats.users_with_subscriptions || 0;
                document.getElementById('new-users-count').textContent = stats.recent_registrations || 0;
            } catch (error) {
                console.error('Error loading statistics:', error);
                // Set default values on error
                document.getElementById('total-users-count').textContent = '0';
                document.getElementById('verified-users-count').textContent = '0';
                document.getElementById('subscription-users-count').textContent = '0';
                document.getElementById('new-users-count').textContent = '0';
            }
        }

        // Populate role select options
        function populateRoleSelects() {
            const filterSelect = document.getElementById('filter-role');
            const createUserSelect = document.getElementById('user-role');

            // Clear existing options (except the first default option)
            filterSelect.innerHTML = '<option value="">All Roles</option>';
            createUserSelect.innerHTML = '<option value="">Select a role</option>';

            if (Array.isArray(allRoles) && allRoles.length > 0) {
                allRoles.forEach(role => {
                    // Filter dropdown
                    const filterOption = document.createElement('option');
                    filterOption.value = role.name;
                    filterOption.textContent = role.display_name;
                    filterSelect.appendChild(filterOption);

                    // Create user dropdown
                    const createOption = document.createElement('option');
                    createOption.value = role.id;
                    createOption.textContent = role.display_name;
                    createUserSelect.appendChild(createOption);
                });
            }
        }

        // Render users table
        function renderUsers(users) {
            const tbody = document.getElementById('users-tbody');

            if (!Array.isArray(users) || users.length === 0) {
                showEmptyState();
                return;
            }

            tbody.innerHTML = users.map(user => `
        <tr class="hover:bg-gray-50">
            <td class="px-6 py-4 whitespace-nowrap">
                <input type="checkbox" class="user-checkbox rounded" value="${user.id}">
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                    <img class="h-10 w-10 rounded-full border" 
                         src="https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&color=7F9CF5&background=EBF4FF" 
                         alt="${user.name}">
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-900">${user.name}</div>
                        <div class="text-sm text-gray-500">${user.email}</div>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex flex-col">
                    ${user.primary_role ? `
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                                  style="background-color: ${user.primary_role.color}20; color: ${user.primary_role.color}">
                                ${user.primary_role.display_name}
                            </span>
                        ` : '<span class="text-sm text-gray-400">No role assigned</span>'}
                    ${user.roles_count > 1 ? `<span class="text-xs text-gray-500 mt-1">+${user.roles_count - 1} more</span>` : ''}
                </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex flex-col space-y-1">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                        user.email_verified_at ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                    }">
                        ${user.email_verified_at ? 'Verified' : 'Unverified'}
                    </span>
                    ${user.subscriptions_count > 0 ? `
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                ${user.subscriptions_count} subscription${user.subscriptions_count > 1 ? 's' : ''}
                            </span>
                        ` : ''}
                </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                ${user.subscriptions_count || 0}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                ${new Date(user.created_at).toLocaleDateString()}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <div class="flex space-x-2">
                    <button onclick="viewUser(${user.id})" 
                            class="text-blue-600 hover:text-blue-900">View</button>
                    <button onclick="editUser(${user.id})" 
                            class="text-green-600 hover:text-green-900">Edit</button>
                    ${user.id !== 1 ? `
                            <button onclick="deleteUser(${user.id})" 
                                    class="text-red-600 hover:text-red-900">Delete</button>
                        ` : ''}
                </div>
            </td>
        </tr>
    `).join('');

            // Make sure table is visible
            document.getElementById('users-table').classList.remove('hidden');
            document.getElementById('users-empty').classList.add('hidden');
        }

        // Utility functions
        function showLoading() {
            document.getElementById('users-loading').classList.remove('hidden');
            document.getElementById('users-table').classList.add('hidden');
            document.getElementById('users-empty').classList.add('hidden');
        }

        function hideLoading() {
            document.getElementById('users-loading').classList.add('hidden');
            document.getElementById('users-table').classList.remove('hidden');
        }

        function showEmptyState() {
            document.getElementById('users-empty').classList.remove('hidden');
            document.getElementById('users-table').classList.add('hidden');
            document.getElementById('users-loading').classList.add('hidden');
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
                        <button onclick="loadUsers(${current_page - 1})" 
                                class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                            Previous
                        </button>
                    ` : ''}
                ${current_page < last_page ? `
                        <button onclick="loadUsers(${current_page + 1})" 
                                class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                            Next
                        </button>
                    ` : ''}
            </div>
        </div>
    `;
        }

        // Placeholder functions for other operations
        function filterUsers() {
            // Implement filtering logic
            console.log('Filter users');
        }

        function refreshUsers() {
            loadUsers();
            loadStatistics();
        }

        function viewUser(userId) {
            console.log('View user:', userId);
        }

        function editUser(userId) {
            console.log('Edit user:', userId);
        }

        function deleteUser(userId) {
            console.log('Delete user:', userId);
        }

        function handleCreateUser(event) {
            event.preventDefault();
            console.log('Create user');
        }

        function bulkActionModal() {
            console.log('Bulk actions');
        }
    </script>
</x-layouts.admin>
