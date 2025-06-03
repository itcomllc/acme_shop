<div>
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-xl font-medium text-gray-900">Create Subscription</h3>
        <button wire:click="$dispatch('closeModal')" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
    </div>

    <form wire:submit.prevent="createSubscription">
        <!-- Plan Selection -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-3">Select Plan</label>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach ($plans as $key => $plan)
                    <div wire:click="$set('selectedPlan', '{{ $key }}')"
                        class="border rounded-lg p-4 cursor-pointer transition-colors {{ $selectedPlan === $key ? 'border-blue-600 bg-blue-50' : 'border-gray-200 hover:border-gray-300' }}">
                        <div class="text-center">
                            <h4 class="font-medium text-gray-900">{{ $plan['name'] ?? ucfirst($key) }}</h4>
                            <div class="text-lg font-bold text-blue-600 mt-1">
                                ${{ number_format(($plan['price'] ?? 0) / 100, 2) }}/mo
                            </div>
                            <p class="text-sm text-gray-600 mt-1">
                                {{ $plan['max_domains'] ?? $plan['domains'] ?? 1 }}
                                domain{{ ($plan['max_domains'] ?? $plan['domains'] ?? 1) > 1 ? 's' : '' }}
                            </p>
                            <p class="text-xs text-gray-500 mt-1">{{ $plan['certificate_type'] ?? 'DV' }} Certificate</p>
                        </div>
                    </div>
                @endforeach
            </div>
            @error('selectedPlan')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Domain Input -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-3">
                <label class="block text-sm font-medium text-gray-700">Domains</label>
                @if (method_exists($this, 'canAddDomain') ? $this->canAddDomain() : ($selectedPlan && isset($plans[$selectedPlan]) && count($domains) < ($plans[$selectedPlan]['max_domains'] ?? $plans[$selectedPlan]['domains'] ?? 1)))
                    <button type="button" wire:click="addDomain" class="text-blue-600 hover:text-blue-800 text-sm">
                        + Add Domain
                    </button>
                @endif
            </div>

            <div class="space-y-3">
                @foreach ($domains as $index => $domain)
                    <div class="flex items-center space-x-2">
                        <input type="text" wire:model="domains.{{ $index }}" placeholder="example.com"
                            class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('domains.' . $index) border-red-500 @enderror" />
                        @if (count($domains) > 1)
                            <button type="button" wire:click="removeDomain({{ $index }})"
                                class="text-red-600 hover:text-red-800 px-2 py-1 text-sm">
                                Remove
                            </button>
                        @endif
                    </div>
                    @error('domains.' . $index)
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                @endforeach
            </div>

            @if ($selectedPlan && isset($plans[$selectedPlan]))
                <p class="text-sm text-gray-500 mt-2">
                    You can add up to {{ $plans[$selectedPlan]['max_domains'] ?? $plans[$selectedPlan]['domains'] ?? 1 }}
                    domain{{ ($plans[$selectedPlan]['max_domains'] ?? $plans[$selectedPlan]['domains'] ?? 1) > 1 ? 's' : '' }} with this plan
                </p>
            @endif
        </div>

        <!-- Payment Section -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-3">Payment Information</label>
            <div class="border border-gray-300 rounded-md p-4">
                <!-- Square Web Payments SDK integration would go here -->
                <div id="card-container" class="mb-4"></div>
                <div id="payment-status" class="text-sm"></div>
                
                <!-- Placeholder for now -->
                <div class="bg-gray-50 p-4 rounded border-2 border-dashed border-gray-300 text-center">
                    <p class="text-gray-600">Square Web Payments SDK integration</p>
                    <p class="text-xs text-gray-500 mt-1">Card nonce: {{ $cardNonce ?: 'Not set' }}</p>
                </div>
            </div>
            @error('cardNonce')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        @if ($error)
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md">
                <p class="text-red-600 text-sm">{{ $error }}</p>
            </div>
        @endif

        <!-- Summary -->
        @if ($selectedPlan && isset($plans[$selectedPlan]))
            <div class="mb-6 p-4 bg-gray-50 rounded-md">
                <h4 class="font-medium text-gray-900 mb-2">Order Summary</h4>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">{{ ucfirst($selectedPlan) }} Plan</span>
                    <span class="font-medium">${{ number_format(($plans[$selectedPlan]['price'] ?? 0) / 100, 2) }}/month</span>
                </div>
                <div class="flex justify-between items-center mt-1">
                    <span class="text-sm text-gray-600">Domains: {{ count(array_filter($domains, fn($d) => trim($d) !== '')) }}</span>
                    <span class="text-sm text-gray-600">{{ $plans[$selectedPlan]['certificate_type'] ?? 'DV' }} Certificate</span>
                </div>
            </div>
        @endif

        <div class="flex justify-end space-x-3">
            <button type="button" wire:click="$dispatch('closeModal')"
                class="px-6 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md">
                Cancel
            </button>
            <button type="submit" 
                wire:loading.attr="disabled" 
                wire:target="createSubscription"
                {{ !$cardNonce ? 'disabled' : '' }}
                class="px-6 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 rounded-md flex items-center">
                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z">
                    </path>
                </svg>
                <span wire:loading.remove wire:target="createSubscription">Subscribe & Pay</span>
                <span wire:loading wire:target="createSubscription">Processing...</span>
            </button>
        </div>
    </form>
</div>

<script>
// Square Web Payments SDK integration placeholder
document.addEventListener('livewire:initialized', () => {
    // Initialize Square Web Payments SDK here
    // Set card nonce using: @this.setCardNonce(nonce)
});
</script>