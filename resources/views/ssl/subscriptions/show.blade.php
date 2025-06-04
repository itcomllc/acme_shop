<x-layouts.app :title="__('Subscription Details')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-6">
        <div class="mb-6">
            <flux:heading size="xl">{{ __('Subscription Details') }}</flux:heading>
            <flux:subheading>{{ __('Manage your SSL subscription and certificates') }}</flux:subheading>
        </div>

        <!-- Back Button -->
        <div class="mb-6">
            <a href="{{ route('ssl.subscriptions.index') }}" class="inline-flex items-center text-blue-600 hover:text-blue-500">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Subscriptions
            </a>
        </div>

        @auth
            @php
                $user = Auth::user();
                $subscription = $user->activeSubscription;
                
                // Mock subscription data for display
                if (!$subscription) {
                    $subscription = (object) [
                        'id' => 1,
                        'plan_type' => 'professional',
                        'status' => 'active',
                        'max_domains' => 5,
                        'price' => 2999,
                        'billing_period' => 'MONTHLY',
                        'next_billing_date' => now()->addMonth(),
                        'last_payment_date' => now()->subMonth(),
                        'created_at' => now()->subMonths(3),
                        'certificates_issued' => 3,
                        'certificates_renewed' => 1,
                        'certificates_failed' => 0
                    ];
                }
                
                // Mock certificates
                $certificates = collect([
                    (object) [
                        'id' => 1,
                        'domain' => 'example.com',
                        'status' => 'issued',
                        'expires_at' => now()->addDays(75),
                        'created_at' => now()->subDays(15)
                    ],
                    (object) [
                        'id' => 2,
                        'domain' => 'api.example.com',
                        'status' => 'issued', 
                        'expires_at' => now()->addDays(60),
                        'created_at' => now()->subDays(30)
                    ],
                    (object) [
                        'id' => 3,
                        'domain' => 'staging.example.com',
                        'status' => 'pending_validation',
                        'expires_at' => null,
                        'created_at' => now()->subHours(2)
                    ]
                ]);
            @endphp

            @if(!$subscription)
                <!-- No Subscription -->
                <div class="text-center py-12">
                    <div class="mx-auto w-24 h-24 bg-yellow-100 rounded-full flex items-center justify-center mb-6">
                        <svg class="w-12 h-12 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.664-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                    
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Active Subscription</h3>
                    <p class="text-gray-600 mb-6 max-w-md mx-auto">
                        You don't have an active subscription to view details for.
                    </p>
                    
                    <a href="{{ route('ssl.subscriptions.index') }}" 
                       class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        Choose a Subscription Plan
                    </a>
                </div>
            @else
                <!-- Subscription Overview -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="px-6 py-4 bg-gray-50 border-b">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-900">
                                {{ ucfirst($subscription->plan_type) }} Plan
                            </h2>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                                {{ $subscription->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ ucfirst($subscription->status) }}
                            </span>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <h3 class="text-sm font-medium text-gray-500 mb-1">Monthly Price</h3>
                                <p class="text-2xl font-bold text-gray-900">${{ number_format($subscription->price / 100, 2) }}</p>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500 mb-1">Domain Limit</h3>
                                <p class="text-2xl font-bold text-gray-900">{{ $subscription->max_domains }}</p>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500 mb-1">Domains Used</h3>
                                <p class="text-2xl font-bold text-gray-900">{{ $certificates->count() }}/{{ $subscription->max_domains }}</p>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <div class="bg-gray-200 rounded-full h-2">
                                <div 
                                    class="bg-blue-600 h-2 rounded-full"
                                    style="width: {{ ($certificates->count() / $subscription->max_domains) * 100 }}%"
                                ></div>
                            </div>
                            <p class="text-sm text-gray-600 mt-2">
                                You've used {{ $certificates->count() }} of {{ $subscription->max_domains }} available certificates
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Billing Information -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="px-6 py-4 bg-gray-50 border-b">
                        <h2 class="text-lg font-semibold text-gray-900">Billing Information</h2>
                    </div>
                    
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h3 class="text-sm font-medium text-gray-500 mb-1">Billing Period</h3>
                                <p class="text-sm text-gray-900">
                                    @switch($subscription->billing_period)
                                        @case('MONTHLY')
                                            Monthly
                                            @break
                                        @case('QUARTERLY')
                                            Quarterly
                                            @break
                                        @case('ANNUALLY')
                                            Annually
                                            @break
                                        @default
                                            {{ ucfirst(strtolower($subscription->billing_period)) }}
                                    @endswitch
                                </p>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500 mb-1">Next Billing Date</h3>
                                <p class="text-sm text-gray-900">{{ $subscription->next_billing_date->format('M d, Y') }}</p>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500 mb-1">Last Payment</h3>
                                <p class="text-sm text-gray-900">{{ $subscription->last_payment_date ? $subscription->last_payment_date->format('M d, Y') : 'N/A' }}</p>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500 mb-1">Created</h3>
                                <p class="text-sm text-gray-900">{{ $subscription->created_at->format('M d, Y') }}</p>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <a href="{{ route('ssl.billing.index') }}" 
                               class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                View Billing Details
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex items-center">
                            <div class="p-2 rounded-lg text-green-600 bg-green-100">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Certificates Issued</p>
                                <p class="text-2xl font-bold text-gray-900">{{ $subscription->certificates_issued }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex items-center">
                            <div class="p-2 rounded-lg text-blue-600 bg-blue-100">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Certificates Renewed</p>
                                <p class="text-2xl font-bold text-gray-900">{{ $subscription->certificates_renewed }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex items-center">
                            <div class="p-2 rounded-lg text-red-600 bg-red-100">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.664-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Failed Certificates</p>
                                <p class="text-2xl font-bold text-gray-900">{{ $subscription->certificates_failed }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Current Certificates -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="px-6 py-4 bg-gray-50 border-b">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-900">Current Certificates</h2>
                            @if($certificates->count() < $subscription->max_domains)
                                <a href="{{ route('ssl.dashboard') }}" 
                                   class="inline-flex items-center px-3 py-1 text-sm font-medium text-blue-600 hover:text-blue-500">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    Add Certificate
                                </a>
                            @endif
                        </div>
                    </div>
                    
                    @if($certificates->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Domain</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expires</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($certificates as $certificate)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">{{ $certificate->domain }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($certificate->status === 'issued')
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Issued</span>
                                                @elseif($certificate->status === 'pending_validation')
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Pending Validation</span>
                                                @elseif($certificate->status === 'processing')
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Processing</span>
                                                @else
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">{{ ucfirst($certificate->status) }}</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                @if($certificate->expires_at)
                                                    {{ $certificate->expires_at->format('M d, Y') }}
                                                    @if($certificate->expires_at->lessThan(now()->addDays(30)))
                                                        <div class="text-xs text-red-600">Expires soon</div>
                                                    @endif
                                                @else
                                                    <span class="text-gray-400">N/A</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $certificate->created_at->format('M d, Y') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex space-x-2">
                                                    <a href="{{ route('ssl.certificates.show', $certificate->id) }}" 
                                                       class="text-blue-600 hover:text-blue-900">View</a>
                                                    @if($certificate->status === 'issued')
                                                        <a href="{{ route('ssl.certificate.download', $certificate->id) }}" 
                                                           class="text-green-600 hover:text-green-900">Download</a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-6 text-center">
                            <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No certificates yet</h3>
                            <p class="text-gray-600 mb-4">Start by creating your first SSL certificate.</p>
                            <a href="{{ route('ssl.dashboard') }}" 
                               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Create Certificate
                            </a>
                        </div>
                    @endif
                </div>

                <!-- Subscription Actions -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b">
                        <h2 class="text-lg font-semibold text-gray-900">Subscription Actions</h2>
                    </div>
                    
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <button onclick="showUpgradeModal()" 
                                    class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                                </svg>
                                Upgrade Plan
                            </button>
                            
                            <button onclick="showBillingModal()" 
                                    class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                </svg>
                                Update Payment
                            </button>
                            
                            <button onclick="showCancelModal()" 
                                    class="inline-flex items-center justify-center px-4 py-2 border border-red-300 text-sm font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                Cancel Subscription
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        @endauth
    </div>

    <!-- Modal Placeholders -->
    <div id="upgradeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Upgrade Plan</h3>
                <p class="text-gray-600 mb-4">Plan upgrade functionality will be available soon.</p>
                <div class="flex justify-end">
                    <button onclick="closeUpgradeModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showUpgradeModal() {
            document.getElementById('upgradeModal').classList.remove('hidden');
        }

        function closeUpgradeModal() {
            document.getElementById('upgradeModal').classList.add('hidden');
        }

        function showBillingModal() {
            // Redirect to billing page
            window.location.href = '{{ route("ssl.billing.index") }}';
        }

        function showCancelModal() {
            if (confirm('Are you sure you want to cancel your subscription? This action cannot be undone and will immediately revoke all your SSL certificates.')) {
                // Handle cancellation
                alert('Cancellation functionality will be available soon.');
            }
        }
    </script>
</x-layouts.app>
