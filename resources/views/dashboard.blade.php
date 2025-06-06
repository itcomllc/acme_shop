<x-layouts.app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <!-- Welcome Message -->
        <div class="mb-8">
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 dark:from-blue-700 dark:to-blue-900 text-white rounded-lg p-8 transition-colors duration-200">
                <h1 class="text-3xl font-bold mb-2">Welcome back, {{ Auth::user()->name }}!</h1>
                <p class="text-blue-100 text-lg">Manage your SSL certificates and subscriptions from your dashboard.</p>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid auto-rows-min gap-4 md:grid-cols-3 mb-8">
            <!-- SSL Dashboard -->
            <div class="relative overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 hover:shadow-lg transition-all duration-200">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">SSL Dashboard</h3>
                        <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">Manage certificates</p>
                    </div>
                    <div class="p-3 bg-blue-100 dark:bg-blue-900/50 rounded-lg">
                        <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="{{ route('ssl.dashboard') }}" class="inline-flex items-center text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium transition-colors duration-200">
                        Open SSL Dashboard
                        <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Certificates -->
            <div class="relative overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 hover:shadow-lg transition-all duration-200">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Certificates</h3>
                        <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">View & download</p>
                    </div>
                    <div class="p-3 bg-green-100 dark:bg-green-900/50 rounded-lg">
                        <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="{{ route('ssl.certificates.index') }}" class="inline-flex items-center text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300 font-medium transition-colors duration-200">
                        View Certificates
                        <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Billing -->
            <div class="relative overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 hover:shadow-lg transition-all duration-200">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Billing</h3>
                        <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">Manage subscription</p>
                    </div>
                    <div class="p-3 bg-yellow-100 dark:bg-yellow-900/50 rounded-lg">
                        <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="{{ route('ssl.billing.index') }}" class="inline-flex items-center text-yellow-600 dark:text-yellow-400 hover:text-yellow-800 dark:hover:text-yellow-300 font-medium transition-colors duration-200">
                        View Billing
                        <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Activity / Overview -->
        <div class="relative h-full flex-1 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 transition-colors duration-200">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Recent Activity</h2>
                    <a href="{{ route('ssl.dashboard') }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 text-sm font-medium transition-colors duration-200">
                        View all activity
                    </a>
                </div>

                @auth
                    @php
                        $user = Auth::user();
                        $subscription = $user->activeSubscription;
                    @endphp

                    @if($subscription)
                        <!-- User has subscription - show overview -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg transition-colors duration-200">
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ ucfirst($subscription->plan_type) }} Plan</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $subscription->certificates()->count() }}/{{ $subscription->max_domains }} certificates used</p>
                                </div>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $subscription->status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-400' }}">
                                    {{ $subscription->status }}
                                </span>
                            </div>

                            @php
                                $recentCertificates = $subscription->certificates()->latest()->take(3)->get();
                            @endphp

                            @if($recentCertificates->count() > 0)
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3">Recent Certificates</h3>
                                    <div class="space-y-3">
                                        @foreach($recentCertificates as $certificate)
                                            <div class="flex items-center justify-between p-3 border border-gray-200 dark:border-gray-600 rounded-lg transition-colors duration-200">
                                                <div>
                                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $certificate->domain }}</p>
                                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $certificate->created_at->diffForHumans() }}</p>
                                                </div>
                                                <span class="status-badge status-badge-{{ $certificate->status === 'issued' ? 'issued' : ($certificate->status === 'pending_validation' ? 'pending' : 'failed') }}">
                                                    {{ $certificate->getStatusDisplayName() }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <div class="text-center py-8">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                    </svg>
                                    <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-gray-100">No certificates yet</h3>
                                    <p class="mt-2 text-gray-600 dark:text-gray-400">Get started by creating your first SSL certificate.</p>
                                    <div class="mt-4">
                                        <a href="{{ route('ssl.dashboard') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-800 text-white rounded-lg transition-colors duration-200">
                                            Create Certificate
                                        </a>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @else
                        <!-- User has no subscription -->
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                            <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-gray-100">Welcome to SSL SaaS Platform</h3>
                            <p class="mt-2 text-gray-600 dark:text-gray-400 max-w-md mx-auto">Get started by choosing a subscription plan and issuing your first SSL certificate.</p>
                            <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
                                <a href="{{ route('ssl.dashboard') }}" class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-800 text-white rounded-lg font-medium transition-colors duration-200">
                                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    Get Started
                                </a>
                                <a href="{{ route('ssl.docs.index') }}" class="inline-flex items-center px-6 py-3 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg font-medium transition-colors duration-200">
                                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                    </svg>
                                    Documentation
                                </a>
                            </div>
                        </div>
                    @endif
                @endauth
            </div>
        </div>
    </div>
</x-layouts.app>