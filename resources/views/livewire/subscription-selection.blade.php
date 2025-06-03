<div class="min-h-screen bg-gray-50 py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">
                Choose Your SSL Plan
            </h1>
            <p class="text-xl text-gray-600">
                Automated SSL certificates with ACME validation and Square billing
            </p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            @foreach($plans as $key => $plan)
                <div class="relative bg-white rounded-lg shadow-md overflow-hidden {{ $key === 'professional' ? 'ring-2 ring-blue-600' : '' }}">
                    @if($key === 'professional')
                        <div class="absolute top-0 left-0 right-0 bg-blue-600 text-white text-center py-1 text-sm font-medium">
                            Most Popular
                        </div>
                    @endif
                    
                    <div class="p-6">
                        <div class="text-center">
                            <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ $plan['name'] }}</h3>
                            <div class="text-4xl font-bold text-blue-600 mb-1">{{ $plan['price'] }}</div>
                            <p class="text-gray-600 mb-4">{{ $plan['domains'] }} domain{{ $plan['domains'] > 1 ? 's' : '' }}</p>
                            <p class="text-sm text-gray-500 mb-6">{{ $plan['certificate_type'] }}</p>
                        </div>
                        
                        <ul class="space-y-3 mb-8">
                            @foreach($plan['features'] as $feature)
                                <li class="flex items-center">
                                    <svg class="h-4 w-4 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span class="text-gray-700">{{ $feature }}</span>
                                </li>
                            @endforeach
                        </ul>
                        
                        <button
                            wire:click="$emit('openSubscriptionModal')"
                            class="w-full py-3 px-4 rounded-lg font-medium transition-colors {{ $key === 'professional' ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-900' }}"
                        >
                            <svg class="h-4 w-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                            </svg>
                            Choose {{ $plan['name'] }}
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>