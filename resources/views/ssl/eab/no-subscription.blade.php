<x-layouts.app :title="__('EAB Credentials - No Subscription')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-6">
        <div class="mb-6">
            <flux:heading size="xl">{{ __('EAB Credentials Management') }}</flux:heading>
            <flux:subheading>{{ __('Manage your ACME External Account Binding credentials') }}</flux:subheading>
        </div>

        <!-- No Subscription Warning -->
        <div class="text-center py-12">
            <div class="mx-auto w-24 h-24 bg-yellow-100 rounded-full flex items-center justify-center mb-6">
                <svg class="w-12 h-12 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.664-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
            </div>
            
            <h3 class="text-lg font-medium text-gray-900 mb-2">No Active Subscription</h3>
            <p class="text-gray-600 mb-6 max-w-md mx-auto">
                You need an active SSL subscription to manage EAB credentials. EAB credentials are used to authenticate your ACME client with our certificate authority.
            </p>
            
            <div class="space-y-4">
                <a href="{{ route('ssl.dashboard') }}" 
                   class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Choose a Subscription Plan
                </a>
                
                <div class="text-sm text-gray-500">
                    <p>Already have a subscription? <a href="{{ route('ssl.dashboard') }}" class="text-blue-600 hover:text-blue-500">Check your dashboard</a></p>
                </div>
            </div>
        </div>

        <!-- Information about EAB -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mt-8">
            <h4 class="text-lg font-medium text-blue-900 mb-3">What are EAB Credentials?</h4>
            <div class="text-blue-800 space-y-2">
                <p>External Account Binding (EAB) credentials are used to authenticate your ACME client with our certificate authority. They consist of:</p>
                <ul class="list-disc list-inside ml-4 space-y-1">
                    <li><strong>MAC ID:</strong> A unique identifier for your account binding</li>
                    <li><strong>MAC Key:</strong> A secret key used to sign requests</li>
                </ul>
                <p class="mt-3">With an active subscription, you can generate and manage these credentials to use with ACME clients like Certbot, acme.sh, or Lego.</p>
            </div>
        </div>
    </div>
</x-layouts.app>
