<div>
    <button
        wire:click="showValidationInstructions"
        class="text-yellow-600 hover:text-yellow-900"
    >
        View Instructions
    </button>
    
    @if($showInstructions && $validationData)
        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">
                            Domain Validation Instructions for {{ $certificate->domain }}
                        </h3>
                        <button
                            wire:click="closeInstructions"
                            class="text-gray-400 hover:text-gray-600 text-2xl"
                        >
                            &times;
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        @foreach($validationData['validation_instructions'] as $instruction)
                            <div class="border rounded-lg p-4">
                                <h4 class="font-medium text-gray-900 mb-2">
                                    {{ $instruction['type'] }} Validation
                                </h4>
                                <p class="text-sm text-gray-600 mb-3">{{ $instruction['description'] }}</p>
                                
                                @if($instruction['type'] === 'HTTP')
                                    <div class="space-y-2">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">File Path:</label>
                                            <code class="block bg-gray-100 p-2 rounded text-sm">{{ $instruction['file_path'] }}</code>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">File Content:</label>
                                            <code class="block bg-gray-100 p-2 rounded text-sm break-all">{{ $instruction['file_content'] }}</code>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Verification URL:</label>
                                            <a href="{{ $instruction['verification_url'] }}" target="_blank" rel="noopener noreferrer" 
                                               class="text-blue-600 hover:text-blue-800 text-sm">
                                                {{ $instruction['verification_url'] }}
                                            </a>
                                        </div>
                                    </div>
                                @endif
                                
                                @if($instruction['type'] === 'DNS')
                                    <div class="space-y-2">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Record Name:</label>
                                            <code class="block bg-gray-100 p-2 rounded text-sm">{{ $instruction['record_name'] }}</code>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Record Value:</label>
                                            <code class="block bg-gray-100 p-2 rounded text-sm break-all">{{ $instruction['record_value'] }}</code>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">TTL:</label>
                                            <code class="block bg-gray-100 p-2 rounded text-sm">{{ $instruction['ttl'] }}</code>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    
                    <div class="mt-6 flex justify-end">
                        <button
                            wire:click="closeInstructions"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
