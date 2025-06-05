<x-layouts.admin :title="__('System Health')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl p-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ __('System Health') }}</flux:heading>
                <flux:subheading>{{ __('Comprehensive application and infrastructure monitoring') }}</flux:subheading>
            </div>
            <div class="flex space-x-2">
                <button onclick="refreshAllData()" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Refresh
                </button>
                <button onclick="exportHealthReport()" 
                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center">
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Export Report
                </button>
                <div class="relative">
                    <button onclick="toggleAutoRefresh()" 
                            id="auto-refresh-btn"
                            class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg flex items-center">
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span id="auto-refresh-text">Auto Refresh: Off</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- System Status Alert -->
        <div id="system-alert" class="hidden">
            <!-- System alerts will be displayed here -->
        </div>

        <!-- System Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
            <!-- Overall System Status -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-2 rounded-lg" id="system-status-icon">
                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">System Status</p>
                        <p class="text-lg font-bold" id="system-status-text">Loading...</p>
                        <p class="text-xs text-gray-500" id="last-check-time"></p>
                    </div>
                </div>
            </div>

            <!-- Application Health -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-2 rounded-lg text-blue-600 bg-blue-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Application</p>
                        <p class="text-lg font-bold text-gray-900" id="app-status">Loading...</p>
                        <p class="text-xs text-gray-500" id="app-version"></p>
                    </div>
                </div>
            </div>

            <!-- Database Health -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-2 rounded-lg text-green-600 bg-green-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Database</p>
                        <p class="text-lg font-bold text-gray-900" id="db-status">Loading...</p>
                        <p class="text-xs text-gray-500" id="db-response-time"></p>
                    </div>
                </div>
            </div>

            <!-- Server Resources -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-2 rounded-lg text-purple-600 bg-purple-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Server Resources</p>
                        <p class="text-lg font-bold text-gray-900" id="server-status">Loading...</p>
                        <p class="text-xs text-gray-500" id="server-load"></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resource Usage Charts -->
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <!-- Resource Usage -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Resource Usage</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-6">
                        <!-- CPU Usage -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium text-gray-700">CPU Usage</span>
                                <span class="text-sm text-gray-900" id="cpu-percentage">0%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="bg-blue-600 h-3 rounded-full transition-all duration-500" 
                                     id="cpu-bar" style="width: 0%"></div>
                            </div>
                            <div class="text-xs text-gray-500 mt-1" id="cpu-details">Load average: N/A</div>
                        </div>

                        <!-- Memory Usage -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium text-gray-700">Memory Usage</span>
                                <span class="text-sm text-gray-900" id="memory-percentage">0%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="bg-green-600 h-3 rounded-full transition-all duration-500" 
                                     id="memory-bar" style="width: 0%"></div>
                            </div>
                            <div class="text-xs text-gray-500 mt-1" id="memory-details">Used: 0 MB / Total: 0 MB</div>
                        </div>

                        <!-- Disk Usage -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium text-gray-700">Disk Usage</span>
                                <span class="text-sm text-gray-900" id="disk-percentage">0%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="bg-purple-600 h-3 rounded-full transition-all duration-500" 
                                     id="disk-bar" style="width: 0%"></div>
                            </div>
                            <div class="text-xs text-gray-500 mt-1" id="disk-details">Used: 0 GB / Total: 0 GB</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Metrics -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Performance Metrics</h2>
                </div>
                <div class="p-6">
                    <canvas id="performanceChart" height="300"></canvas>
                </div>
            </div>
        </div>

        <!-- Services and Components Status -->
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <!-- Application Services -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">Application Services</h2>
                    <button onclick="refreshServices()" class="text-blue-600 hover:text-blue-800">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </button>
                </div>
                <div class="p-6">
                    <div id="services-status" class="space-y-3">
                        <!-- Services will be loaded here -->
                        <div class="animate-pulse">
                            <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                            <div class="h-4 bg-gray-200 rounded w-1/2"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent System Events -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Recent System Events</h2>
                </div>
                <div class="p-6">
                    <div id="system-events" class="space-y-3 max-h-80 overflow-y-auto">
                        <!-- Events will be loaded here -->
                        <div class="animate-pulse">
                            <div class="h-4 bg-gray-200 rounded w-full mb-2"></div>
                            <div class="h-3 bg-gray-200 rounded w-2/3"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Health Checks -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Detailed Health Checks</h2>
                <p class="text-sm text-gray-600 mt-1">Comprehensive system component monitoring</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Component</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Response Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Check</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
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

        <!-- System Information -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">System Information</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
                    <div>
                        <h4 class="font-medium text-gray-900 mb-2">Environment</h4>
                        <dl class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-600">PHP Version:</dt>
                                <dd class="text-gray-900" id="php-version">Loading...</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Laravel Version:</dt>
                                <dd class="text-gray-900" id="laravel-version">Loading...</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Environment:</dt>
                                <dd class="text-gray-900" id="app-env">Loading...</dd>
                            </div>
                        </dl>
                    </div>
                    
                    <div>
                        <h4 class="font-medium text-gray-900 mb-2">Database</h4>
                        <dl class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Connection:</dt>
                                <dd class="text-gray-900" id="db-connection">Loading...</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Version:</dt>
                                <dd class="text-gray-900" id="db-version">Loading...</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Size:</dt>
                                <dd class="text-gray-900" id="db-size">Loading...</dd>
                            </div>
                        </dl>
                    </div>
                    
                    <div>
                        <h4 class="font-medium text-gray-900 mb-2">Cache</h4>
                        <dl class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Driver:</dt>
                                <dd class="text-gray-900" id="cache-driver">Loading...</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Status:</dt>
                                <dd class="text-gray-900" id="cache-status">Loading...</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Hit Rate:</dt>
                                <dd class="text-gray-900" id="cache-hit-rate">Loading...</dd>
                            </div>
                        </dl>
                    </div>
                    
                    <div>
                        <h4 class="font-medium text-gray-900 mb-2">Queue</h4>
                        <dl class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Driver:</dt>
                                <dd class="text-gray-900" id="queue-driver">Loading...</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Pending Jobs:</dt>
                                <dd class="text-gray-900" id="queue-pending">Loading...</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Failed Jobs:</dt>
                                <dd class="text-gray-900" id="queue-failed">Loading...</dd>
                            </div>
                        </dl>
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
        let performanceChart = null;
        let autoRefreshInterval = null;
        let isAutoRefreshEnabled = false;

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            loadSystemHealth();
        });

        // Load system health data
        async function loadSystemHealth() {
            try {
                const response = await fetch('/admin/system/health-data', {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (!response.ok) throw new Error('Failed to load system health data');
                
                const data = await response.json();
                
                if (data.success) {
                    healthData = data.data;
                    updateHealthDisplay();
                } else {
                    throw new Error(data.message || 'Failed to load system health data');
                }
            } catch (error) {
                console.error('Error loading system health:', error);
                showError('Failed to load system health data: ' + error.message);
                // Show offline state
                showOfflineState();
            }
        }

        // Update health display
        function updateHealthDisplay() {
            updateOverviewCards();
            updateResourceUsage();
            updatePerformanceChart();
            updateServicesStatus();
            updateSystemEvents();
            updateHealthChecksTable();
            updateSystemInformation();
            updateSystemAlert();
        }

        // Update overview cards
        function updateOverviewCards() {
            const { overview } = healthData;
            
            // System status
            updateSystemStatusCard(overview);
            
            // Application status
            document.getElementById('app-status').textContent = overview.app_status || 'Unknown';
            document.getElementById('app-version').textContent = `Laravel ${overview.laravel_version || 'Unknown'}`;
            
            // Database status
            document.getElementById('db-status').textContent = overview.db_status || 'Unknown';
            document.getElementById('db-response-time').textContent = 
                overview.db_response_time ? `${overview.db_response_time}ms` : 'N/A';
            
            // Server status
            document.getElementById('server-status').textContent = overview.server_status || 'Unknown';
            document.getElementById('server-load').textContent = 
                overview.server_load ? `Load: ${overview.server_load}` : 'N/A';
            
            // Last check time
            document.getElementById('last-check-time').textContent = 
                `Last check: ${new Date().toLocaleTimeString()}`;
        }

        // Update system status card
        function updateSystemStatusCard(overview) {
            const icon = document.getElementById('system-status-icon');
            const text = document.getElementById('system-status-text');
            
            if (overview.overall_status === 'healthy') {
                icon.className = 'p-2 rounded-lg text-green-600 bg-green-100';
                text.textContent = 'Healthy';
                text.className = 'text-lg font-bold text-green-600';
            } else if (overview.overall_status === 'warning') {
                icon.className = 'p-2 rounded-lg text-yellow-600 bg-yellow-100';
                text.textContent = 'Warning';
                text.className = 'text-lg font-bold text-yellow-600';
            } else {
                icon.className = 'p-2 rounded-lg text-red-600 bg-red-100';
                text.textContent = 'Critical';
                text.className = 'text-lg font-bold text-red-600';
            }
        }

        // Update resource usage
        function updateResourceUsage() {
            const { resources } = healthData;
            
            // CPU Usage
            const cpuPercentage = resources.cpu_percentage || 0;
            document.getElementById('cpu-percentage').textContent = `${cpuPercentage}%`;
            document.getElementById('cpu-bar').style.width = `${cpuPercentage}%`;
            document.getElementById('cpu-details').textContent = 
                `Load average: ${resources.cpu_load || 'N/A'}`;
            
            // Memory Usage
            const memoryPercentage = resources.memory_percentage || 0;
            document.getElementById('memory-percentage').textContent = `${memoryPercentage}%`;
            document.getElementById('memory-bar').style.width = `${memoryPercentage}%`;
            document.getElementById('memory-details').textContent = 
                `Used: ${resources.memory_used || '0'} / Total: ${resources.memory_total || '0'}`;
            
            // Disk Usage
            const diskPercentage = resources.disk_percentage || 0;
            document.getElementById('disk-percentage').textContent = `${diskPercentage}%`;
            document.getElementById('disk-bar').style.width = `${diskPercentage}%`;
            document.getElementById('disk-details').textContent = 
                `Used: ${resources.disk_used || '0'} / Total: ${resources.disk_total || '0'}`;
        }

        // Update performance chart
        function updatePerformanceChart() {
            const { performance_history } = healthData;
            const ctx = document.getElementById('performanceChart').getContext('2d');
            
            if (performanceChart) {
                performanceChart.destroy();
            }
            
            performanceChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: performance_history.labels || [],
                    datasets: [
                        {
                            label: 'CPU %',
                            data: performance_history.cpu || [],
                            borderColor: 'rgb(59, 130, 246)',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Memory %',
                            data: performance_history.memory || [],
                            borderColor: 'rgb(16, 185, 129)',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Response Time (ms)',
                            data: performance_history.response_time || [],
                            borderColor: 'rgb(139, 92, 246)',
                            backgroundColor: 'rgba(139, 92, 246, 0.1)',
                            tension: 0.4,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            max: 100,
                            beginAtZero: true
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            beginAtZero: true,
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    }
                }
            });
        }

        // Update services status
        function updateServicesStatus() {
            const { services } = healthData;
            const container = document.getElementById('services-status');
            
            container.innerHTML = Object.entries(services).map(([service, status]) => `
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-3 h-3 rounded-full mr-3 ${getStatusColor(status.status)}"></div>
                        <div>
                            <h4 class="font-medium text-gray-900">${getServiceDisplayName(service)}</h4>
                            <p class="text-sm text-gray-600">${status.message || 'No message'}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-medium text-gray-900">${status.version || 'N/A'}</div>
                        <div class="text-xs text-gray-500">Version</div>
                    </div>
                </div>
            `).join('');
        }

        // Update system events
        function updateSystemEvents() {
            const { events } = healthData;
            const container = document.getElementById('system-events');
            
            if (!events || events.length === 0) {
                container.innerHTML = '<p class="text-gray-500 text-sm">No recent events</p>';
                return;
            }
            
            container.innerHTML = events.map(event => `
                <div class="border-l-4 ${getEventBorderColor(event.level)} bg-gray-50 p-3">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            ${getEventIcon(event.level)}
                        </div>
                        <div class="ml-3 flex-1">
                            <p class="text-sm font-medium text-gray-900">${event.message}</p>
                            <p class="text-xs text-gray-500 mt-1">${new Date(event.timestamp).toLocaleString()}</p>
                            ${event.details ? `<p class="text-xs text-gray-600 mt-1">${event.details}</p>` : ''}
                        </div>
                    </div>
                </div>
            `).join('');
        }

        // Update health checks table
        function updateHealthChecksTable() {
            const { checks } = healthData;
            const tbody = document.getElementById('health-checks-tbody');
            
            tbody.innerHTML = Object.entries(checks).map(([component, check]) => `
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">${getComponentDisplayName(component)}</div>
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
                        ${check.message || 'No details available'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <button onclick="testComponent('${component}')" 
                                class="text-blue-600 hover:text-blue-900">Test</button>
                    </td>
                </tr>
            `).join('');
        }

        // Update system information
        function updateSystemInformation() {
            const { system_info } = healthData;
            
            // Environment
            document.getElementById('php-version').textContent = system_info.php_version || 'Unknown';
            document.getElementById('laravel-version').textContent = system_info.laravel_version || 'Unknown';
            document.getElementById('app-env').textContent = system_info.app_env || 'Unknown';
            
            // Database
            document.getElementById('db-connection').textContent = system_info.db_connection || 'Unknown';
            document.getElementById('db-version').textContent = system_info.db_version || 'Unknown';
            document.getElementById('db-size').textContent = system_info.db_size || 'Unknown';
            
            // Cache
            document.getElementById('cache-driver').textContent = system_info.cache_driver || 'Unknown';
            document.getElementById('cache-status').textContent = system_info.cache_status || 'Unknown';
            document.getElementById('cache-hit-rate').textContent = system_info.cache_hit_rate || 'N/A';
            
            // Queue
            document.getElementById('queue-driver').textContent = system_info.queue_driver || 'Unknown';
            document.getElementById('queue-pending').textContent = system_info.queue_pending || '0';
            document.getElementById('queue-failed').textContent = system_info.queue_failed || '0';
        }

        // Update system alert
        function updateSystemAlert() {
            const { alerts } = healthData;
            const alertContainer = document.getElementById('system-alert');
            
            if (!alerts || alerts.length === 0) {
                alertContainer.classList.add('hidden');
                return;
            }
            
            const highestSeverity = Math.max(...alerts.map(alert => 
                alert.severity === 'critical' ? 3 : alert.severity === 'warning' ? 2 : 1
            ));
            
            const alertClass = highestSeverity === 3 ? 'bg-red-50 border-red-200' :
                              highestSeverity === 2 ? 'bg-yellow-50 border-yellow-200' :
                              'bg-blue-50 border-blue-200';
            
            const iconClass = highestSeverity === 3 ? 'text-red-400' :
                             highestSeverity === 2 ? 'text-yellow-400' :
                             'text-blue-400';
            
            alertContainer.innerHTML = `
                <div class="${alertClass} border rounded-lg p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 ${iconClass}" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium">System Alerts (${alerts.length})</h3>
                            <div class="mt-2 text-sm">
                                ${alerts.slice(0, 3).map(alert => `<p>• ${alert.message}</p>`).join('')}
                                ${alerts.length > 3 ? `<p class="text-xs text-gray-600 mt-1">... and ${alerts.length - 3} more</p>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            alertContainer.classList.remove('hidden');
        }

        // Show offline state
        function showOfflineState() {
            document.getElementById('system-status-text').textContent = 'Offline';
            document.getElementById('system-status-text').className = 'text-lg font-bold text-red-600';
            document.getElementById('system-status-icon').className = 'p-2 rounded-lg text-red-600 bg-red-100';
        }

        // Refresh functions
        async function refreshAllData() {
            showInfo('Refreshing system health data...');
            await loadSystemHealth();
            showSuccess('System health data refreshed');
        }

        async function refreshServices() {
            try {
                const response = await fetch('/admin/system/services/refresh', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                if (response.ok) {
                    await loadSystemHealth();
                    showSuccess('Services refreshed');
                } else {
                    throw new Error('Failed to refresh services');
                }
            } catch (error) {
                showError('Failed to refresh services');
            }
        }

        // Test component
        async function testComponent(component) {
            try {
                showInfo(`Testing ${component}...`);
                
                const response = await fetch(`/admin/system/test/${component}`, {
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
                    await loadSystemHealth();
                } else {
                    throw new Error(data.message || 'Test failed');
                }
            } catch (error) {
                showError(`Failed to test ${component}: ` + error.message);
            }
        }

        // Auto refresh
        function toggleAutoRefresh() {
            const btn = document.getElementById('auto-refresh-btn');
            const text = document.getElementById('auto-refresh-text');
            
            if (isAutoRefreshEnabled) {
                clearInterval(autoRefreshInterval);
                isAutoRefreshEnabled = false;
                btn.className = 'bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg flex items-center';
                text.textContent = 'Auto Refresh: Off';
            } else {
                autoRefreshInterval = setInterval(() => {
                    if (document.visibilityState === 'visible') {
                        loadSystemHealth();
                    }
                }, 30000);
                isAutoRefreshEnabled = true;
                btn.className = 'bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center';
                text.textContent = 'Auto Refresh: On';
            }
        }

        // Export health report
        async function exportHealthReport() {
            try {
                showInfo('Generating health report...');
                
                const response = await fetch('/admin/system/health/export', {
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
                    a.download = `system-health-report-${new Date().toISOString().split('T')[0]}.pdf`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    showSuccess('Health report downloaded');
                } else {
                    throw new Error('Failed to generate report');
                }
            } catch (error) {
                showError('Failed to export health report');
            }
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

        function getServiceDisplayName(service) {
            const names = {
                'database': 'Database',
                'cache': 'Cache',
                'queue': 'Queue',
                'mail': 'Mail Service',
                'storage': 'File Storage',
                'session': 'Session Store'
            };
            return names[service] || service;
        }

        function getComponentDisplayName(component) {
            const names = {
                'database': 'Database Connection',
                'cache': 'Cache System',
                'queue': 'Queue System',
                'mail': 'Mail Service',
                'storage': 'File Storage',
                'session': 'Session Store',
                'redis': 'Redis Cache',
                'http': 'HTTP Service'
            };
            return names[component] || component;
        }

        function getEventBorderColor(level) {
            switch (level) {
                case 'error': case 'critical': return 'border-red-400';
                case 'warning': return 'border-yellow-400';
                case 'info': return 'border-blue-400';
                default: return 'border-gray-400';
            }
        }

        function getEventIcon(level) {
            switch (level) {
                case 'error': case 'critical':
                    return '<svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>';
                case 'warning':
                    return '<svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>';
                default:
                    return '<svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>';
            }
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

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
            if (performanceChart) {
                performanceChart.destroy();
            }
        });
    </script>
</x-layouts.admin>
