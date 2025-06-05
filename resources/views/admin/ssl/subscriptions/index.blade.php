<x-layouts.admin :title="__('All SSL Subscriptions')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-6">
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="xl">{{ __('All SSL Subscriptions') }}</flux:heading>
                    <flux:subheading>{{ __('Manage all user SSL subscriptions and certificates') }}</flux:subheading>
                </div>
                <div class="flex space-x-2">
                    <button onclick="exportSubscriptions()" 
                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center">
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Export
                    </button>
                    <button onclick="refreshSubscriptions()" 
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-2 rounded-lg text-blue-600 bg-blue-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Subscriptions</p>
                        <p class="text-2xl font-bold text-gray-900" id="total-subscriptions-count">Loading...</p>
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
                        <p class="text-sm font-medium text-gray-600">Active Subscriptions</p>
                        <p class="text-2xl font-bold text-gray-900" id="active-subscriptions-count">Loading...</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-2 rounded-lg text-purple-600 bg-purple-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">SSL Certificates</p>
                        <p class="text-2xl font-bold text-gray-900" id="total-certificates-count">Loading...</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-2 rounded-lg text-yellow-600 bg-yellow-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Monthly Revenue</p>
                        <p class="text-2xl font-bold text-gray-900" id="monthly-revenue">Loading...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <input type="text" 
                           id="search-subscriptions" 
                           placeholder="Search users or domains..." 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <select id="filter-status" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="past_due">Past Due</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="suspended">Suspended</option>
                        <option value="paused">Paused</option>
                    </select>
                </div>
                <div>
                    <select id="filter-plan" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All Plans</option>
                        <option value="basic">Basic</option>
                        <option value="professional">Professional</option>
                        <option value="enterprise">Enterprise</option>
                    </select>
                </div>
                <div>
                    <select id="filter-provider" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All Providers</option>
                        <option value="gogetssl">GoGetSSL</option>
                        <option value="google_certificate_manager">Google CM</option>
                        <option value="lets_encrypt">Let's Encrypt</option>
                    </select>
                </div>
                <div>
                    <button onclick="clearFilters()" 
                            class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-md">
                        Clear Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Subscriptions Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Subscription Management</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200" id="subscriptions-table">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <input type="checkbox" id="select-all-subscriptions" class="rounded">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Certificates</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Provider</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Next Billing</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="subscriptions-tbody">
                        <!-- Subscriptions will be loaded here -->
                    </tbody>
                </table>
            </div>

            <!-- Loading State -->
            <div id="subscriptions-loading" class="p-8 text-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                <p class="text-gray-500 mt-2">Loading subscriptions...</p>
            </div>

            <!-- Empty State -->
            <div id="subscriptions-empty" class="p-8 text-center hidden">
                <svg class="h-12 w-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No subscriptions found</h3>
                <p class="text-gray-500">No subscriptions match your current filters.</p>
            </div>
        </div>

        <!-- Pagination -->
        <div id="pagination-container" class="mt-6"></div>
    </div>

    <!-- Subscription Details Modal -->
    <div id="subscriptionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-2/3 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Subscription Details</h3>
                    <button onclick="closeSubscriptionModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>
                
                <div id="subscription-details-content">
                    <!-- Subscription details will be loaded here -->
                </div>
                
                <div class="flex justify-end mt-6">
                    <button onclick="closeSubscriptionModal()" 
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
        let allSubscriptions = [];
        let filteredSubscriptions = [];

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            loadSubscriptions();
            loadStatistics();
            setupEventListeners();
        });

        function setupEventListeners() {
            // Search and filter functionality
            document.getElementById('search-subscriptions').addEventListener('input', debounce(filterSubscriptions, 300));
            document.getElementById('filter-status').addEventListener('change', filterSubscriptions);
            document.getElementById('filter-plan').addEventListener('change', filterSubscriptions);
            document.getElementById('filter-provider').addEventListener('change', filterSubscriptions);
            
            // Select all functionality
            document.getElementById('select-all-subscriptions').addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.subscription-checkbox');
                checkboxes.forEach(cb => cb.checked = this.checked);
            });
        }

        // Load subscriptions
        async function loadSubscriptions(page = 1) {
            try {
                showLoading();
                const response = await fetch(`/admin/ssl/subscriptions/data?page=${page}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (!response.ok) throw new Error('Failed to load subscriptions');

                const data = await response.json();
                if (data.success) {
                    allSubscriptions = data.data.subscriptions || [];
                    filteredSubscriptions = [...allSubscriptions];
                    renderSubscriptions(filteredSubscriptions);
                    
                    if (data.data.pagination) {
                        renderPagination(data.data.pagination);
                    }
                }
                hideLoading();
            } catch (error) {
                console.error('Error loading subscriptions:', error);
                showError('Failed to load subscriptions: ' + error.message);
                hideLoading();
                showEmptyState();
            }
        }

        // Load statistics
        async function loadStatistics() {
            try {
                const response = await fetch('/admin/ssl/subscriptions/statistics', {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (!response.ok) throw new Error('Failed to load statistics');

                const data = await response.json();
                const stats = data.data;
                
                document.getElementById('total-subscriptions-count').textContent = stats.total_subscriptions || 0;
                document.getElementById('active-subscriptions-count').textContent = stats.active_subscriptions || 0;
                document.getElementById('total-certificates-count').textContent = stats.total_certificates || 0;
                document.getElementById('monthly-revenue').textContent = '$' + (stats.monthly_revenue || 0).toFixed(2);
            } catch (error) {
                console.error('Error loading statistics:', error);
                // Set default values
                document.getElementById('total-subscriptions-count').textContent = '0';
                document.getElementById('active-subscriptions-count').textContent = '0';
                document.getElementById('total-certificates-count').textContent = '0';
                document.getElementById('monthly-revenue').textContent = '$0.00';
            }
        }

        // Render subscriptions table
        function renderSubscriptions(subscriptions) {
            const tbody = document.getElementById('subscriptions-tbody');
            
            if (!Array.isArray(subscriptions) || subscriptions.length === 0) {
                showEmptyState();
                return;
            }
            
            tbody.innerHTML = subscriptions.map(subscription => `
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <input type="checkbox" class="subscription-checkbox rounded" value="${subscription.id}">
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <img class="h-8 w-8 rounded-full" 
                                 src="https://ui-avatars.com/api/?name=${encodeURIComponent(subscription.user.name)}&size=32" 
                                 alt="${subscription.user.name}">
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">${subscription.user.name}</div>
                                <div class="text-sm text-gray-500">${subscription.user.email}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getPlanColor(subscription.plan_type)}">
                            ${subscription.plan_type.charAt(0).toUpperCase() + subscription.plan_type.slice(1)}
                        </span>
                        <div class="text-xs text-gray-500 mt-1">${subscription.certificate_type}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(subscription.status)}">
                            ${getStatusDisplayName(subscription.status)}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <div>${subscription.certificates_count || 0} / ${subscription.max_domains}</div>
                        <div class="text-xs text-gray-500">${subscription.certificates_issued || 0} issued</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${subscription.provider || 'Default'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        $${(subscription.price / 100).toFixed(2)}
                        <div class="text-xs text-gray-500">${subscription.billing_period}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${subscription.next_billing_date ? new Date(subscription.next_billing_date).toLocaleDateString() : 'N/A'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <div class="flex space-x-2">
                            <button onclick="viewSubscription(${subscription.id})" 
                                    class="text-blue-600 hover:text-blue-900">View</button>
                            <button onclick="manageSubscription(${subscription.id})" 
                                    class="text-green-600 hover:text-green-900">Manage</button>
                        </div>
                    </td>
                </tr>
            `).join('');

            document.getElementById('subscriptions-table').classList.remove('hidden');
            document.getElementById('subscriptions-empty').classList.add('hidden');
        }

        // Filter subscriptions
        function filterSubscriptions() {
            const search = document.getElementById('search-subscriptions').value.toLowerCase();
            const status = document.getElementById('filter-status').value;
            const plan = document.getElementById('filter-plan').value;
            const provider = document.getElementById('filter-provider').value;
            
            filteredSubscriptions = allSubscriptions.filter(subscription => {
                const matchesSearch = !search || 
                    subscription.user.name.toLowerCase().includes(search) ||
                    subscription.user.email.toLowerCase().includes(search) ||
                    (subscription.domains || []).some(domain => domain.toLowerCase().includes(search));
                
                const matchesStatus = !status || subscription.status === status;
                const matchesPlan = !plan || subscription.plan_type === plan;
                const matchesProvider = !provider || subscription.provider === provider;
                
                return matchesSearch && matchesStatus && matchesPlan && matchesProvider;
            });
            
            renderSubscriptions(filteredSubscriptions);
        }

        // View subscription details
        async function viewSubscription(subscriptionId) {
            try {
                const response = await fetch(`/admin/ssl/subscriptions/${subscriptionId}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (!response.ok) throw new Error('Failed to load subscription details');

                const data = await response.json();
                if (data.success) {
                    renderSubscriptionDetails(data.data);
                    document.getElementById('subscriptionModal').classList.remove('hidden');
                }
            } catch (error) {
                console.error('Error loading subscription details:', error);
                showError('Failed to load subscription details: ' + error.message);
            }
        }

        // Render subscription details
        function renderSubscriptionDetails(subscription) {
            const content = document.getElementById('subscription-details-content');
            content.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="font-medium text-gray-900 mb-3">Subscription Information</h4>
                        <dl class="space-y-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">User</dt>
                                <dd class="text-sm text-gray-900">${subscription.user.name} (${subscription.user.email})</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Plan</dt>
                                <dd class="text-sm text-gray-900">${subscription.plan_type} - ${subscription.certificate_type}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Status</dt>
                                <dd class="text-sm">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(subscription.status)}">
                                        ${getStatusDisplayName(subscription.status)}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Provider</dt>
                                <dd class="text-sm text-gray-900">${subscription.provider || 'Default'}</dd>
                            </div>
                        </dl>
                    </div>
                    
                    <div>
                        <h4 class="font-medium text-gray-900 mb-3">Billing Information</h4>
                        <dl class="space-y-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Price</dt>
                                <dd class="text-sm text-gray-900">$${(subscription.price / 100).toFixed(2)} ${subscription.billing_period}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Next Billing</dt>
                                <dd class="text-sm text-gray-900">${subscription.next_billing_date ? new Date(subscription.next_billing_date).toLocaleDateString() : 'N/A'}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Created</dt>
                                <dd class="text-sm text-gray-900">${new Date(subscription.created_at).toLocaleDateString()}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
                
                <div class="mt-6">
                    <h4 class="font-medium text-gray-900 mb-3">Certificates (${subscription.certificates ? subscription.certificates.length : 0})</h4>
                    ${subscription.certificates && subscription.certificates.length > 0 ? `
                        <div class="space-y-2">
                            ${subscription.certificates.map(cert => `
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">${cert.domain}</div>
                                        <div class="text-xs text-gray-500">${cert.status} - Expires: ${cert.expires_at ? new Date(cert.expires_at).toLocaleDateString() : 'N/A'}</div>
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getCertificateStatusColor(cert.status)}">
                                        ${cert.status}
                                    </span>
                                </div>
                            `).join('')}
                        </div>
                    ` : '<p class="text-sm text-gray-500">No certificates found</p>'}
                </div>
            `;
        }

        // Utility functions
        function getPlanColor(plan) {
            switch (plan) {
                case 'basic': return 'bg-blue-100 text-blue-800';
                case 'professional': return 'bg-green-100 text-green-800';
                case 'enterprise': return 'bg-purple-100 text-purple-800';
                default: return 'bg-gray-100 text-gray-800';
            }
        }

        function getStatusColor(status) {
            switch (status) {
                case 'active': return 'bg-green-100 text-green-800';
                case 'past_due': return 'bg-yellow-100 text-yellow-800';
                case 'suspended': return 'bg-red-100 text-red-800';
                case 'cancelled': return 'bg-gray-100 text-gray-800';
                case 'paused': return 'bg-blue-100 text-blue-800';
                default: return 'bg-gray-100 text-gray-800';
            }
        }

        function getCertificateStatusColor(status) {
            switch (status) {
                case 'issued': return 'bg-green-100 text-green-800';
                case 'pending_validation': return 'bg-yellow-100 text-yellow-800';
                case 'failed': return 'bg-red-100 text-red-800';
                case 'expired': return 'bg-gray-100 text-gray-800';
                default: return 'bg-gray-100 text-gray-800';
            }
        }

        function getStatusDisplayName(status) {
            switch (status) {
                case 'active': return 'Active';
                case 'past_due': return 'Past Due';
                case 'suspended': return 'Suspended';
                case 'cancelled': return 'Cancelled';
                case 'paused': return 'Paused';
                default: return status;
            }
        }

        function clearFilters() {
            document.getElementById('search-subscriptions').value = '';
            document.getElementById('filter-status').value = '';
            document.getElementById('filter-plan').value = '';
            document.getElementById('filter-provider').value = '';
            filterSubscriptions();
        }

        function refreshSubscriptions() {
            loadSubscriptions();
            loadStatistics();
        }

        function exportSubscriptions() {
            window.open('/admin/ssl/subscriptions/export', '_blank');
        }

        function manageSubscription(subscriptionId) {
            window.open(`/ssl/subscriptions/${subscriptionId}`, '_blank');
        }

        function closeSubscriptionModal() {
            document.getElementById('subscriptionModal').classList.add('hidden');
        }

        function showLoading() {
            document.getElementById('subscriptions-loading').classList.remove('hidden');
            document.getElementById('subscriptions-table').classList.add('hidden');
            document.getElementById('subscriptions-empty').classList.add('hidden');
        }

        function hideLoading() {
            document.getElementById('subscriptions-loading').classList.add('hidden');
            document.getElementById('subscriptions-table').classList.remove('hidden');
        }

        function showEmptyState() {
            document.getElementById('subscriptions-empty').classList.remove('hidden');
            document.getElementById('subscriptions-table').classList.add('hidden');
            document.getElementById('subscriptions-loading').classList.add('hidden');
        }

        function showError(message) {
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

        function renderPagination(pagination) {
            const container = document.getElementById('pagination-container');
            if (!container || !pagination) return;

            const { current_page, last_page, from, to, total } = pagination;

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
                            <button onclick="loadSubscriptions(${current_page - 1})" 
                                    class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                Previous
                            </button>
                        ` : ''}
                        ${current_page < last_page ? `
                            <button onclick="loadSubscriptions(${current_page + 1})" 
                                    class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                Next
                            </button>
                        ` : ''}
                    </div>
                </div>
            `;
        }
    </script>
</x-layouts.admin>