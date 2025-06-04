<x-layouts.app :title="__('SSL Certificates')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-6">
        <div class="mb-6">
            <flux:heading size="xl">{{ __('SSL Certificates') }}</flux:heading>
            <flux:subheading>{{ __('Manage your SSL certificates and their validation status') }}</flux:subheading>
        </div>

        @auth
            @php
                $user = Auth::user();
                $activeSubscription = $user->activeSubscription;
            @endphp

            @if(!$activeSubscription)
                <!-- No Active Subscription -->
                <div class="text-center py-12">
                    <div class="mx-auto w-24 h-24 bg-yellow-100 rounded-full flex items-center justify-center mb-6">
                        <svg class="w-12 h-12 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.664-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                    
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Active Subscription</h3>
                    <p class="text-gray-600 mb-6 max-w-md mx-auto">
                        You need an active SSL subscription to view and manage certificates.
                    </p>
                    
                    <a href="{{ route('ssl.subscriptions.index') }}" 
                       class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Choose a Subscription Plan
                    </a>
                </div>
            @else
                <!-- Subscription Info Header -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900">
                                {{ ucfirst($activeSubscription->plan_type) }} Plan
                            </h2>
                            <p class="text-gray-600">
                                {{ $activeSubscription->certificates()->count() }} / {{ $activeSubscription->max_domains }} certificates used
                            </p>
                        </div>
                        <div class="text-right">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                                {{ $activeSubscription->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $activeSubscription->status }}
                            </span>
                            @if($activeSubscription->canAddDomain())
                                <div class="mt-2">
                                    <a href="{{ route('ssl.dashboard') }}" 
                                       class="inline-flex items-center px-3 py-1 text-sm font-medium text-blue-600 hover:text-blue-500">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                        Add Certificate
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="bg-gray-200 rounded-full h-2">
                            <div 
                                class="bg-blue-600 h-2 rounded-full"
                                style="width: {{ ($activeSubscription->certificates()->count() / $activeSubscription->max_domains) * 100 }}%"
                            ></div>
                        </div>
                    </div>
                </div>

                @php
                    $certificates = $activeSubscription->certificates()->latest()->get();
                @endphp

                @if($certificates->count() > 0)
                    <!-- Certificates Table -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900">Your SSL Certificates</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Domain</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Provider</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expires</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($certificates as $certificate)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">{{ $certificate->domain }}</div>
                                                @if($certificate->type === 'wildcard')
                                                    <div class="text-xs text-blue-600">Wildcard Certificate</div>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                    {{ $certificate->type ?? 'DV' }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $certificate->getProviderDisplayName() }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    @if($certificate->status === 'issued')
                                                        <svg class="h-5 w-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                        </svg>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">{{ $certificate->getStatusDisplayName() }}</span>
                                                    @elseif($certificate->status === 'pending_validation')
                                                        <svg class="h-5 w-5 text-yellow-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                        </svg>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">{{ $certificate->getStatusDisplayName() }}</span>
                                                    @elseif($certificate->status === 'processing')
                                                        <svg class="h-5 w-5 text-blue-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                        </svg>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">{{ $certificate->getStatusDisplayName() }}</span>
                                                    @else
                                                        <svg class="h-5 w-5 text-red-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.664-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                                        </svg>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">{{ $certificate->getStatusDisplayName() }}</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                @if($certificate->expires_at)
                                                    <div>{{ $certificate->expires_at->format('M d, Y') }}</div>
                                                    @if($certificate->isExpiringSoon())
                                                        <div class="text-xs text-red-600">Expires in {{ $certificate->getDaysUntilExpiration() }} days</div>
                                                    @else
                                                        <div class="text-xs text-gray-500">{{ $certificate->getDaysUntilExpiration() }} days left</div>
                                                    @endif
                                                @else
                                                    <span class="text-gray-400">N/A</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex space-x-2">
                                                    <a href="{{ route('ssl.certificates.show', $certificate) }}" 
                                                       class="text-blue-600 hover:text-blue-900">
                                                        View
                                                    </a>
                                                    @if($certificate->status === 'pending_validation')
                                                        <button onclick="showValidationInstructions({{ $certificate->id }})"
                                                                class="text-yellow-600 hover:text-yellow-900">
                                                            Validate
                                                        </button>
                                                    @endif
                                                    @if($certificate->status === 'issued')
                                                        <a href="{{ route('ssl.certificate.download', $certificate) }}" 
                                                           class="text-green-600 hover:text-green-900 flex items-center">
                                                            Download
                                                        </a>
                                                        @if($certificate->isExpiringSoon())
                                                            <button onclick="renewCertificate({{ $certificate->id }})"
                                                                    class="text-orange-600 hover:text-orange-900">
                                                                Renew
                                                            </button>
                                                        @endif
                                                    @endif
                                                    @if($certificate->status === 'failed')
                                                        <button onclick="retryCertificate({{ $certificate->id }})"
                                                                class="text-blue-600 hover:text-blue-900">
                                                            Retry
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <!-- No Certificates -->
                    <div class="text-center py-12">
                        <div class="mx-auto w-24 h-24 bg-blue-100 rounded-full flex items-center justify-center mb-6">
                            <svg class="w-12 h-12 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                        
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No certificates yet</h3>
                        <p class="text-gray-600 mb-6 max-w-md mx-auto">
                            You haven't issued any SSL certificates yet. Start by creating your first certificate.
                        </p>
                        
                        <a href="{{ route('ssl.dashboard') }}" 
                           class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Issue Your First Certificate
                        </a>
                    </div>
                @endif
            @endif
        @endauth
    </div>

    <script>
        function showValidationInstructions(certificateId) {
            // Redirect to validation instructions
            window.location.href = `/ssl/certificates/${certificateId}#validation`;
        }

        function renewCertificate(certificateId) {
            if (confirm('Are you sure you want to renew this certificate?')) {
                // Implement renewal logic
                fetch(`/api/ssl/certificates/${certificateId}/renew`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Certificate renewal initiated successfully');
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error renewing certificate: ' + error.message);
                });
            }
        }

        function retryCertificate(certificateId) {
            if (confirm('Are you sure you want to retry issuing this certificate?')) {
                // Implement retry logic
                fetch(`/api/ssl/certificates/${certificateId}`, {
                    method: 'PUT',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ action: 'retry' })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Certificate issuance retry initiated');
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error retrying certificate: ' + error.message);
                });
            }
        }
    </script>
</x-layouts.app>
