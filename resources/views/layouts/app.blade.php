<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50" data-theme="{{ session('theme', 'system') }}">

<!-- layouts/app.php の head 部分 -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme" content="{{ session('theme', 'system') }}">
    <title>{{ config('app.name', 'SSL SaaS Platform') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

@php
$userTheme = 'system';
if (Auth::check() && Auth::user()->preferences && isset(Auth::user()->preferences['theme'])) {
    $userTheme = Auth::user()->preferences['theme'];
} else {
    $userTheme = session('theme', 'system');
}

$bodyClass = '';
if ($userTheme === 'dark') {
    $bodyClass = 'dark';
} elseif ($userTheme === 'light') {
    $bodyClass = 'light';
} else {
    // system の場合はJavaScriptに任せる
    $bodyClass = '';
}
@endphp

<body class="h-full bg-gray-50 dark:bg-gray-900 transition-colors duration-200 {{ $bodyClass }}" data-theme="{{ $userTheme }}">

    <!-- Navigation -->
    <nav class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700 transition-colors duration-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100 flex items-center transition-colors duration-200">
                            <svg class="h-6 w-6 text-blue-600 dark:text-blue-400 mr-2 transition-colors duration-200" fill="none" stroke="currentColor"
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
                                class="{{ request()->routeIs('ssl.dashboard') ? 'border-blue-500 text-gray-900 dark:text-gray-100' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600' }} inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition-colors duration-200">
                                Dashboard
                            </a>
                            <a href="{{ route('ssl.certificates.index') }}"
                                class="{{ request()->routeIs('ssl.certificates.*') ? 'border-blue-500 text-gray-900 dark:text-gray-100' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600' }} inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition-colors duration-200">
                                Certificates
                            </a>
                            <a href="{{ route('ssl.billing.index') }}"
                                class="{{ request()->routeIs('ssl.billing.*') ? 'border-blue-500 text-gray-900 dark:text-gray-100' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600' }} inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition-colors duration-200">
                                Billing
                            </a>
                            <a href="{{ route('ssl.docs.index') }}"
                                class="{{ request()->routeIs('ssl.docs.*') ? 'border-blue-500 text-gray-900 dark:text-gray-100' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600' }} inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition-colors duration-200">
                                Support
                            </a>
                        </div>
                    @endauth
                </div>

                <!-- User Menu -->
                <div class="flex items-center space-x-4">
                    @auth
                        <div class="flex items-center space-x-3">
                            <!-- Theme Toggle Button (Quick Access) -->
                            <button onclick="toggleTheme()" class="p-2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors duration-200 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700" title="Toggle theme">
                                <svg class="h-5 w-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                                <svg class="h-5 w-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                </svg>
                            </button>
                            
                            <span class="text-sm text-gray-700 dark:text-gray-300 transition-colors duration-200">{{ Auth::user()->name }}</span>
                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open"
                                    class="flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                    <img class="h-8 w-8 rounded-full border-2 border-transparent hover:border-gray-300 dark:hover:border-gray-600 transition-colors duration-200"
                                        src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&color=7F9CF5&background=EBF4FF"
                                        alt="">
                                </button>

                                <div x-show="open" @click.away="open = false" x-transition
                                    class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 border border-gray-200 dark:border-gray-700 transition-colors duration-200">
                                    <div class="py-1">
                                        <a href="{{ route('settings.profile') }}"
                                            class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">Profile</a>
                                        <!-- 管理画面へのリンク - 権限がある場合のみ表示 -->
                                        @if (Auth::user()->hasPermission('admin.access'))
                                            <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>
                                            <a href="{{ route('admin.dashboard') }}"
                                                class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 font-medium transition-colors duration-200">
                                                <svg class="w-4 h-4 inline mr-2 text-red-600 dark:text-red-400" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                                                    </path>
                                                </svg>
                                                Admin Dashboard
                                            </a>
                                        @endif
                                        <a href="{{ route('settings.profile') }}"
                                            class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">Settings</a>
                                        <form method="POST" action="{{ route('logout') }}" class="block">
                                            @csrf
                                            <button type="submit"
                                                class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                                                Sign out
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors duration-200">Sign in</a>
                        <a href="{{ route('register') }}" class="btn-primary">Get Started</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-1 bg-gray-50 dark:bg-gray-900 transition-colors duration-200">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 mt-auto transition-colors duration-200">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center">
                <p class="text-sm text-gray-500 dark:text-gray-400 transition-colors duration-200">
                    © {{ date('Y') }} SSL SaaS Platform. All rights reserved.
                </p>
                <div class="flex space-x-6">
                    <a href="{{ route('ssl.docs.index') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors duration-200">Documentation</a>
                    <a href="#" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors duration-200">Privacy</a>
                    <a href="#" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors duration-200">Terms</a>
                    <a href="{{ route('ssl.docs.index') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors duration-200">Support</a>
                </div>
            </div>
        </div>
    </footer>

    @livewireScripts
    @stack('scripts')

    <!-- Theme Manager Script -->
    <script>
        // Quick theme toggle function
        function toggleTheme() {
            const currentTheme = window.getCurrentTheme();
            const effectiveTheme = window.getEffectiveTheme();
            const newTheme = effectiveTheme === 'dark' ? 'light' : 'dark';
            
            console.log('Toggle theme from', effectiveTheme, 'to', newTheme);
            window.setTheme(newTheme);
        }

        // Banner message system
        window.addEventListener('banner-message', (event) => {
            const { style, message } = event.detail;
            showNotification(message, style);
        });

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }

        // Initialize theme debugging
        console.log('Layout loaded with theme:', document.documentElement.getAttribute('data-theme'));
        console.log('Document classes:', document.documentElement.className);
    </script>

    <!-- Alpine.js
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
     -->
</body>

</html>