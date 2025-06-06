<div>
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-medium text-gray-900">Issue New Certificate</h3>
        <button wire:click="$dispatch('closeModal')" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
    </div>
    
    <form wire:submit.prevent="createCertificate">
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Domain Name
            </label>
            <input
                type="text"
                wire:model="domain"
                placeholder="example.com"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('domain') border-red-500 @enderror"
                required
            />
            @error('domain')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        
        @if($error)
            <div class="mb-4 text-red-600 text-sm">{{ $error }}</div>
        @endif
        
        <div class="flex justify-end space-x-3">
            <button
                type="button"
                wire:click="$dispatch('closeModal')"
                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md"
            >
                Cancel
            </button>
            <button
                type="submit"
                wire:loading.attr="disabled"
                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 rounded-md"
            >
                <span wire:loading.remove>Create Certificate</span>
                <span wire:loading>Creating...</span>
            </button>
        </div>
    </form>
</div>