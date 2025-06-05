<x-layouts.admin :title="__('System Logs')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-6">
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="xl">{{ __('System Logs') }}</flux:heading>
                    <flux:subheading>{{ __('Monitor and analyze system logs') }}</flux:subheading>
                </div>
                <div class="flex items-center space-x-3">
                    <button onclick="downloadLogs()" 
                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center">
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Download
                    </button>
                    <button onclick="showClearLogsModal()" 
                            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg flex items-center">
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        Clear Logs
                    </button>
                    <button onclick="refreshLogs()" 
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Logs (24h)</p>
                        <p class="text-2xl font-bold text-gray-900" id="total-logs-count">Loading...</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-2 rounded-lg text-red-600 bg-red-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.464 0L4.35 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Errors (24h)</p>
                        <p class="text-2xl font-bold text-gray-900" id="error-logs-count">Loading...</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-2 rounded-lg text-yellow-600 bg-yellow-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Warnings (24h)</p>
                        <p class="text-2xl font-bold text-gray-900" id="warning-logs-count">Loading...</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-2 rounded-lg text-purple-600 bg-purple-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v1m4-1v14a4 4 0 01-4 4H7m4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4h-4z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Log Files</p>
                        <p class="text-2xl font-bold text-gray-900" id="log-files-count">{{ count($logFiles) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Source Selection -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <!-- Log Source -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Source</label>
                    <select id="log-source" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="database">Database Logs</option>
                        <option value="file">Log Files</option>
                    </select>
                </div>

                <!-- Log File (shown when source is 'file') -->
                <div id="log-file-selector" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Log File</label>
                    <select id="log-file" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        @foreach($logFiles as $file)
                            <option value="{{ $file['name'] }}">{{ $file['name'] }} ({{ $file['size'] }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- Log Level -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Level</label>
                    <select id="log-level" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All Levels</option>
                        <option value="emergency">Emergency</option>
                        <option value="alert">Alert</option>
                        <option value="critical">Critical</option>
                        <option value="error">Error</option>
                        <option value="warning">Warning</option>
                        <option value="notice">Notice</option>
                        <option value="info">Info</option>
                        <option value="debug">Debug</option>
                    </select>
                </div>

                <!-- Date From -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                    <input type="date" 
                           id="date-from" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Date To -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                    <input type="date" 
                           id="date-to" 
                           value="{{ date('Y-m-d') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Search -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" 
                           id="log-search" 
                           placeholder="Search logs..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="flex justify-between items-center mt-4">
                <div class="flex items-center space-x-2">
                    <button onclick="filterLogs()" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                        Apply Filters
                    </button>
                    <button onclick="clearFilters()" 
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-md">
                        Clear Filters
                    </button>
                </div>
                <div class="flex items-center space-x-2">
                    <label class="text-sm text-gray-600">Per page:</label>
                    <select id="per-page" 
                            class="border border-gray-300 rounded px-2 py-1 text-sm">
                        <option value="25">25</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Recent Errors Card -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Errors (Last 24h)</h3>
            <div id="recent-errors-container">
                <div class="animate-pulse">
                    <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                    <div class="h-4 bg-gray-200 rounded w-1/2 mb-2"></div>
                    <div class="h-4 bg-gray-200 rounded w-2/3"></div>
                </div>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">System Logs</h2>
            </div>
            
            <!-- Loading State -->
            <div id="logs-loading" class="p-8 text-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                <p class="text-gray-500 mt-2">Loading logs...</p>
            </div>

            <!-- Logs Content -->
            <div id="logs-content" class="hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Level</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Channel</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Message</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="logs-tbody" class="bg-white divide-y divide-gray-200">
                            <!-- Logs will be populated here -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Empty State -->
            <div id="logs-empty" class="p-8 text-center hidden">
                <svg class="h-12 w-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No logs found</h3>
                <p class="text-gray-500">No logs match your current filters.</p>
            </div>
        </div>

        <!-- Pagination -->
        <div id="pagination-container" class="mt-6"></div>
    </div>

    <!-- Clear Logs Modal -->
    <div id="clearLogsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 lg:w-1/3 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Clear System Logs</h3>
                    <button onclick="closeClearLogsModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>

                <form id="clearLogsForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Log Source</label>
                        <select name="source" id="clear-source" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required>
                            <option value="database">Database Logs</option>
                            <option value="file">Log File</option>
                        </select>
                    </div>

                    <div id="clear-file-selector" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Log File</label>
                        <select name="file" id="clear-file"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            @foreach($logFiles as $file)
                                <option value="{{ $file['name'] }}">{{ $file['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Clear logs older than</label>
                        <select name="days" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required>
                            <option value="7">7 days</option>
                            <option value="30">30 days</option>
                            <option value="60">60 days</option>
                            <option value="90">90 days</option>
                            <option value="365">1 year</option>
                        </select>
                    </div>

                    <div class="bg-red-50 border border-red-200 rounded-md p-4">
                        <div class="flex">
                            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Warning</h3>
                                <p class="text-sm text-red-700 mt-1">This action cannot be undone. Logs will be permanently deleted.</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeClearLogsModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md">
                            Clear Logs
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Download Logs Modal -->
    <div id="downloadLogsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 lg:w-1/3 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Download Logs</h3>
                    <button onclick="closeDownloadLogsModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>

                <form id="downloadLogsForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Log Source</label>
                        <select name="source" id="download-source" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required>
                            <option value="database">Database Logs</option>
                            <option value="file">Log File</option>
                        </select>
                    </div>

                    <div id="download-file-selector" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Log File</label>
                        <select name="file" id="download-file"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            @foreach($logFiles as $file)
                                <option value="{{ $file['name'] }}">{{ $file['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Format</label>
                        <select name="format" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required>
                            <option value="json">JSON</option>
                            <option value="csv">CSV</option>
                            <option value="txt">Text</option>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeDownloadLogsModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md">
                            Download
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Log Details Modal -->
    <div id="logDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-2/3 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Log Details</h3>
                    <button onclick="closeLogDetailsModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>

                <div id="log-details-content">
                    <!-- Log details will be loaded here -->
                </div>

                <div class="flex justify-end mt-6">
                    <button onclick="closeLogDetailsModal()"
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
        let currentFilters = {};
        let autoRefreshInterval = null;

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            loadStatistics();
            loadLogs();
            setupEventListeners();
            startAutoRefresh();
        });

        function setupEventListeners() {
            // Source change handler
            document.getElementById('log-source').addEventListener('change', function() {
                toggleFileSelector();
            });

            // Clear logs source change handler
            document.getElementById('clear-source').addEventListener('change', function() {
                toggleClearFileSelector();
            });

            // Download logs source change handler
            document.getElementById('download-source').addEventListener('change', function() {
                toggleDownloadFileSelector();
            });

            // Form submissions
            document.getElementById('clearLogsForm').addEventListener('submit', handleClearLogs);
            document.getElementById('downloadLogsForm').addEventListener('submit', handleDownloadLogs);

            // Auto-refresh on filter change
            document.getElementById('per-page').addEventListener('change', function() {
                currentPage = 1;
                loadLogs();
            });

            // Search on enter key
            document.getElementById('log-search').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    filterLogs();
                }
            });
        }

        // Toggle file selector visibility
        function toggleFileSelector() {
            const source = document.getElementById('log-source').value;
            const fileSelector = document.getElementById('log-file-selector');
            
            if (source === 'file') {
                fileSelector.classList.remove('hidden');
            } else {
                fileSelector.classList.add('hidden');
            }
        }

        function toggleClearFileSelector() {
            const source = document.getElementById('clear-source').value;
            const fileSelector = document.getElementById('clear-file-selector');
            
            if (source === 'file') {
                fileSelector.classList.remove('hidden');
            } else {
                fileSelector.classList.add('hidden');
            }
        }

        function toggleDownloadFileSelector() {
            const source = document.getElementById('download-source').value;
            const fileSelector = document.getElementById('download-file-selector');
            
            if (source === 'file') {
                fileSelector.classList.remove('hidden');
            } else {
                fileSelector.classList.add('hidden');
            }
        }

        // Load statistics
        async function loadStatistics() {
            try {
                const response = await fetch('/admin/system/logs/statistics', {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (!response.ok) throw new Error('Failed to load statistics');

                const data = await response.json();
                if (data.success) {
                    updateStatistics(data.data);
                    updateRecentErrors(data.data.recent_errors || []);
                }
            } catch (error) {
                console.error('Error loading statistics:', error);
            }
        }

        // Update statistics display
        function updateStatistics(stats) {
            const dbStats = stats.database || {};
            const levels = stats.levels || {};
            
            document.getElementById('total-logs-count').textContent = dbStats.recent_count || 0;
            document.getElementById('error-logs-count').textContent = 
                (levels.ERROR || 0) + (levels.CRITICAL || 0) + (levels.EMERGENCY || 0) + (levels.ALERT || 0);
            document.getElementById('warning-logs-count').textContent = levels.WARNING || 0;
        }

        // Update recent errors display
        function updateRecentErrors(recentErrors) {
            const container = document.getElementById('recent-errors-container');
            
            if (recentErrors.length === 0) {
                container.innerHTML = '<p class="text-gray-500 text-sm">No recent errors found.</p>';
                return;
            }

            container.innerHTML = recentErrors.map(error => `
                <div class="flex items-start space-x-3 py-2 border-b border-gray-100 last:border-b-0">
                    <div class="flex-shrink-0">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${error.level_class}">
                            ${error.level}
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-900 truncate">${error.message}</p>
                        <p class="text-xs text-gray-500">${error.channel} • ${error.time}</p>
                    </div>
                </div>
            `).join('');
        }

        // Load logs
        async function loadLogs(page = 1) {
            try {
                showLoading();
                
                const params = new URLSearchParams({
                    page: page,
                    per_page: document.getElementById('per-page').value,
                    source: document.getElementById('log-source').value,
                    ...currentFilters
                });

                if (document.getElementById('log-source').value === 'file') {
                    params.append('file', document.getElementById('log-file').value);
                }

                const response = await fetch(`/admin/system/logs?${params}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (!response.ok) throw new Error('Failed to load logs');

                const data = await response.json();
                if (data.success) {
                    renderLogs(data.data.logs || []);
                    renderPagination(data.data.pagination);
                    currentPage = page;
                } else {
                    throw new Error(data.message || 'Failed to load logs');
                }

                hideLoading();
            } catch (error) {
                console.error('Error loading logs:', error);
                showError('Failed to load logs: ' + error.message);
                hideLoading();
                showEmpty();
            }
        }

        // Render logs table
        function renderLogs(logs) {
            const tbody = document.getElementById('logs-tbody');

            if (!Array.isArray(logs) || logs.length === 0) {
                showEmpty();
                return;
            }

            tbody.innerHTML = logs.map(log => `
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${log.formatted_time}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${log.level_class}">
                            ${log.level}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${log.channel || log.environment || '-'}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <div class="max-w-xs truncate">${log.message}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <button onclick="viewLogDetails(${JSON.stringify(log).replace(/"/g, '&quot;')})" 
                                class="text-blue-600 hover:text-blue-900">
                            View Details
                        </button>
                    </td>
                </tr>
            `).join('');

            document.getElementById('logs-content').classList.remove('hidden');
            document.getElementById('logs-empty').classList.add('hidden');
        }

        // View log details
        function viewLogDetails(log) {
            const content = document.getElementById('log-details-content');
            
            let contextHtml = '';
            if (log.context) {
                contextHtml = `
                    <div class="mt-4">
                        <h4 class="font-medium text-gray-900 mb-2">Context</h4>
                        <pre class="bg-gray-50 p-3 rounded-md text-sm overflow-x-auto">${JSON.stringify(log.context, null, 2)}</pre>
                    </div>
                `;
            }

            let extraHtml = '';
            if (log.extra) {
                extraHtml = `
                    <div class="mt-4">
                        <h4 class="font-medium text-gray-900 mb-2">Extra</h4>
                        <pre class="bg-gray-50 p-3 rounded-md text-sm overflow-x-auto">${JSON.stringify(log.extra, null, 2)}</pre>
                    </div>
                `;
            }

            content.innerHTML = `
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <h4 class="font-medium text-gray-900">Timestamp</h4>
                            <p class="text-sm text-gray-600">${log.formatted_time}</p>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900">Level</h4>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${log.level_class}">
                                ${log.level}
                            </span>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900">Channel</h4>
                            <p class="text-sm text-gray-600">${log.channel || log.environment || '-'}</p>
                        </div>
                    </div>
                    
                    <div>
                        <h4 class="font-medium text-gray-900 mb-2">Message</h4>
                        <div class="bg-gray-50 p-3 rounded-md text-sm whitespace-pre-wrap">${log.message}</div>
                    </div>
                    
                    ${contextHtml}
                    ${extraHtml}
                    
                    ${log.raw_line ? `
                        <div class="mt-4">
                            <h4 class="font-medium text-gray-900 mb-2">Raw Log Line</h4>
                            <pre class="bg-gray-50 p-3 rounded-md text-sm overflow-x-auto">${log.raw_line}</pre>
                        </div>
                    ` : ''}
                </div>
            `;

            document.getElementById('logDetailsModal').classList.remove('hidden');
        }

        // Apply filters
        function filterLogs() {
            currentFilters = {
                level: document.getElementById('log-level').value,
                search: document.getElementById('log-search').value,
                date_from: document.getElementById('date-from').value,
                date_to: document.getElementById('date-to').value
            };

            // Remove empty filters
            Object.keys(currentFilters).forEach(key => {
                if (!currentFilters[key]) {
                    delete currentFilters[key];
                }
            });

            currentPage = 1;
            loadLogs();
        }

        // Clear filters
        function clearFilters() {
            document.getElementById('log-level').value = '';
            document.getElementById('log-search').value = '';
            document.getElementById('date-from').value = '';
            document.getElementById('date-to').value = new Date().toISOString().split('T')[0];
            
            currentFilters = {};
            currentPage = 1;
            loadLogs();
        }

        // Handle clear logs form submission
        async function handleClearLogs(event) {
            event.preventDefault();

            if (!confirm('Are you sure you want to clear these logs? This action cannot be undone.')) {
                return;
            }

            const formData = new FormData(event.target);
            const clearData = {
                source: formData.get('source'),
                days: formData.get('days')
            };

            if (formData.get('source') === 'file') {
                clearData.file = formData.get('file');
            }

            try {
                const response = await fetch('/admin/system/logs/clear', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(clearData)
                });

                const result = await response.json();

                if (result.success) {
                    showSuccess(result.message);
                    closeClearLogsModal();
                    loadLogs();
                    loadStatistics();
                } else {
                    showError(result.message || 'Failed to clear logs');
                }
            } catch (error) {
                console.error('Error clearing logs:', error);
                showError('Failed to clear logs: ' + error.message);
            }
        }

        // Handle download logs
        function handleDownloadLogs(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const params = new URLSearchParams(formData);
            
            // Create download link
            const downloadUrl = `/admin/system/logs/download?${params}`;
            
            // Create temporary link and click it
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = '';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            closeDownloadLogsModal();
        }

        // Render pagination
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
                            <button onclick="loadLogs(${current_page - 1})" 
                                    class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                Previous
                            </button>
                        ` : ''}
                        ${current_page < last_page ? `
                            <button onclick="loadLogs(${current_page + 1})" 
                                    class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                Next
                            </button>
                        ` : ''}
                    </div>
                </div>
            `;
        }

        // Auto refresh
        function startAutoRefresh() {
            autoRefreshInterval = setInterval(() => {
                if (document.visibilityState === 'visible') {
                    loadStatistics();
                    loadLogs(currentPage);
                }
            }, 30000); // Refresh every 30 seconds
        }

        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
            }
        }

        // Utility functions
        function refreshLogs() {
            loadLogs(currentPage);
            loadStatistics();
        }

        function downloadLogs() {
            document.getElementById('downloadLogsModal').classList.remove('hidden');
        }

        function showClearLogsModal() {
            document.getElementById('clearLogsModal').classList.remove('hidden');
        }

        function closeClearLogsModal() {
            document.getElementById('clearLogsModal').classList.add('hidden');
            document.getElementById('clearLogsForm').reset();
        }

        function closeDownloadLogsModal() {
            document.getElementById('downloadLogsModal').classList.add('hidden');
            document.getElementById('downloadLogsForm').reset();
        }

        function closeLogDetailsModal() {
            document.getElementById('logDetailsModal').classList.add('hidden');
        }

        function showLoading() {
            document.getElementById('logs-loading').classList.remove('hidden');
            document.getElementById('logs-content').classList.add('hidden');
            document.getElementById('logs-empty').classList.add('hidden');
        }

        function hideLoading() {
            document.getElementById('logs-loading').classList.add('hidden');
            document.getElementById('logs-content').classList.remove('hidden');
        }

        function showEmpty() {
            document.getElementById('logs-empty').classList.remove('hidden');
            document.getElementById('logs-content').classList.add('hidden');
            document.getElementById('logs-loading').classList.add('hidden');
        }

        function showSuccess(message) {
            // Simple alert for now - you can implement toast notifications later
            alert(message);
        }

        function showError(message) {
            // Simple alert for now - you can implement toast notifications later
            alert('Error: ' + message);
        }

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            stopAutoRefresh();
        });
    </script>
</x-layouts.admin>