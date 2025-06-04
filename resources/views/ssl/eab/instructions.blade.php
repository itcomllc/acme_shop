<x-layouts.app :title="__('EAB Usage Instructions')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-6">
        <div class="mb-6">
            <flux:heading size="xl">{{ __('EAB Usage Instructions') }}</flux:heading>
            <flux:subheading>{{ __('How to use EAB credentials with ACME clients') }}</flux:subheading>
        </div>

        <!-- Back Button -->
        <div class="mb-6">
            <a href="{{ route('ssl.eab.index') }}" class="inline-flex items-center text-blue-600 hover:text-blue-500">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to EAB Management
            </a>
        </div>

        <!-- ACME Directory Information -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
            <h3 class="text-lg font-medium text-blue-900 mb-3">ACME Directory URL</h3>
            <p class="text-blue-800 mb-3">Use this URL as your ACME server endpoint:</p>
            <div class="bg-blue-100 p-3 rounded border font-mono text-sm break-all">
                {{ $acmeDirectoryUrl }}
            </div>
        </div>

        @if($activeCredential)
        <!-- Current Active Credentials -->
        <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-8">
            <h3 class="text-lg font-medium text-green-900 mb-3">Your Active EAB Credentials</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-green-800 mb-1">MAC ID</label>
                    <div class="bg-green-100 p-3 rounded border font-mono text-sm break-all">
                        {{ $activeCredential->mac_id }}
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-green-800 mb-1">Status</label>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                        Active
                    </span>
                </div>
            </div>
            <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
                <p class="text-sm text-yellow-800">
                    <strong>Security Note:</strong> The MAC Key is only shown once during generation. If you've lost it, you'll need to generate new credentials.
                </p>
            </div>
        </div>
        @else
        <!-- No Active Credentials -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-8">
            <h3 class="text-lg font-medium text-yellow-900 mb-3">No Active EAB Credentials</h3>
            <p class="text-yellow-800 mb-4">You need to generate EAB credentials first before you can use them with ACME clients.</p>
            <a href="{{ route('ssl.eab.index') }}" class="inline-flex items-center px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg">
                Generate EAB Credentials
            </a>
        </div>
        @endif

        <!-- Client Examples -->
        <div class="space-y-8">
            <h3 class="text-xl font-semibold text-gray-900">ACME Client Examples</h3>

            @foreach($clientExamples as $clientKey => $client)
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b">
                    <h4 class="text-lg font-medium text-gray-900">{{ $client['name'] }}</h4>
                    <p class="text-sm text-gray-600">{{ $client['description'] }}</p>
                </div>
                <div class="p-6">
                    @if(isset($client['command']))
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Command Example:</label>
                            <div class="bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto">
                                <code class="text-sm">{{ $client['command'] }}</code>
                            </div>
                        </div>
                        
                        <div class="text-sm text-gray-600">
                            <p><strong>Replace the following placeholders:</strong></p>
                            <ul class="list-disc list-inside mt-2 space-y-1">
                                <li><code>{{ACME_DIRECTORY}}</code> - {{ $acmeDirectoryUrl }}</li>
                                @if($activeCredential)
                                <li><code>{{MAC_ID}}</code> - {{ $activeCredential->mac_id }}</li>
                                <li><code>{{MAC_KEY}}</code> - Your MAC Key (shown during generation)</li>
                                @else
                                <li><code>{{MAC_ID}}</code> - Your MAC ID (generate credentials first)</li>
                                <li><code>{{MAC_KEY}}</code> - Your MAC Key (generate credentials first)</li>
                                @endif
                                <li><code>{{DOMAIN}}</code> - The domain you want to get a certificate for</li>
                            </ul>
                        </div>
                    @else
                        <div class="text-sm text-gray-600">
                            <p>{{ $client['description'] }}</p>
                            <p class="mt-2">Refer to your ACME client documentation for specific EAB configuration options.</p>
                        </div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

        <!-- General Information -->
        <div class="bg-gray-50 rounded-lg p-6 mt-8">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Important Notes</h3>
            <div class="space-y-3 text-sm text-gray-700">
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                    <p><strong>Security:</strong> Keep your MAC Key secure and never share it publicly. Treat it like a password.</p>
                </div>
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                    </svg>
                    <p><strong>Compatibility:</strong> Our ACME server supports RFC 8555 (ACME v2) and RFC 8739 (EAB).</p>
                </div>
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-yellow-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    <p><strong>Rate Limits:</strong> Be aware of rate limits when testing. Our ACME server implements standard rate limiting.</p>
                </div>
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-purple-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 3a1 1 0 00-1.447-.894L8.763 6H5a3 3 0 000 6h.28l1.771 5.316A1 1 0 008 18h1a1 1 0 001-1v-4.382l6.553 3.276A1 1 0 0018 15V3z" clip-rule="evenodd"></path>
                    </svg>
                    <p><strong>Support:</strong> If you encounter issues, check that your ACME client supports EAB and is configured correctly.</p>
                </div>
            </div>
        </div>

        <!-- Troubleshooting -->
        <div class="bg-red-50 border border-red-200 rounded-lg p-6 mt-8">
            <h3 class="text-lg font-medium text-red-900 mb-4">Troubleshooting</h3>
            <div class="space-y-4 text-sm text-red-800">
                <div>
                    <h4 class="font-medium">Authentication Failed</h4>
                    <ul class="list-disc list-inside mt-1 ml-4">
                        <li>Verify your MAC ID and MAC Key are correct</li>
                        <li>Ensure your EAB credentials are still active</li>
                        <li>Check that your ACME client supports EAB (RFC 8739)</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-medium">Connection Issues</h4>
                    <ul class="list-disc list-inside mt-1 ml-4">
                        <li>Verify the ACME directory URL is correct</li>
                        <li>Check your network connectivity</li>
                        <li>Ensure your firewall allows HTTPS connections</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-medium">Rate Limiting</h4>
                    <ul class="list-disc list-inside mt-1 ml-4">
                        <li>Wait before retrying failed requests</li>
                        <li>Use staging environment for testing when available</li>
                        <li>Implement exponential backoff in your automation</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
