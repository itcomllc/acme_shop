<x-layouts.app :title="__('SSL Subscriptions')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-6">
        <div class="mb-6">
            <flux:heading size="xl">{{ __('SSL Subscription Plans') }}</flux:heading>
            <flux:subheading>{{ __('Choose the right SSL certificate plan for your needs') }}</flux:subheading>
        </div>

        @auth
            @php
                $user = Auth::user();
                $activeSubscription = $user->activeSubscription;
            @endphp

            @if($activeSubscription)
                <!-- Current Active Subscription -->
                <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-8">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-8 w-8 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-lg font-medium text-green-800">You have an active subscription!</h3>
                            <div class="mt-2 text-sm text-green-700">
                                <p>Current Plan: <strong>{{ ucfirst($activeSubscription->plan_type) }}</strong></p>
                                <p>Status: <strong>{{ ucfirst($activeSubscription->status) }}</strong></p>
                                <p>Domains: {{ $activeSubscription->certificates()->count() }}/{{ $activeSubscription->max_domains }}</p>
                            </div>
                            <div class="mt-4">
                                <a href="{{ route('ssl.dashboard') }}" 
                                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-green-700 bg-green-100 hover:bg-green-200">
                                    Go to SSL Dashboard
                                </a>
                                <a href="{{ route('ssl.billing.index') }}" 
                                   class="ml-3 inline-flex items-center px-4 py-2 border border-green-300 text-sm font-medium rounded-md text-green-700 bg-green-50 hover:bg-green-100">
                                    Manage Billing
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <!-- No Active Subscription -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-8 w-8 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-lg font-medium text-blue-800">Get started with SSL certificates</h3>
                            <div class="mt-2 text-sm text-blue-700">
                                <p>Choose a subscription plan below to start issuing SSL certificates for your domains.</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endauth

        <!-- Pricing Plans -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Basic Plan -->
            <div class="relative bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <div class="text-center">
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Basic SSL</h3>
                        <div class="text-4xl font-bold text-blue-600 mb-1">$9.99</div>
                        <p class="text-gray-600 mb-4">per month</p>
                        <p class="text-sm text-gray-500 mb-6">Perfect for personal websites</p>
                    </div>
                    
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-center">
                            <svg class="h-4 w-4 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-gray-700">1 SSL Certificate</span>
                        </li>
                        <li class="flex items-center">
                            <svg class="h-4 w-4 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-gray-700">Domain Validation (DV)</span>
                        </li>
                        <li class="flex items-center">
                            <svg class="h-4 w-4 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-gray-700">ACME Automation</span>
                        </li>
                        <li class="flex items-center">
                            <svg class="h-4 w-4 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-gray-700">99.9% Uptime SLA</span>
                        </li>
                        <li class="flex items-center">
                            <svg class="h-4 w-4 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-gray-700">Email Support</span>
                        </li>
                    </ul>
                    
                    <button onclick="selectPlan('basic')" 
                            class="w-full py-3 px-4 rounded-lg font-medium transition-colors bg-gray-100 hover:bg-gray-200 text-gray-900">
                        Choose Basic
                    </button>
                </div>
            </div>

            <!-- Professional Plan -->
            <div class="relative bg-white rounded-lg shadow-md overflow-hidden ring-2 ring-blue-600">
                <div class="absolute top-0 left-0 right-0 bg-blue-600 text-white text-center py-1 text-sm font-medium">
                    Most Popular
                </div>
                
                <div class="p-6 pt-10">
                    <div class="text-center">
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Professional SSL</h3>
                        <div class="text-4xl font-bold text-blue-600 mb-1">$29.99</div>
                        <p class="text-gray-600 mb-4">per month</p>
                        <p class="text-sm text-gray-500 mb-6">Great for small businesses</p>
                    </div>
                    
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-center">
                            <svg class="h-4 w-4 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-gray-700">Up to 5 SSL Certificates</span>
                        </li>
                        <li class="flex items-center">
                            <svg class="h-4 w-4 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-gray-700">Organization Validation (OV)</span>
                        </li>
                        <li class="flex items-center">
                            <svg class="h-4 w-4 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-gray-700">ACME Automation</span>
                        </li>
                        <li class="flex items-center">
                            <svg class="h-4 w-4 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-gray-700">Priority Support</span>
                        </li>
                        <li class="flex items-center">
                            <svg class="h-4 w-4 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-gray-700">99.9% Uptime SLA</span>
                        </li>
                    </ul>
                    
                    <button onclick="selectPlan('professional')" 
                            class="w-full py-3 px-4 rounded-lg font-medium transition-colors bg-blue-600 hover:bg-blue-700 text-white">
                        Choose Professional
                    </button>
                </div>
            </div>

            <!-- Enterprise Plan -->
            <div class="relative bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <div class="text-center">
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Enterprise SSL</h3>
                        <div class="text-4xl font-bold text-blue-600 mb-1">$99.99</div>
                        <p class="text-gray-600 mb-4">per month</p>
                        <p class="text-sm text-gray-500 mb-6">For large organizations</p>
                    </div>
                    
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-center">
                            <svg class="h-4 w-4 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-gray-700">Up to 100 SSL Certificates</span>
                        </li>
                        <li class="flex items-center">
                            <svg class="h-4 w-4 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-gray-700">Extended Validation (EV)</span>
                        </li>
                        <li class="flex items-center">
                            <svg class="h-4 w-4 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-gray-700">ACME Automation</span>
                        </li>
                        <li class="flex items-center">
                            <svg class="h-4 w-4 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-gray-700">Dedicated Support</span>
                        </li>
                        <li class="flex items-center">
                            <svg class="h-4 w-4 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-gray-700">Custom Integrations</span>
                        </li>
                    </ul>
                    
                    <button onclick="selectPlan('enterprise')" 
                            class="w-full py-3 px-4 rounded-lg font-medium transition-colors bg-gray-100 hover:bg-gray-200 text-gray-900">
                        Choose Enterprise
                    </button>
                </div>
            </div>
        </div>

        <!-- Features Comparison -->
        <div class="mt-12">
            <h3 class="text-xl font-semibold text-gray-900 mb-6">Feature Comparison</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Feature</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Basic</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Professional</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Enterprise</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">SSL Certificates</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">1</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">5</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">100</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Certificate Type</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">DV</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">OV</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">EV</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">ACME Automation</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                <svg class="h-5 w-5 text-green-500 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                <svg class="h-5 w-5 text-green-500 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                <svg class="h-5 w-5 text-green-500 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Auto-Renewal</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                <svg class="h-5 w-5 text-green-500 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                <svg class="h-5 w-5 text-green-500 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                <svg class="h-5 w-5 text-green-500 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Support Level</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">Email</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">Priority</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">Dedicated</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- FAQ Section -->
        <div class="mt-12">
            <h3 class="text-xl font-semibold text-gray-900 mb-6">Frequently Asked Questions</h3>
            <div class="space-y-4">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h4 class="text-lg font-medium text-gray-900 mb-2">What is ACME automation?</h4>
                    <p class="text-gray-600">ACME (Automatic Certificate Management Environment) allows you to automatically obtain, install, and renew SSL certificates using clients like Certbot, acme.sh, or Lego.</p>
                </div>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h4 class="text-lg font-medium text-gray-900 mb-2">Can I upgrade or downgrade my plan?</h4>
                    <p class="text-gray-600">Yes, you can change your subscription plan at any time. Changes will be prorated and take effect immediately.</p>
                </div>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h4 class="text-lg font-medium text-gray-900 mb-2">What's the difference between DV, OV, and EV certificates?</h4>
                    <p class="text-gray-600">DV (Domain Validation) verifies domain ownership, OV (Organization Validation) also verifies business identity, and EV (Extended Validation) provides the highest level of verification with green address bar display.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Plan Selection Modal -->
    <div id="planSelectionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Subscribe to <span id="selectedPlanName"></span></h3>
                    <button onclick="closePlanModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>
                
                <div class="mb-6">
                    <p class="text-gray-600">You'll be redirected to complete your subscription setup with payment information.</p>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button onclick="closePlanModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md">
                        Cancel
                    </button>
                    <button onclick="proceedWithPlan()"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md">
                        Continue
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedPlan = null;

        function selectPlan(planType) {
            selectedPlan = planType;
            const planNames = {
                'basic': 'Basic SSL',
                'professional': 'Professional SSL', 
                'enterprise': 'Enterprise SSL'
            };
            
            document.getElementById('selectedPlanName').textContent = planNames[planType];
            document.getElementById('planSelectionModal').classList.remove('hidden');
        }

        function closePlanModal() {
            document.getElementById('planSelectionModal').classList.add('hidden');
            selectedPlan = null;
        }

        function proceedWithPlan() {
            if (selectedPlan) {
                // Redirect to SSL dashboard with plan selection
                window.location.href = `{{ route('ssl.dashboard') }}?plan=${selectedPlan}`;
            }
        }
    </script>
</x-layouts.app>