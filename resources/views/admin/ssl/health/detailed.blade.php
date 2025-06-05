<x-layouts.admin :title="__('SSL System Health')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl p-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ __('SSL System Health') }}</flux:heading>
                <flux:subheading>{{ __('Comprehensive system monitoring and diagnostics') }}</flux:subheading>
            </div>
            <div class="flex space-x-2">
                <button onclick="refreshHealthStatus()" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Refresh
                </button>
                <button onclick="runDiagnostics()" 
                        class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg flex items-center">
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                    </svg>
                    Run Diagnostics
                </button>
            </div>
        </div>

        <!-- System Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
            <!-- Overall Health -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-2 rounded-lg" id="overall-health-icon">
                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Overall Health</p>
                        <p class="text-lg font-bold" id="overall-health-status">Loading...</p>
                    </div>
                </div>
            </div>

            <!-- Provider Status -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-2 rounded-lg text-blue-600 bg-blue-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">SSL Providers</p>
                        <p class="text-lg font-bold text-gray-900" id="provider-status">Loading...</p>
                    </div>
                </div>
            </div>

            <!-- Active Certificates -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-2 rounded-lg text-green-600 bg-green-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Active Certificates</p>
                        <p class="text-lg font-bold text-gray-900" id="active-certificates">Loading...</p>
                    </div>
                </div>
            </div>

            <!-- System Uptime -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-2 rounded-lg text-purple-600 bg-purple-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">System Uptime</p>
                        <p class="text-lg font-bold text-gray-900" id="system-uptime">Loading...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <!-- SSL Provider Health -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">SSL Provider Health</h2>
                    <button onclick="refreshProviderHealth()" class="text-blue-600 hover:text-blue-800">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </button>
                </div>
                <div class="p-6">
                    <div id="provider-health-content" class="space-y-4">
                        <!-- Provider health items will be loaded here -->
                        <div class="animate-pulse">
                            <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                            <div class="h-4 bg-gray-200 rounded w-1/2"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Performance -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">System Performance</h2>
                </div>
                <div class="p-6">
                    <div id="performance-metrics" class="space-y-4">
                        <!-- Performance metrics will be loaded here -->
                        <div class="animate-pulse">
                            <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                            <div class="h-4 bg-gray-200 rounded w-1/2"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Certificate Status Distribution -->
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <!-- Certificate Status Chart -->
            <div class="xl:col-span-2 bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Certificate Status Distribution</h2>
                </div>
                <div class="p-6">
                    <canvas id="certificateStatusChart" height="300"></canvas>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Quick Actions</h2>
                </div>
                <div class="p-6 space-y-3">
                    <button onclick="checkExpiring()" 
                            class="w-full flex items-center p-3 bg-yellow-50 hover:bg-yellow-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 15.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        <div class="text-left">
                            <h4 class="font-medium text-gray-900">Check Expiring</h4>
                            <p class="text-sm text-gray-600">Review certificates expiring soon</p>
                        </div>
                    </button>

                    <button onclick="validateConnections()" 
                            class="w-full flex items-center p-3 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div class="text-left">
                            <h4 class="font-medium text-gray-900">Validate Connections</h4>
                            <p class="text-sm text-gray-600">Test all provider connections</p>
                        </div>
                    </button>

                    <button onclick="generateReport()" 
                            class="w-full flex items-center p-3 bg-green-50 hover:bg-green-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <div class="text-left">
                            <h4 class="font-medium text-gray-900">Generate Report</h4>
                            <p class="text-sm text-gray-600">Create health status report</p>
                        </div>
                    </button>

                    <button onclick="clearCache()" 
                            class="w-full flex items-center p-3 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        <div class="text-left">
                            <h4 class="font-medium text-gray-900">Clear Cache</h4>
                            <p class="text-sm text-gray-600">Clear system cache</p>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- Detailed Health Checks -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Detailed Health Checks</h2>
                <p class="text-sm text-gray-600 mt-1">Comprehensive system component status</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Component</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Response Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Check</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Message</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="health-checks-tbody" class="bg-white divide-y divide-gray-200">
                        <!-- Health check rows will be loaded here -->
                        <tr class="animate-pulse">
                            <td class="px-6 py-4"><div class="h-4 bg-gray-200 rounded w-24"></div></td>
                            <td class="px-6 py-4"><div class="h-4 bg-gray-200 rounded w-16"></div></td>
                            <td class="px-6 py-4"><div class="h-4 bg-gray-200 rounded w-20"></div></td>
                            <td class="px-6 py-4"><div class="h-4 bg-gray-200 rounded w-32"></div></td>
                            <td class="px-6 py-4"><div class="h-4 bg-gray-200 rounded w-40"></div></td>
                            <td class="px-6 py-4"><div class="h-4 bg-gray-200 rounded w-16"></div></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Diagnostic Results Modal -->
        <div id="diagnosticModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
            <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-2/3 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">System Diagnostics Results</h3>
                        <button onclick="closeDiagnosticModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                    </div>
                    
                    <div id="diagnostic-content">
                        <!-- Diagnostic results will be loaded here -->
                    </div>
                    
                    <div class="flex justify-end mt-6">
                        <button onclick="closeDiagnosticModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart.js CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

    <script>
        // Global variables
        let healthData = {};
        let certificateChart = null;
        let autoRefreshInterval = null;

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            loadHealthData();
            setupAutoRefresh();
        });

        // Load all health data
        async function loadHealthData() {
            try {
                const response = await fetch('/admin/ssl/health/data', {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (!response.ok) throw new Error('Failed to load health data');
                
                const data = await response.json();
                
                if (data.success) {
                    healthData = data.data;
                    updateHealthDisplay();
                } else {
                    throw new Error(data.message || 'Failed to load health data');
                }
            } catch (error) {
                console.error('Error loading health data:', error);
                showError('Failed to load health data: ' + error.message);
            }
        }

        // Update health display
        function updateHealthDisplay() {
            updateOverviewCards();
            updateProviderHealth();
            updatePerformanceMetrics();
            updateHealthChecksTable();
            updateCertificateChart();
        }

        // Update overview cards
        function updateOverviewCards() {
            const { overview } = healthData;
            
            // Overall health
            const healthIcon = document.getElementById('overall-health-icon');
            const healthStatus = document.getElementById('overall-health-status');
            
            if (overview.overall_status === 'healthy') {
                healthIcon.className = 'p-2 rounded-lg text-green-600 bg-green-100';
                healthStatus.textContent = 'Healthy';
                healthStatus.className = 'text-lg font-bold text-green-600';
            } else if (overview.overall_status === 'warning') {
                healthIcon.className = 'p-2 rounded-lg text-yellow-600 bg-yellow-100';
                healthStatus.textContent = 'Warning';
                healthStatus.className = 'text-lg font-bold text-yellow-600';
            } else {
                healthIcon.className = 'p-2 rounded-lg text-red-600 bg-red-100';
                healthStatus.textContent = 'Critical';
                healthStatus.className = 'text-lg font-bold text-red-600';
            }

            // Provider status
            document.getElementById('provider-status').textContent = 
                `${overview.healthy_providers}/${overview.total_providers} Online`;

            // Active certificates
            document.getElementById('active-certificates').textContent = 
                overview.active_certificates || '0';

            // System uptime
            document.getElementById('system-uptime').textContent = 
                overview.uptime || 'Unknown';
        }

        // Update provider health
        function updateProviderHealth() {
            const { providers } = healthData;
            const container = document.getElementById('provider-health-content');
            
            container.innerHTML = Object.entries(providers).map(([provider, status]) => `
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-3 h-3 rounded-full mr-3 ${getStatusColor(status.status)}"></div>
                        <div>
                            <h4 class="font-medium text-gray-900">${getProviderDisplayName(provider)}</h4>
                            <p class="text-sm text-gray-600">${status.message || 'No message'}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-medium text-gray-900">${status.response_time || 'N/A'}</div>
                        <div class="text-xs text-gray-500">Response time</div>
                    </div>
                </div>
            `).join('');
        }

        // Update performance metrics
        function updatePerformanceMetrics() {
            const { performance } = healthData;
            const container = document.getElementById('performance-metrics');
            
            container.innerHTML = `
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-700">Memory Usage</span>
                        <span class="text-sm text-gray-900">${performance.memory_usage || 'N/A'}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: ${performance.memory_percentage || 0}%"></div>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-700">CPU Load</span>
                        <span class="text-sm text-gray-900">${performance.cpu_load || 'N/A'}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-green-600 h-2 rounded-full" style="width: ${performance.cpu_percentage || 0}%"></div>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-700">Disk Usage</span>
                        <span class="text-sm text-gray-900">${performance.disk_usage || 'N/A'}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-purple-600 h-2 rounded-full" style="width: ${performance.disk_percentage || 0}%"></div>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-700">Queue Jobs</span>
                        <span class="text-sm text-gray-900">${performance.queue_jobs || 0}</span>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-700">Cache Hit Rate</span>
                        <span class="text-sm text-gray-900">${performance.cache_hit_rate || 'N/A'}</span>
                    </div>
                </div>
            `;
        }

        // Update health checks table
        function updateHealthChecksTable() {
            const { checks } = healthData;
            const tbody = document.getElementById('health-checks-tbody');
            
            tbody.innerHTML = Object.entries(checks).map(([component, check]) => `
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">${component}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                            check.status === 'healthy' ? 'bg-green-100 text-green-800' :
                            check.status === 'warning' ? 'bg-yellow-100 text-yellow-800' :
                            'bg-red-100 text-red-800'
                        }">
                            ${check.status}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${check.response_time || 'N/A'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${check.last_check ? new Date(check.last_check).toLocaleString() : 'Never'}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                        ${check.message || 'No message'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <button onclick="testComponent('${component}')" 
                                class="text-blue-600 hover:text-blue-900">Test</button>
                    </td>
                </tr>
            `).join('');
        }

        // Update certificate chart
        function updateCertificateChart() {
            const { certificate_stats } = healthData;
            const ctx = document.getElementById('certificateStatusChart').getContext('2d');
            
            if (certificateChart) {
                certificateChart.destroy();
            }
            
            certificateChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Issued', 'Pending', 'Expiring Soon', 'Failed', 'Expired'],
                    datasets: [{
                        data: [
                            certificate_stats.issued || 0,
                            certificate_stats.pending || 0,
                            certificate_stats.expiring_soon || 0,
                            certificate_stats.failed || 0,
                            certificate_stats.expired || 0
                        ],
                        backgroundColor: [
                            '#10b981',
                            '#f59e0b',
                            '#ef4444',
                            '#8b5cf6',
                            '#6b7280'
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Refresh health status
        async function refreshHealthStatus() {
            showLoading();
            await loadHealthData();
            showSuccess('Health status refreshed');
        }

        // Refresh provider health
        async function refreshProviderHealth() {
            try {
                const response = await fetch('/admin/ssl/providers/health', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                if (response.ok) {
                    await loadHealthData();
                    showSuccess('Provider health refreshed');
                } else {
                    throw new Error('Failed to refresh provider health');
                }
            } catch (error) {
                console.error('Error refreshing provider health:', error);
                showError('Failed to refresh provider health');
            }
        }

        // Run diagnostics
        async function runDiagnostics() {
            try {
                showInfo('Running diagnostics...');
                
                const response = await fetch('/admin/ssl/diagnostics/run', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                if (!response.ok) throw new Error('Failed to run diagnostics');
                
                const data = await response.json();
                
                if (data.success) {
                    showDiagnosticResults(data.data);
                } else {
                    throw new Error(data.message || 'Diagnostics failed');
                }
            } catch (error) {
                console.error('Error running diagnostics:', error);
                showError('Failed to run diagnostics: ' + error.message);
            }
        }

        // Show diagnostic results
        function showDiagnosticResults(results) {
            const content = document.getElementById('diagnostic-content');
            
            content.innerHTML = `
                <div class="space-y-6">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-medium text-gray-900 mb-2">Diagnostic Summary</h4>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>Total Checks: ${results.total_checks || 0}</div>
                            <div>Passed: ${results.passed || 0}</div>
                            <div>Warnings: ${results.warnings || 0}</div>
                            <div>Failed: ${results.failed || 0}</div>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        ${(results.checks || []).map(check => `
                            <div class="border-l-4 ${
                                check.status === 'passed' ? 'border-green-400 bg-green-50' :
                                check.status === 'warning' ? 'border-yellow-400 bg-yellow-50' :
                                'border-red-400 bg-red-50'
                            } p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        ${check.status === 'passed' ? 
                                            '<svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>' :
                                            check.status === 'warning' ?
                                            '<svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>' :
                                            '<svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>'
                                        }
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium ${
                                            check.status === 'passed' ? 'text-green-800' :
                                            check.status === 'warning' ? 'text-yellow-800' :
                                            'text-red-800'
                                        }">${check.name}</h3>
                                        <div class="mt-2 text-sm ${
                                            check.status === 'passed' ? 'text-green-700' :
                                            check.status === 'warning' ? 'text-yellow-700' :
                                            'text-red-700'
                                        }">
                                            <p>${check.message}</p>
                                            ${check.details ? `<div class="mt-1 text-xs">${check.details}</div>` : ''}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
            
            document.getElementById('diagnosticModal').classList.remove('hidden');
        }

        // Close diagnostic modal
        function closeDiagnosticModal() {
            document.getElementById('diagnosticModal').classList.add('hidden');
        }

        // Test specific component
        async function testComponent(component) {
            try {
                showInfo(`Testing ${component}...`);
                
                const response = await fetch(`/admin/ssl/health/test/${component}`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                if (!response.ok) throw new Error('Test failed');
                
                const data = await response.json();
                
                if (data.success) {
                    showSuccess(`${component} test completed: ${data.data.status}`);
                    await loadHealthData(); // Refresh data
                } else {
                    throw new Error(data.message || 'Test failed');
                }
            } catch (error) {
                console.error(`Error testing ${component}:`, error);
                showError(`Failed to test ${component}: ` + error.message);
            }
        }

        // Quick action functions
        async function checkExpiring() {
            try {
                const response = await fetch('/admin/ssl/certificates/expiring', {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    showInfo(`Found ${data.data.count} certificates expiring in the next 30 days`);
                } else {
                    throw new Error('Failed to check expiring certificates');
                }
            } catch (error) {
                showError('Failed to check expiring certificates');
            }
        }

        async function validateConnections() {
            showInfo('Validating all provider connections...');
            await refreshProviderHealth();
        }

        async function generateReport() {
            try {
                const response = await fetch('/admin/ssl/health/report', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                if (response.ok) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `ssl-health-report-${new Date().toISOString().split('T')[0]}.pdf`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    showSuccess('Health report downloaded');
                } else {
                    throw new Error('Failed to generate report');
                }
            } catch (error) {
                showError('Failed to generate report');
            }
        }

        async function clearCache() {
            try {
                const response = await fetch('/admin/ssl/cache/clear', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                if (response.ok) {
                    showSuccess('Cache cleared successfully');
                    await loadHealthData();
                } else {
                    throw new Error('Failed to clear cache');
                }
            } catch (error) {
                showError('Failed to clear cache');
            }
        }

        // Setup auto-refresh
        function setupAutoRefresh() {
            autoRefreshInterval = setInterval(() => {
                if (document.visibilityState === 'visible') {
                    loadHealthData();
                }
            }, 30000); // Refresh every 30 seconds
        }

        // Utility functions
        function getStatusColor(status) {
            switch (status) {
                case 'healthy': return 'bg-green-400';
                case 'warning': return 'bg-yellow-400';
                case 'error': case 'unhealthy': return 'bg-red-400';
                default: return 'bg-gray-400';
            }
        }

        function getProviderDisplayName(provider) {
            const names = {
                'gogetssl': 'GoGetSSL',
                'google_certificate_manager': 'Google Certificate Manager',
                'lets_encrypt': "Let's Encrypt"
            };
            return names[provider] || provider;
        }

        function showSuccess(message) {
            alert(message); // Replace with toast notification
        }

        function showError(message) {
            alert('Error: ' + message); // Replace with toast notification
        }

        function showInfo(message) {
            alert(message); // Replace with toast notification
        }

        function showLoading() {
            // Add loading indicator
        }

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
            if (certificateChart) {
                certificateChart.destroy();
            }
        });
    </script>
</x-layouts.admin>
