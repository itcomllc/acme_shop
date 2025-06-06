<div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-12 transition-colors duration-200">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Settings Header -->
        <div class="relative mb-6 w-full">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">{{ __('Settings') }}</h1>
            <p class="text-gray-600 dark:text-gray-400 mb-6">{{ __('Manage your profile and account settings') }}</p>
            <div class="border-b border-gray-200 dark:border-gray-700"></div>
        </div>

        <!-- Settings Navigation & Content Layout -->
        <div class="flex items-start max-md:flex-col">
            <!-- Settings Navigation -->
            <div class="me-10 w-full pb-4 md:w-[220px]">
                <nav class="space-y-1">
                    <a href="{{ route('settings.profile') }}" 
                       class="flex items-center px-3 py-2 text-sm font-medium rounded-md {{ request()->routeIs('settings.profile') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-50 dark:text-gray-300 dark:hover:text-white dark:hover:bg-gray-700' }} transition-colors duration-200"
                       wire:navigate>
                        <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        {{ __('Profile') }}
                    </a>
                    
                    <a href="{{ route('settings.password') }}" 
                       class="flex items-center px-3 py-2 text-sm font-medium rounded-md {{ request()->routeIs('settings.password') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-50 dark:text-gray-300 dark:hover:text-white dark:hover:bg-gray-700' }} transition-colors duration-200"
                       wire:navigate>
                        <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                        {{ __('Password') }}
                    </a>
                    
                    <a href="{{ route('settings.appearance') }}" 
                       class="flex items-center px-3 py-2 text-sm font-medium rounded-md {{ request()->routeIs('settings.appearance') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-50 dark:text-gray-300 dark:hover:text-white dark:hover:bg-gray-700' }} transition-colors duration-200"
                       wire:navigate>
                        <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17v4a2 2 0 002 2h4M15 5l2 2"></path>
                        </svg>
                        {{ __('Appearance') }}
                    </a>

                    @auth
                        @if(Auth::user()->activeSubscription)
                            <a href="{{ route('ssl.eab.index') }}" 
                               class="flex items-center px-3 py-2 text-sm font-medium rounded-md {{ request()->routeIs('ssl.eab.*') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-50 dark:text-gray-300 dark:hover:text-white dark:hover:bg-gray-700' }} transition-colors duration-200"
                               wire:navigate>
                                <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                                {{ __('ACME Settings') }}
                            </a>
                        @endif
                    @endauth
                </nav>
            </div>

            <div class="border-r border-gray-200 dark:border-gray-700 h-64 hidden md:block"></div>

            <!-- Settings Content -->
            <div class="flex-1 self-stretch max-md:pt-6 md:pl-10">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 transition-colors duration-200">
                    <!-- Appearance Section Header -->
                    <div class="mb-6">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ __('Appearance') }}</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Customize the look and feel of your SSL SaaS Platform experience.') }}</p>
                    </div>

                    <!-- Success/Error Messages -->
                    @if (session('status') === 'appearance-updated')
                        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg dark:bg-green-900 dark:border-green-700 dark:text-green-300">
                            {{ __('Appearance settings updated successfully.') }}
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg dark:bg-red-900 dark:border-red-700 dark:text-red-300">
                            {{ session('error') }}
                        </div>
                    @endif

                    <form wire:submit.prevent="updateAppearance" class="space-y-6">
                        <!-- Theme Selection (ライブ更新) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ __('Theme') }}</label>
                            <div class="mt-2 grid grid-cols-3 gap-4">
                                <label class="flex flex-col items-center p-4 border rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors {{ $theme === 'light' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-300 dark:border-gray-600' }}">
                                    <input type="radio" wire:model.live="theme" value="light" class="sr-only">
                                    <svg class="h-8 w-8 mb-2 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                                    </svg>
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('Light') }}</span>
                                </label>

                                <label class="flex flex-col items-center p-4 border rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors {{ $theme === 'dark' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-300 dark:border-gray-600' }}">
                                    <input type="radio" wire:model.live="theme" value="dark" class="sr-only">
                                    <svg class="h-8 w-8 mb-2 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                    </svg>
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('Dark') }}</span>
                                </label>

                                <label class="flex flex-col items-center p-4 border rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors {{ $theme === 'system' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-300 dark:border-gray-600' }}">
                                    <input type="radio" wire:model.live="theme" value="system" class="sr-only">
                                    <svg class="h-8 w-8 mb-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('System') }}</span>
                                </label>
                            </div>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                {{ __('Choose your preferred theme. System will match your device settings.') }}
                            </p>
                        </div>

                        <!-- Language Selection -->
                        <div>
                            <label for="language" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Language') }}</label>
                            <select wire:model="language" name="language" id="language" class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200">
                                @foreach($availableLanguages as $code => $name)
                                    <option value="{{ $code }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {{ __('Select your preferred language for the interface.') }}
                            </p>
                        </div>

                        <!-- Timezone Selection -->
                        <div>
                            <label for="timezone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Timezone') }}</label>
                            <select wire:model="timezone" name="timezone" id="timezone" class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200">
                                @foreach($availableTimezones as $tz => $name)
                                    <option value="{{ $tz }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {{ __('Your timezone affects how dates and times are displayed.') }}
                            </p>
                        </div>

                        <!-- Interface Preferences -->
                        <div class="space-y-4">
                            <h3 class="text-md font-medium text-gray-900 dark:text-gray-100">{{ __('Interface Preferences') }}</h3>

                            <div class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-600 rounded-lg">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Enable Animations') }}</label>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ __('Show smooth transitions and animations throughout the interface.') }}
                                    </p>
                                </div>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" wire:model="animations" class="form-checkbox h-5 w-5 text-blue-600 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500">
                                </label>
                            </div>

                            <div class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-600 rounded-lg">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Sound Notifications') }}</label>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ __('Play sounds for important notifications and alerts.') }}
                                    </p>
                                </div>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" wire:model="sound_notifications" class="form-checkbox h-5 w-5 text-blue-600 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500">
                                </label>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
                            <button type="button" wire:click="resetToDefaults" class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition-colors duration-200">
                                {{ __('Reset to Defaults') }}
                            </button>

                            <div class="flex items-center gap-4">
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-800 text-white font-medium py-2 px-4 rounded-lg transition-colors duration-200">
                                    {{ __('Save Changes') }}
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Preview Section -->
                    <div class="mt-8 pt-8 border-t border-gray-200 dark:border-gray-700">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ __('Preview') }}</h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {{ __('See how your settings will look in the SSL dashboard.') }}
                            </p>
                        </div>

                        <div class="space-y-4">
                            <!-- Sample SSL Certificate Card -->
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-white dark:bg-gray-800 transition-colors">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="font-medium text-gray-900 dark:text-white">
                                            example.com
                                        </h3>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            DV Certificate • GoGetSSL
                                        </p>
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-400">
                                        Active
                                    </span>
                                </div>
                            </div>

                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ __('This is how certificate cards will appear with your current theme selection.') }}
                            </p>
                        </div>
                    </div>

                    <!-- Debug Info (Development Only) -->
                    @if (config('app.debug'))
                        <div class="mt-8 pt-8 border-t border-gray-200 dark:border-gray-700">
                            <div class="text-xs text-gray-500 space-y-1">
                                <p>Current Theme: {{ $theme }}</p>
                                <p>Session Theme: {{ session('theme', 'not set') }}</p>
                                <p>User Timezone: {{ Auth::user()->timezone ?? 'not set' }}</p>
                                <p>Language: {{ $language }}</p>
                                <p>Animations: {{ $animations ? 'enabled' : 'disabled' }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @script
    <script>
        // グローバルに関数を定義（即座に実行）
        window.applyTheme = function(theme) {
            const html = document.documentElement;
            const body = document.body;
            
            console.log('Applying theme:', theme);
            
            // 既存クラスを削除
            html.classList.remove('dark', 'light');
            if (body) body.classList.remove('dark', 'light');
            
            // テーマを適用
            if (theme === 'dark') {
                html.classList.add('dark');
                if (body) body.classList.add('dark');
            } else if (theme === 'light') {
                html.classList.add('light');
                if (body) body.classList.add('light');
            } else { // system
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (prefersDark) {
                    html.classList.add('dark');
                    if (body) body.classList.add('dark');
                } else {
                    html.classList.add('light');
                    if (body) body.classList.add('light');
                }
            }
            
            // localStorageに保存
            localStorage.setItem('theme', theme);
        };

        window.showSuccessMessage = function(message) {
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-opacity duration-300';
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
        };

        // 初期化時にテーマを適用
        const currentTheme = localStorage.getItem('theme') || 'system';
        window.applyTheme(currentTheme);
        console.log('Appearance component initialized with theme:', currentTheme);

        // Livewireイベントリスナー
        $wire.on('theme-updated', (data) => {
            console.log('Theme updated event received:', data);
            const theme = Array.isArray(data) ? data[0]?.theme : data.theme;
            if (theme) {
                window.applyTheme(theme);
                window.showSuccessMessage('Theme updated!');
            }
        });

        $wire.on('appearance-updated', (data) => {
            console.log('Appearance updated event received:', data);
            const eventData = Array.isArray(data) ? data[0] : data;
            if (eventData?.theme) {
                window.applyTheme(eventData.theme);
            }
            window.showSuccessMessage('Settings saved successfully!');
        });
    </script>
    @endscript
</div>