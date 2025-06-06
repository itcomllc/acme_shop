<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'SSL SaaS Platform') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts and Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="h-full">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <h1 class="text-xl font-semibold text-gray-900 flex items-center">
                            <svg class="h-6 w-6 text-blue-600 mr-2" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                                </path>
                            </svg>
                            SSL SaaS Platform
                        </h1>
                    </div>

                    <!-- Navigation Links -->
                    @auth
                        <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                            <a href="{{ route('ssl.dashboard') }}"
                                class="{{ request()->routeIs('ssl.dashboard') ? 'border-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                Dashboard
                            </a>
                            <a href="{{ route('ssl.certificates.index') }}"
                                class="{{ request()->routeIs('ssl.certificates.*') ? 'border-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                Certificates
                            </a>
                            <a href="{{ route('ssl.billing.index') }}"
                                class="{{ request()->routeIs('ssl.billing.*') ? 'border-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                Billing
                            </a>
                            <a href="{{ route('ssl.docs.index') }}"
                                class="{{ request()->routeIs('ssl.docs.*') ? 'border-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                Support
                            </a>
                        </div>
                    @endauth
                </div>

                <!-- User Menu -->
                <div class="flex items-center space-x-4">
                    @auth
                        <div class="flex items-center space-x-3">
                            <span class="text-sm text-gray-700">{{ Auth::user()->name }}</span>
                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open"
                                    class="flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <img class="h-8 w-8 rounded-full"
                                        src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&color=7F9CF5&background=EBF4FF"
                                        alt="">
                                </button>

                                <div x-show="open" @click.away="open = false"
                                    class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5">
                                    <div class="py-1">
                                        <a href="{{ route('settings.profile') }}"
                                            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profile</a>
                                        <!-- 管理画面へのリンク - 権限がある場合のみ表示 -->
                                        @if (Auth::user()->hasPermission('admin.access'))
                                            <div class="border-t border-gray-100 my-1"></div>
                                            <a href="{{ route('admin.dashboard') }}"
                                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 font-medium">
                                                <svg class="w-4 h-4 inline mr-2 text-red-600" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                                                    </path>
                                                </svg>
                                                Admin Dashboard
                                            </a>
                                        @endif
                                        <a href="{{ route('settings.profile') }}"
                                            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Settings</a>
                                        <form method="POST" action="{{ route('logout') }}" class="block">
                                            @csrf
                                            <button type="submit"
                                                class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                Sign out
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="text-gray-500 hover:text-gray-700">Sign in</a>
                        <a href="{{ route('register') }}" class="btn-primary">Get Started</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-1">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-auto">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center">
                <p class="text-sm text-gray-500">
                    © {{ date('Y') }} SSL SaaS Platform. All rights reserved.
                </p>
                <div class="flex space-x-6">
                    <a href="{{ route('ssl.docs.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Documentation</a>
                    <a href="#" class="text-sm text-gray-500 hover:text-gray-700">Privacy</a>
                    <a href="#" class="text-sm text-gray-500 hover:text-gray-700">Terms</a>
                    <a href="{{ route('ssl.docs.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Support</a>
                </div>
            </div>
        </div>
    </footer>

    @livewireScripts
    @stack('scripts')

    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>

</html>