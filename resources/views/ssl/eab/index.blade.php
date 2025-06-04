<x-layouts.app :title="__('EAB Credentials Management')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-6">
        <div class="mb-6">
            <flux:heading size="xl">{{ __('EAB Credentials Management') }}</flux:heading>
            <flux:subheading>{{ __('Manage your ACME External Account Binding credentials') }}</flux:subheading>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-2 rounded-lg text-blue-600 bg-blue-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Credentials</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $stats['total_credentials'] }}</p>
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
                        <p class="text-sm font-medium text-gray-600">Active Credentials</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $stats['active_credentials'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-2 rounded-lg text-purple-600 bg-purple-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Usage</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $stats['total_usage'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-2 rounded-lg text-orange-600 bg-orange-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Last Used</p>
                        <p class="text-sm font-bold text-gray-900">
                            {{ $stats['last_used'] ? $stats['last_used']->diffForHumans() : 'Never' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ACME Directory Information -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">ACME Directory URL</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <p>Use this URL with your ACME client:</p>
                        <code class="block bg-blue-100 p-2 rounded mt-2 text-xs">{{ $acmeDirectoryUrl }}</code>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-between items-center mb-6">
            <div class="flex space-x-4">
                <button id="generateCredentialsBtn" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Generate New Credentials
                </button>
                
                <a href="{{ route('ssl.eab.instructions') }}" 
                   class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg flex items-center">
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    View Instructions
                </a>
            </div>
        </div>

        <!-- Credentials Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">EAB Credentials</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MAC ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usage Count</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Used</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($eabCredentials as $credential)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 font-mono">
                                        {{ $credential->mac_id }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        {{ $credential->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $credential->is_active ? 'Active' : 'Revoked' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $credential->usage_count }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $credential->last_used_at ? $credential->last_used_at->diffForHumans() : 'Never' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $credential->created_at->format('M d, Y') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <button onclick="viewCredentialDetails({{ $credential->id }})"
                                                class="text-blue-600 hover:text-blue-900">
                                            View Details
                                        </button>
                                        @if($credential->is_active)
                                            <button onclick="revokeCredential({{ $credential->id }})"
                                                    class="text-red-600 hover:text-red-900">
                                                Revoke
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                    <div class="flex flex-col items-center">
                                        <svg class="h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                                        </svg>
                                        <p class="text-lg font-medium">No EAB credentials found</p>
                                        <p class="text-sm">Generate your first EAB credentials to get started with ACME</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        @if($eabCredentials->hasPages())
            <div class="mt-6">
                {{ $eabCredentials->links() }}
            </div>
        @endif
    </div>

    <!-- Generate Credentials Modal -->
    <div id="generateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Generate EAB Credentials</h3>
                    <button onclick="closeGenerateModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>
                
                <div class="mb-4">
                    <p class="text-sm text-gray-600">
                        This will generate new EAB (External Account Binding) credentials for use with ACME clients.
                    </p>
                </div>
                
                <div id="generationResult" class="hidden mb-4">
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <h4 class="font-medium text-green-800 mb-2">Credentials Generated Successfully!</h4>
                        <div class="text-sm text-green-700">
                            <div class="mb-2">
                                <strong>MAC ID:</strong>
                                <code id="generatedMacId" class="block bg-green-100 p-2 rounded mt-1 text-xs break-all"></code>
                            </div>
                            <div class="mb-2">
                                <strong>MAC Key:</strong>
                                <code id="generatedMacKey" class="block bg-green-100 p-2 rounded mt-1 text-xs break-all"></code>
                            </div>
                            <p class="text-xs mt-2 text-red-600">
                                <strong>Important:</strong> This is the only time the MAC Key will be displayed. Please save it securely.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button onclick="closeGenerateModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md">
                        Cancel
                    </button>
                    <button id="confirmGenerateBtn" onclick="generateCredentials()"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md">
                        Generate Credentials
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Credential Details Modal -->
    <div id="detailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-3/4 max-w-4xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">EAB Credential Details</h3>
                    <button onclick="closeDetailsModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>
                
                <div id="credentialDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
                
                <div class="flex justify-end mt-6">
                    <button onclick="closeDetailsModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Generate new EAB credentials
        function showGenerateModal() {
            document.getElementById('generateModal').classList.remove('hidden');
            document.getElementById('generationResult').classList.add('hidden');
        }

        function closeGenerateModal() {
            document.getElementById('generateModal').classList.add('hidden');
            document.getElementById('generationResult').classList.add('hidden');
        }

        async function generateCredentials() {
            const btn = document.getElementById('confirmGenerateBtn');
            btn.disabled = true;
            btn.textContent = 'Generating...';

            try {
                const response = await fetch('{{ route('ssl.eab.generate') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                const result = await response.json();

                if (result.success) {
                    document.getElementById('generatedMacId').textContent = result.data.mac_id;
                    document.getElementById('generatedMacKey').textContent = result.data.mac_key;
                    document.getElementById('generationResult').classList.remove('hidden');
                    
                    // Hide generate button and show success
                    btn.style.display = 'none';
                    
                    // Refresh page after a delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 5000);
                } else {
                    alert('Error: ' + result.message);
                    closeGenerateModal();
                }
            } catch (error) {
                alert('Error generating credentials: ' + error.message);
                closeGenerateModal();
            } finally {
                btn.disabled = false;
                btn.textContent = 'Generate Credentials';
            }
        }

        // View credential details
        async function viewCredentialDetails(credentialId) {
            try {
                const response = await fetch(`/ssl/eab/credentials/${credentialId}`, {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                const result = await response.json();

                if (result.success) {
                    document.getElementById('credentialDetailsContent').innerHTML = buildDetailsHTML(result.data);
                    document.getElementById('detailsModal').classList.remove('hidden');
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error loading credential details: ' + error.message);
            }
        }

        function buildDetailsHTML(data) {
            return `
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">MAC ID</label>
                            <code class="block bg-gray-100 p-2 rounded text-sm">${data.mac_id}</code>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${data.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                ${data.is_active ? 'Active' : 'Revoked'}
                            </span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Usage Count</label>
                            <p class="text-sm text-gray-900">${data.usage_count}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Created</label>
                            <p class="text-sm text-gray-900">${new Date(data.created_at).toLocaleString()}</p>
                        </div>
                    </div>
                    
                    ${data.acme_accounts && data.acme_accounts.length > 0 ? `
                        <div>
                            <h4 class="text-md font-medium text-gray-900 mb-2">Associated ACME Accounts</h4>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Account ID</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Orders</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        ${data.acme_accounts.map(account => `
                                            <tr>
                                                <td class="px-3 py-2 text-sm text-gray-900">${account.id}</td>
                                                <td class="px-3 py-2 text-sm text-gray-900">${account.status}</td>
                                                <td class="px-3 py-2 text-sm text-gray-900">${account.orders_count}</td>
                                                <td class="px-3 py-2 text-sm text-gray-900">${new Date(account.created_at).toLocaleDateString()}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ` : ''}
                </div>
            `;
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').classList.add('hidden');
        }

        // Revoke credential
        async function revokeCredential(credentialId) {
            if (!confirm('Are you sure you want to revoke this EAB credential? This action cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch(`/ssl/eab/${credentialId}/revoke`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                const result = await response.json();

                if (result.success) {
                    alert('EAB credential revoked successfully');
                    window.location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error revoking credential: ' + error.message);
            }
        }

        // Event listeners
        document.getElementById('generateCredentialsBtn').addEventListener('click', showGenerateModal);
    </script>
</x-layouts.app>