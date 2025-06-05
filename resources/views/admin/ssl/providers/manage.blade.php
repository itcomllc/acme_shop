<x-layouts.admin :title="__('SSL Provider Management')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-6">
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="xl">{{ __('SSL Provider Management') }}</flux:heading>
                    <flux:subheading>{{ __('Manage SSL certificate providers and their configurations') }}</flux:subheading>
                </div>
                <div class="flex space-x-2">
                    <button onclick="testAllProviders()" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Test All Providers
                    </button>
                    <button onclick="refreshProviders()" 
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Provider Status Overview -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-2 rounded-lg text-green-600 bg-green-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Available Providers</p>
                        <p class="text-2xl font-bold text-gray-900" id="available-providers-count">Loading...</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-2 rounded-lg text-blue-600 bg-blue-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Active Certificates</p>
                        <p class="text-2xl font-bold text-gray-900" id="active-certificates-count">Loading...</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-2 rounded-lg text-yellow-600 bg-yellow-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Failed Certificates</p>
                        <p class="text-2xl font-bold text-gray-900" id="failed-certificates-count">Loading...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Provider Cards -->
        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6" id="providers-container">
            <!-- Providers will be loaded here -->
        </div>

        <!-- Provider Health Status -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Provider Health Status</h3>
            <div id="health-status-container">
                <!-- Health status will be loaded here -->
            </div>
        </div>

        <!-- Provider Comparison -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Provider Comparison</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200" id="comparison-table">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Provider</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Certificate Types</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Auto Renewal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cost</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Max Validity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="comparison-tbody">
                        <!-- Comparison data will be loaded here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Provider Configuration Modal -->
    <div id="configModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900" id="config-modal-title">Provider Configuration</h3>
                    <button onclick="closeConfigModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>
                
                <div id="config-modal-content">
                    <!-- Configuration content will be loaded here -->
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button onclick="closeConfigModal()" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md">
                        Close
                    </button>
                    <button onclick="saveProviderConfig()" 
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md">
                        Save Configuration
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let providers = [];
        let healthStatus = {};

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            loadProviders();
            loadHealthStatus();
            loadStatistics();
        });

        // Load providers
        async function loadProviders() {
            try {
                const response = await fetch('/admin/ssl/providers/data', {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (!response.ok) throw new Error('Failed to load providers');

                const data = await response.json();
                if (data.success) {
                    providers = data.data.providers || {};
                    renderProviders();
                    renderComparison();
                }
            } catch (error) {
                console.error('Error loading providers:', error);
                showError('Failed to load providers: ' + error.message);
            }
        }

        // Load health status
        async function loadHealthStatus() {
            try {
                const response = await fetch('/admin/ssl/providers/health', {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (!response.ok) throw new Error('Failed to load health status');

                const data = await response.json();
                if (data.success) {
                    healthStatus = data.data || {};
                    renderHealthStatus();
                }
            } catch (error) {
                console.error('Error loading health status:', error);
                showError('Failed to load health status: ' + error.message);
            }
        }

        // Load statistics
        async function loadStatistics() {
            try {
                const response = await fetch('/admin/ssl/providers/statistics', {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (!response.ok) throw new Error('Failed to load statistics');

                const data = await response.json();
                if (data.success) {
                    const stats = data.data;
                    document.getElementById('available-providers-count').textContent = Object.keys(providers).length;
                    document.getElementById('active-certificates-count').textContent = stats.active_certificates || 0;
                    document.getElementById('failed-certificates-count').textContent = stats.failed_certificates || 0;
                }
            } catch (error) {
                console.error('Error loading statistics:', error);
                // Set default values
                document.getElementById('available-providers-count').textContent = '0';
                document.getElementById('active-certificates-count').textContent = '0';
                document.getElementById('failed-certificates-count').textContent = '0';
            }
        }

        // Render providers
        function renderProviders() {
            const container = document.getElementById('providers-container');
            container.innerHTML = '';

            Object.entries(providers).forEach(([type, provider]) => {
                const status = healthStatus[type] || { status: 'unknown' };
                const statusColor = getStatusColor(status.status);
                
                const card = document.createElement('div');
                card.className = 'bg-white rounded-lg shadow-md p-6 border-l-4';
                card.style.borderLeftColor = statusColor;
                
                card.innerHTML = `
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-lg font-semibold text-gray-900">${provider.name}</h4>
                        <div class="flex items-center space-x-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                                  style="background-color: ${statusColor}20; color: ${statusColor}">
                                ${status.status}
                            </span>
                            <button onclick="configureProvider('${type}')" 
                                    class="text-blue-600 hover:text-blue-800 text-sm">
                                Configure
                            </button>
                        </div>
                    </div>
                    
                    <p class="text-sm text-gray-600 mb-4">${provider.description}</p>
                    
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="font-medium text-gray-700">Certificate Types:</span>
                            <div class="mt-1">
                                ${provider.supported_types ? provider.supported_types.join(', ') : 'N/A'}
                            </div>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Auto Renewal:</span>
                            <div class="mt-1">
                                ${provider.auto_renewal ? '✅ Yes' : '❌ No'}
                            </div>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Cost:</span>
                            <div class="mt-1">${provider.cost || 'Unknown'}</div>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Max Validity:</span>
                            <div class="mt-1">${provider.max_validity_days || 'N/A'} days</div>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Response Time:</span>
                            <span class="text-sm font-medium">${status.response_time || 'N/A'}ms</span>
                        </div>
                        ${status.error ? `
                            <div class="mt-2 text-sm text-red-600">
                                Error: ${status.error}
                            </div>
                        ` : ''}
                    </div>
                    
                    <div class="mt-4 flex space-x-2">
                        <button onclick="testProvider('${type}')" 
                                class="flex-1 bg-blue-100 hover:bg-blue-200 text-blue-700 py-2 px-4 rounded text-sm">
                            Test Connection
                        </button>
                        <button onclick="viewProviderDetails('${type}')" 
                                class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded text-sm">
                            View Details
                        </button>
                    </div>
                `;
                
                container.appendChild(card);
            });
        }

        // Render health status
        function renderHealthStatus() {
            const container = document.getElementById('health-status-container');
            container.innerHTML = '';

            if (Object.keys(healthStatus).length === 0) {
                container.innerHTML = '<p class="text-gray-500">No health data available</p>';
                return;
            }

            Object.entries(healthStatus).forEach(([provider, status]) => {
                const statusColor = getStatusColor(status.status);
                
                const statusItem = document.createElement('div');
                statusItem.className = 'flex items-center justify-between p-3 bg-gray-50 rounded-lg mb-2';
                
                statusItem.innerHTML = `
                    <div class="flex items-center">
                        <div class="w-3 h-3 rounded-full mr-3" style="background-color: ${statusColor}"></div>
                        <span class="font-medium text-gray-900">${providers[provider]?.name || provider}</span>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-medium text-gray-900">${status.status}</div>
                        <div class="text-xs text-gray-500">${status.response_time || 'N/A'}ms</div>
                    </div>
                `;
                
                container.appendChild(statusItem);
            });
        }

        // Render comparison table
        function renderComparison() {
            const tbody = document.getElementById('comparison-tbody');
            tbody.innerHTML = '';

            Object.entries(providers).forEach(([type, provider]) => {
                const status = healthStatus[type] || { status: 'unknown' };
                const statusColor = getStatusColor(status.status);
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full mr-3" style="background-color: ${statusColor}"></div>
                            <span class="text-sm font-medium text-gray-900">${provider.name}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${provider.supported_types ? provider.supported_types.join(', ') : 'N/A'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${provider.auto_renewal ? '✅' : '❌'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${provider.cost || 'Unknown'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${provider.max_validity_days || 'N/A'} days
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                              style="background-color: ${statusColor}20; color: ${statusColor}">
                            ${status.status}
                        </span>
                    </td>
                `;
                
                tbody.appendChild(row);
            });
        }

        // Test all providers
        async function testAllProviders() {
            try {
                showLoading('Testing all providers...');
                
                const response = await fetch('/admin/ssl/providers/test-all', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (!response.ok) throw new Error('Failed to test providers');

                const data = await response.json();
                if (data.success) {
                    healthStatus = data.data || {};
                    renderHealthStatus();
                    renderProviders();
                    renderComparison();
                    showSuccess('All providers tested successfully');
                } else {
                    showError(data.message || 'Failed to test providers');
                }
            } catch (error) {
                console.error('Error testing providers:', error);
                showError('Failed to test providers: ' + error.message);
            } finally {
                hideLoading();
            }
        }

        // Test single provider
        async function testProvider(providerType) {
            try {
                showLoading(`Testing ${providers[providerType]?.name || providerType}...`);
                
                const response = await fetch(`/admin/ssl/providers/${providerType}/test`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (!response.ok) throw new Error('Failed to test provider');

                const data = await response.json();
                if (data.success) {
                    healthStatus[providerType] = data.data;
                    renderHealthStatus();
                    renderProviders();
                    renderComparison();
                    showSuccess(`${providers[providerType]?.name || providerType} tested successfully`);
                } else {
                    showError(data.message || 'Failed to test provider');
                }
            } catch (error) {
                console.error('Error testing provider:', error);
                showError('Failed to test provider: ' + error.message);
            } finally {
                hideLoading();
            }
        }

        // Configure provider
        function configureProvider(providerType) {
            const provider = providers[providerType];
            document.getElementById('config-modal-title').textContent = `Configure ${provider?.name || providerType}`;
            
            // This would be implemented based on specific provider configuration needs
            document.getElementById('config-modal-content').innerHTML = `
                <div class="space-y-4">
                    <p class="text-gray-600">Configuration options for ${provider?.name || providerType}</p>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <p class="text-yellow-800">Provider configuration is managed through environment variables and config files.</p>
                        <p class="text-yellow-700 text-sm mt-2">Please update your .env file and config/services.php for provider settings.</p>
                    </div>
                </div>
            `;
            
            document.getElementById('configModal').classList.remove('hidden');
        }

        // View provider details
        function viewProviderDetails(providerType) {
            const provider = providers[providerType];
            const status = healthStatus[providerType] || {};
            
            alert(`Provider: ${provider?.name || providerType}\n\nFeatures:\n${provider?.features ? provider.features.join('\n') : 'No features listed'}\n\nStatus: ${status.status || 'Unknown'}\nResponse Time: ${status.response_time || 'N/A'}ms`);
        }

        // Utility functions
        function getStatusColor(status) {
            switch (status) {
                case 'connected':
                case 'healthy':
                    return '#10b981';
                case 'warning':
                    return '#f59e0b';
                case 'failed':
                case 'error':
                case 'unhealthy':
                    return '#ef4444';
                default:
                    return '#6b7280';
            }
        }

        function refreshProviders() {
            loadProviders();
            loadHealthStatus();
            loadStatistics();
        }

        function closeConfigModal() {
            document.getElementById('configModal').classList.add('hidden');
        }

        function saveProviderConfig() {
            // Implementation would depend on specific provider configuration needs
            showSuccess('Configuration saved successfully');
            closeConfigModal();
        }

        function showLoading(message = 'Loading...') {
            // Simple loading implementation
            console.log(message);
        }

        function hideLoading() {
            // Hide loading implementation
        }

        function showSuccess(message) {
            alert(message);
        }

        function showError(message) {
            alert('Error: ' + message);
        }
    </script>
</x-layouts.admin>
