<x-layouts.app :title="__('Certificate Details')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-6">
        <div class="mb-6">
            <flux:heading size="xl">{{ __('Certificate Details') }}</flux:heading>
            <flux:subheading>{{ __('View certificate information and manage validation') }}</flux:subheading>
        </div>

        <!-- Back Button -->
        <div class="mb-6">
            <a href="{{ route('ssl.certificates.index') }}" class="inline-flex items-center text-blue-600 hover:text-blue-500">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Certificates
            </a>
        </div>

        @auth
            @php
                $user = Auth::user();
                $activeSubscription = $user->activeSubscription;
                
                // For demonstration purposes, let's create a mock certificate
                // In the actual implementation, this would come from the controller
                $certificate = (object) [
                    'id' => 1,
                    'domain' => 'example.com',
                    'type' => 'DV',
                    'provider' => 'gogetssl',
                    'status' => 'pending_validation',
                    'expires_at' => now()->addDays(90),
                    'issued_at' => null,
                    'created_at' => now()->subHours(2),
                ];
                
                // Mock validation records
                $validationRecords = [
                    (object) [
                        'type' => 'http-01',
                        'token' => 'token123',
                        'key_authorization' => 'keyauth123',
                        'status' => 'pending'
                    ]
                ];
            @endphp

            @if(!$activeSubscription)
                <!-- No Active Subscription -->
                <div class="text-center py-12">
                    <div class="mx-auto w-24 h-24 bg-yellow-100 rounded-full flex items-center justify-center mb-6">
                        <svg class="w-12 h-12 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.664-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                    
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Active Subscription</h3>
                    <p class="text-gray-600 mb-6 max-w-md mx-auto">
                        You need an active SSL subscription to view certificate details.
                    </p>
                    
                    <a href="{{ route('ssl.subscriptions.index') }}" 
                       class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        Choose a Subscription Plan
                    </a>
                </div>
            @else
                <!-- Certificate Information -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="px-6 py-4 bg-gray-50 border-b">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-900">Certificate Information</h2>
                            <div class="flex items-center space-x-2">
                                @if($certificate->status === 'issued')
                                    <svg class="h-5 w-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">Issued</span>
                                @elseif($certificate->status === 'pending_validation')
                                    <svg class="h-5 w-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">Pending Validation</span>
                                @elseif($certificate->status === 'processing')
                                    <svg class="h-5 w-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">Processing</span>
                                @else
                                    <svg class="h-5 w-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.664-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                    </svg>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">{{ ucfirst($certificate->status) }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h3 class="text-sm font-medium text-gray-500 mb-1">Domain</h3>
                                <p class="text-lg font-medium text-gray-900">{{ $certificate->domain }}</p>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500 mb-1">Certificate Type</h3>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    {{ $certificate->type }}
                                </span>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500 mb-1">Provider</h3>
                                <p class="text-sm text-gray-900">
                                    @switch($certificate->provider)
                                        @case('gogetssl')
                                            GoGetSSL
                                            @break
                                        @case('google_certificate_manager')
                                            Google Certificate Manager
                                            @break
                                        @case('lets_encrypt')
                                            Let's Encrypt
                                            @break
                                        @default
                                            {{ ucfirst($certificate->provider) }}
                                    @endswitch
                                </p>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500 mb-1">Created</h3>
                                <p class="text-sm text-gray-900">{{ $certificate->created_at->format('M d, Y g:i A') }}</p>
                            </div>
                            @if($certificate->issued_at)
                            <div>
                                <h3 class="text-sm font-medium text-gray-500 mb-1">Issued</h3>
                                <p class="text-sm text-gray-900">{{ $certificate->issued_at->format('M d, Y g:i A') }}</p>
                            </div>
                            @endif
                            @if($certificate->expires_at)
                            <div>
                                <h3 class="text-sm font-medium text-gray-500 mb-1">Expires</h3>
                                <p class="text-sm text-gray-900">{{ $certificate->expires_at->format('M d, Y g:i A') }}</p>
                                @if($certificate->expires_at->lessThan(now()->addDays(30)))
                                    <p class="text-xs text-red-600 mt-1">Expires in {{ now()->diffInDays($certificate->expires_at) }} days</p>
                                @endif
                            </div>
                            @endif
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="mt-6 flex space-x-3">
                            @if($certificate->status === 'issued')
                                <a href="{{ route('ssl.certificate.download', $certificate->id) }}" 
                                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    Download Certificate
                                </a>
                                @if($certificate->expires_at && $certificate->expires_at->lessThan(now()->addDays(30)))
                                    <button onclick="renewCertificate({{ $certificate->id }})"
                                            class="inline-flex items-center px-4 py-2 border border-orange-300 text-sm font-medium rounded-md text-orange-700 bg-orange-50 hover:bg-orange-100">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                        </svg>
                                        Renew Certificate
                                    </button>
                                @endif
                            @elseif($certificate->status === 'failed')
                                <button onclick="retryCertificate({{ $certificate->id }})"
                                        class="inline-flex items-center px-4 py-2 border border-blue-300 text-sm font-medium rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    Retry Issuance
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                @if($certificate->status === 'pending_validation')
                <!-- Validation Section -->
                <div id="validation" class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="px-6 py-4 bg-yellow-50 border-b border-yellow-200">
                        <div class="flex items-center">
                            <svg class="h-5 w-5 text-yellow-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            <h2 class="text-lg font-semibold text-yellow-900">Domain Validation Required</h2>
                        </div>
                        <p class="text-sm text-yellow-800 mt-1">Complete domain validation to activate your SSL certificate.</p>
                    </div>
                    
                    <div class="p-6">
                        @foreach($validationRecords as $record)
                            <div class="border rounded-lg p-4 mb-4 last:mb-0">
                                <h4 class="font-medium text-gray-900 mb-3">
                                    {{ $record->type === 'http-01' ? 'HTTP' : 'DNS' }} Validation
                                </h4>
                                
                                @if($record->type === 'http-01')
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">1. Create the following file on your web server:</label>
                                            <div class="bg-gray-100 p-3 rounded border">
                                                <p class="text-sm font-medium text-gray-900">File Path:</p>
                                                <code class="block text-sm text-gray-800 mt-1">/.well-known/acme-challenge/{{ $record->token }}</code>
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">2. File Content:</label>
                                            <div class="bg-gray-100 p-3 rounded border">
                                                <code class="block text-sm text-gray-800 break-all">{{ $record->key_authorization }}</code>
                                                <button onclick="copyToClipboard('{{ $record->key_authorization }}')" 
                                                        class="mt-2 text-xs text-blue-600 hover:text-blue-500">
                                                    Copy to clipboard
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">3. Verify the file is accessible:</label>
                                            <div class="bg-blue-50 p-3 rounded border border-blue-200">
                                                <p class="text-sm text-blue-800">
                                                    <strong>Verification URL:</strong>
                                                </p>
                                                <a href="http://{{ $certificate->domain }}/.well-known/acme-challenge/{{ $record->token }}" 
                                                   target="_blank" rel="noopener noreferrer"
                                                   class="text-sm text-blue-600 hover:text-blue-500 break-all">
                                                    http://{{ $certificate->domain }}/.well-known/acme-challenge/{{ $record->token }}
                                                </a>
                                            </div>
                                        </div>
                                        
                                        <div class="bg-yellow-50 p-3 rounded border border-yellow-200">
                                            <h5 class="text-sm font-medium text-yellow-800 mb-1">Important Notes:</h5>
                                            <ul class="text-sm text-yellow-700 list-disc list-inside space-y-1">
                                                <li>The file must be accessible via HTTP (not HTTPS)</li>
                                                <li>Ensure your web server serves the file with Content-Type: text/plain</li>
                                                <li>The file should not contain any additional content or formatting</li>
                                            </ul>
                                        </div>
                                    </div>
                                @elseif($record->type === 'dns-01')
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">1. Add the following DNS TXT record:</label>
                                            <div class="bg-gray-100 p-3 rounded border space-y-2">
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900">Record Name:</p>
                                                    <code class="block text-sm text-gray-800">_acme-challenge.{{ $certificate->domain }}</code>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900">Record Value:</p>
                                                    <code class="block text-sm text-gray-800 break-all">{{ base64_encode(hash('sha256', $record->key_authorization, true)) }}</code>
                                                    <button onclick="copyToClipboard('{{ base64_encode(hash('sha256', $record->key_authorization, true)) }}')" 
                                                            class="mt-2 text-xs text-blue-600 hover:text-blue-500">
                                                        Copy to clipboard
                                                    </button>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900">TTL:</p>
                                                    <code class="block text-sm text-gray-800">300 (or your DNS provider's minimum)</code>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="bg-yellow-50 p-3 rounded border border-yellow-200">
                                            <h5 class="text-sm font-medium text-yellow-800 mb-1">Important Notes:</h5>
                                            <ul class="text-sm text-yellow-700 list-disc list-inside space-y-1">
                                                <li>DNS propagation can take up to 24 hours</li>
                                                <li>You can verify the record using: <code>dig TXT _acme-challenge.{{ $certificate->domain }}</code></li>
                                                <li>Some DNS providers may require you to enter only "_acme-challenge" as the record name</li>
                                            </ul>
                                        </div>
                                    </div>
                                @endif
                                
                                <div class="mt-4 pt-4 border-t">
                                    <button onclick="checkValidation({{ $certificate->id }})"
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        Check Validation
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Activity Log -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b">
                        <h2 class="text-lg font-semibold text-gray-900">Activity Log</h2>
                    </div>
                    <div class="p-6">
                        <div class="flow-root">
                            <ul class="-mb-8">
                                <li>
                                    <div class="relative pb-8">
                                        <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200"></span>
                                        <div class="relative flex space-x-3">
                                            <div>
                                                <span class="h-8 w-8 rounded-full bg-blue-500 flex items-center justify-center ring-8 ring-white">
                                                    <svg class="h-4 w-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                    </svg>
                                                </span>
                                            </div>
                                            <div class="min-w-0 flex-1 pt-1.5">
                                                <div>
                                                    <p class="text-sm text-gray-500">
                                                        Certificate order created
                                                        <span class="font-medium text-gray-900">{{ $certificate->created_at->diffForHumans() }}</span>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                @if($certificate->status === 'pending_validation')
                                <li>
                                    <div class="relative">
                                        <div class="relative flex space-x-3">
                                            <div>
                                                <span class="h-8 w-8 rounded-full bg-yellow-500 flex items-center justify-center ring-8 ring-white">
                                                    <svg class="h-4 w-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                                    </svg>
                                                </span>
                                            </div>
                                            <div class="min-w-0 flex-1 pt-1.5">
                                                <div>
                                                    <p class="text-sm text-gray-500">
                                                        Waiting for domain validation
                                                        <span class="font-medium text-gray-900">Current status</span>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                @endif
                            </ul>
                        </div>
                    </div>
                </div>
            @endif
        @endauth
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Simple success feedback
                const button = event.target;
                const originalText = button.textContent;
                button.textContent = 'Copied!';
                button.classList.add('text-green-600');
                setTimeout(() => {
                    button.textContent = originalText;
                    button.classList.remove('text-green-600');
                }, 2000);
            }).catch(function(err) {
                console.error('Failed to copy text: ', err);
                alert('Failed to copy to clipboard');
            });
        }

        function checkValidation(certificateId) {
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<svg class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Checking...';
            button.disabled = true;

            fetch(`/api/ssl/certificates/${certificateId}/validation`, {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.status === 'validated') {
                        alert('Validation successful! Your certificate will be issued shortly.');
                        window.location.reload();
                    } else {
                        alert('Validation not yet complete. Please ensure your validation method is properly configured.');
                    }
                } else {
                    alert('Validation check failed: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error checking validation: ' + error.message);
            })
            .finally(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }

        function renewCertificate(certificateId) {
            if (confirm('Are you sure you want to renew this certificate?')) {
                fetch(`/api/ssl/certificates/${certificateId}/renew`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Certificate renewal initiated successfully');
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error renewing certificate: ' + error.message);
                });
            }
        }

        function retryCertificate(certificateId) {
            if (confirm('Are you sure you want to retry issuing this certificate?')) {
                fetch(`/api/ssl/certificates/${certificateId}`, {
                    method: 'PUT',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ action: 'retry' })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Certificate issuance retry initiated');
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error retrying certificate: ' + error.message);
                });
            }
        }
    </script>
</x-layouts.app>