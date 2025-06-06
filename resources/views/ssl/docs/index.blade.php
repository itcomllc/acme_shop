@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Documentation & Support</h1>
        <p class="text-gray-600 mt-2">Learn how to use our SSL certificate platform and get support when you need it.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Quick Start -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center mb-4">
                <div class="p-2 bg-blue-100 rounded-lg">
                    <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <h3 class="ml-3 text-lg font-semibold text-gray-900">Quick Start</h3>
            </div>
            <p class="text-gray-600 mb-4">Get up and running with SSL certificates in minutes.</p>
            <ul class="text-sm text-gray-600 space-y-2 mb-4">
                <li>• Choose a subscription plan</li>
                <li>• Add your domain</li>
                <li>• Complete validation</li>
                <li>• Download your certificate</li>
            </ul>
            <a href="{{ route('ssl.dashboard') }}" class="text-blue-600 hover:text-blue-500 text-sm font-medium">
                Get Started →
            </a>
        </div>

        <!-- API Documentation -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center mb-4">
                <div class="p-2 bg-green-100 rounded-lg">
                    <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                    </svg>
                </div>
                <h3 class="ml-3 text-lg font-semibold text-gray-900">API Reference</h3>
            </div>
            <p class="text-gray-600 mb-4">Integrate SSL certificate management into your applications.</p>
            <ul class="text-sm text-gray-600 space-y-2 mb-4">
                <li>• REST API endpoints</li>
                <li>• Authentication</li>
                <li>• Code examples</li>
                <li>• WebHook integration</li>
            </ul>
            <a href="{{ route('ssl.docs.api') }}" class="text-blue-600 hover:text-blue-500 text-sm font-medium">
                View API Docs →
            </a>
        </div>

        <!-- ACME Support -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center mb-4">
                <div class="p-2 bg-purple-100 rounded-lg">
                    <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                </div>
                <h3 class="ml-3 text-lg font-semibold text-gray-900">ACME Protocol</h3>
            </div>
            <p class="text-gray-600 mb-4">Use standard ACME clients like Certbot with our platform.</p>
            <ul class="text-sm text-gray-600 space-y-2 mb-4">
                <li>• EAB credentials</li>
                <li>• Certbot setup</li>
                <li>• DNS validation</li>
                <li>• Auto-renewal</li>
            </ul>
            @auth
                @if(Auth::user()->activeSubscription)
                    <a href="{{ route('ssl.eab.instructions') }}" class="text-blue-600 hover:text-blue-500 text-sm font-medium">
                        Setup ACME →
                    </a>
                @else
                    <span class="text-gray-400 text-sm">Requires active subscription</span>
                @endif
            @else
                <a href="{{ route('login') }}" class="text-blue-600 hover:text-blue-500 text-sm font-medium">
                    Login to access →
                </a>
            @endauth
        </div>

        <!-- Certificate Validation -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center mb-4">
                <div class="p-2 bg-yellow-100 rounded-lg">
                    <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h3 class="ml-3 text-lg font-semibold text-gray-900">Domain Validation</h3>
            </div>
            <p class="text-gray-600 mb-4">Learn about HTTP and DNS validation methods.</p>
            <ul class="text-sm text-gray-600 space-y-2 mb-4">
                <li>• HTTP-01 validation</li>
                <li>• DNS-01 validation</li>
                <li>• Troubleshooting</li>
                <li>• Best practices</li>
            </ul>
            <a href="#validation-guide" class="text-blue-600 hover:text-blue-500 text-sm font-medium">
                Learn More →
            </a>
        </div>

        <!-- Billing & Plans -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center mb-4">
                <div class="p-2 bg-indigo-100 rounded-lg">
                    <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 003 3z"></path>
                    </svg>
                </div>
                <h3 class="ml-3 text-lg font-semibold text-gray-900">Billing & Plans</h3>
            </div>
            <p class="text-gray-600 mb-4">Understanding our pricing and payment methods.</p>
            <ul class="text-sm text-gray-600 space-y-2 mb-4">
                <li>• Plan comparison</li>
                <li>• Payment methods</li>
                <li>• Billing cycles</li>
                <li>• Cancellation policy</li>
            </ul>
            <a href="{{ route('ssl.billing.index') }}" class="text-blue-600 hover:text-blue-500 text-sm font-medium">
                View Billing →
            </a>
        </div>

        <!-- Support -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center mb-4">
                <div class="p-2 bg-red-100 rounded-lg">
                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192L5.636 18.364M12 2.5a9.5 9.5 0 10.001 19.001A9.5 9.5 0 0012 2.5z"></path>
                    </svg>
                </div>
                <h3 class="ml-3 text-lg font-semibold text-gray-900">Get Support</h3>
            </div>
            <p class="text-gray-600 mb-4">Need help? We're here to assist you.</p>
            <ul class="text-sm text-gray-600 space-y-2 mb-4">
                <li>• Email support</li>
                <li>• Knowledge base</li>
                <li>• FAQ</li>
                <li>• Status page</li>
            </ul>
            <a href="#contact-support" class="text-blue-600 hover:text-blue-500 text-sm font-medium">
                Contact Support →
            </a>
        </div>
    </div>

    <!-- FAQ Section -->
    <div class="mt-12" id="faq">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Frequently Asked Questions</h2>
        
        <div class="space-y-4">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">How long does it take to issue a certificate?</h3>
                <p class="text-gray-600">Domain validated certificates are typically issued within 5-10 minutes after successful validation. Organization and Extended validation certificates may take longer due to additional verification requirements.</p>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">What happens if my certificate expires?</h3>
                <p class="text-gray-600">We'll send reminder emails 30, 14, and 7 days before expiration. If you have auto-renewal enabled, we'll automatically renew your certificate. Otherwise, you'll need to manually renew it before expiration.</p>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Can I use wildcard certificates?</h3>
                <p class="text-gray-600">Yes! Wildcard certificates are supported on our Professional and Enterprise plans. They allow you to secure your main domain and all its subdomains with a single certificate.</p>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">How do I change my subscription plan?</h3>
                <p class="text-gray-600">You can upgrade or downgrade your plan at any time from your billing page. Changes take effect immediately, and you'll be charged or credited prorated amounts.</p>
            </div>
        </div>
    </div>

    <!-- Contact Support Section -->
    <div class="mt-12 bg-gray-50 rounded-lg p-8" id="contact-support">
        <div class="text-center">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Still need help?</h2>
            <p class="text-gray-600 mb-6">Our support team is here to help you with any questions or issues.</p>
            
            <div class="flex justify-center space-x-4">
                <a href="mailto:support@sslsaas.com" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.86c.44.27.97.27 1.41 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    Email Support
                </a>
                
                <a href="#" class="inline-flex items-center px-6 py-3 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Knowledge Base
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
