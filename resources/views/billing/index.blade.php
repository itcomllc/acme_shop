@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">{{ __('Billing & Subscription') }}</h1>
        <p class="text-gray-600 mt-1">{{ __('Manage your subscription and payment methods') }}</p>
    </div>

    @auth
        @php
            $user = Auth::user();
            $subscription = $user->activeSubscription;
        @endphp

        @if($subscription)
            <!-- Current Subscription -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Current Subscription</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <div class="space-y-3">
                            <div>
                                <span class="text-sm text-gray-600">Plan:</span>
                                <span class="font-medium">{{ ucfirst($subscription->plan_type) }}</span>
                            </div>
                            <div>
                                <span class="text-sm text-gray-600">Status:</span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    {{ $subscription->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $subscription->status }}
                                </span>
                            </div>
                            <div>
                                <span class="text-sm text-gray-600">Price:</span>
                                <span class="font-medium">${{ number_format($subscription->price / 100, 2) }}/month</span>
                            </div>
                            <div>
                                <span class="text-sm text-gray-600">Domains:</span>
                                <span class="font-medium">{{ $subscription->certificates()->count() }}/{{ $subscription->max_domains }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="space-y-3">
                            <div>
                                <span class="text-sm text-gray-600">Next Billing Date:</span>
                                <span class="font-medium">
                                    {{ $subscription->next_billing_date ? $subscription->next_billing_date->format('M d, Y') : 'N/A' }}
                                </span>
                            </div>
                            <div>
                                <span class="text-sm text-gray-600">Last Payment:</span>
                                <span class="font-medium">
                                    {{ $subscription->last_payment_date ? $subscription->last_payment_date->format('M d, Y') : 'N/A' }}
                                </span>
                            </div>
                            @if($subscription->payment_failed_attempts > 0)
                                <div>
                                    <span class="text-sm text-red-600">Failed Attempts:</span>
                                    <span class="font-medium text-red-600">{{ $subscription->payment_failed_attempts }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex space-x-4">
                    <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                        Update Payment Method
                    </button>
                    <button class="bg-gray-100 hover:bg-gray-200 text-gray-900 px-4 py-2 rounded-lg">
                        Change Plan
                    </button>
                    <button class="bg-red-100 hover:bg-red-200 text-red-700 px-4 py-2 rounded-lg">
                        Cancel Subscription
                    </button>
                </div>
            </div>

            <!-- Payment History -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Payment History</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($subscription->payments()->latest()->take(10)->get() as $payment)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $payment->paid_at ? $payment->paid_at->format('M d, Y') : 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        ${{ number_format($payment->amount / 100, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            {{ $payment->status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $payment->status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $payment->square_invoice_id }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                        No payment history available.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <!-- No Subscription -->
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No active subscription</h3>
                <p class="mt-1 text-sm text-gray-500">Get started by choosing a plan that works for you.</p>
                <div class="mt-6">
                    <a href="{{ route('ssl.dashboard') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Choose Plan
                    </a>
                </div>
            </div>
        @endif
    @endauth
</div>
@endsection