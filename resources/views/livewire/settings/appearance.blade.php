<div x-data="appearanceComponent()" x-init="init()"
    @theme-changed.window="handleThemeChange($event.detail.theme)"
    @appearance-updated.window="handleAppearanceUpdate($event.detail)"
    @animations-updated.window="handleAnimationsUpdate($event.detail.animations)"
    @sound-notifications-updated.window="handleSoundNotificationsUpdate($event.detail.soundNotifications)"
    class="min-h-screen bg-gray-50 dark:bg-gray-900 py-12 transition-colors duration-200">
    
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

                    <!-- 成功・エラーメッセージ -->
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

                    <form wire:submit="updateAppearance" class="space-y-6">
                        <!-- Theme Selection -->
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
                            <select wire:model.live="language" name="language" id="language" class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200">
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
                            <select wire:model.live="timezone" name="timezone" id="timezone" class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200">
                                @foreach($availableTimezones as $tz => $name)
                                    <option value="{{ $tz }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {{ __('Your timezone affects how dates and times are displayed.') }}
                            </p>
                        </div>

                        <!-- Animation Preferences -->
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
                                    <input type="checkbox" wire:model.live="animations" class="form-checkbox h-5 w-5 text-blue-600 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500">
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
                                    <input type="checkbox" wire:model.live="sound_notifications" class="form-checkbox h-5 w-5 text-blue-600 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500">
                                </label>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
                            <button type="button" wire:click="resetToDefaults" class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition-colors duration-200">
                                {{ __('Reset to Defaults') }}
                            </button>

                            <div class="flex items-center gap-4">
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-800 text-white font-medium py-2 px-4 rounded-lg transition-colors duration-200">
                                    {{ __('Save Changes') }}
                                </button>

                                <div id="save-message" class="text-sm text-green-600 hidden">
                                    {{ __('Saved.') }}
                                </div>
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

                        <div class="space-y-4" id="theme-preview">
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
                                <p>Current Theme: <span id="debug-current-theme">{{ $theme }}</span></p>
                                <p>Session Theme: {{ session('theme', 'not set') }}</p>
                                <p>User Timezone: {{ Auth::user()->timezone ?? 'not set' }}</p>
                                <p>Language: {{ $language }}</p>
                                <p>Animations: {{ $animations ? 'enabled' : 'disabled' }}</p>
                                <div class="flex gap-2 mt-2">
                                    <button type="button" onclick="window.forceReapplyTheme && window.forceReapplyTheme()"
                                        class="text-blue-600 hover:underline text-xs">
                                        Force Reapply Theme
                                    </button>
                                    <button type="button" onclick="console.log('ThemeManager:', window.ThemeManager)"
                                        class="text-blue-600 hover:underline text-xs">
                                        Log ThemeManager
                                    </button>
                                    <button type="button" onclick="console.log('Livewire data:', @this)"
                                        class="text-blue-600 hover:underline text-xs">
                                        Log Livewire
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Enhanced Appearance Component JavaScript - Livewire Integration Fixed
function appearanceComponent() {
    return {
        themeManagerReady: false,
        currentTheme: 'system',
        maxRetries: 15,
        retryInterval: 300,

        init() {
            console.log('Appearance component initialized');
            
            // Livewireからの初期テーマ値を取得
            const initialTheme = @json($theme);
            this.currentTheme = initialTheme;
            console.log('Initial theme from Livewire:', initialTheme);

            this.waitForThemeManager();
            this.setupLivewireSync();
        },

        setupLivewireSync() {
            // Livewireとの双方向同期を確立
            console.log('Setting up Livewire sync');
            
            // ThemeManagerが利用可能になったら連携開始
            this.whenThemeManagerReady(() => {
                if (window.ThemeManager && window.ThemeManager.addObserver) {
                    window.ThemeManager.addObserver((theme, effectiveTheme) => {
                        console.log('ThemeManager theme change:', theme, '->', effectiveTheme);
                        
                        // Livewireプロパティを更新
                        if (this.$wire && this.$wire.set) {
                            this.$wire.set('theme', theme);
                        }
                        
                        this.updatePreview(effectiveTheme);
                    });
                }
            });
        },

        waitForThemeManager() {
            let attempts = 0;

            const checkInterval = setInterval(() => {
                attempts++;
                console.log(`Checking for ThemeManager (attempt ${attempts}/${this.maxRetries})`);

                if (window.ThemeManager && typeof window.setTheme === 'function' && window.ThemeManager.isInitialized) {
                    clearInterval(checkInterval);
                    this.themeManagerReady = true;
                    console.log('ThemeManager is ready for appearance component');

                    // 初期テーマを適用
                    this.applyTheme(this.currentTheme);
                } else if (attempts >= this.maxRetries) {
                    clearInterval(checkInterval);
                    console.warn(`ThemeManager not available after ${this.maxRetries} attempts, using fallback`);
                    this.themeManagerReady = false;
                    this.applyTheme(this.currentTheme);
                }
            }, this.retryInterval);
        },

        whenThemeManagerReady(callback) {
            if (this.themeManagerReady) {
                callback();
            } else {
                setTimeout(() => {
                    if (this.themeManagerReady) {
                        callback();
                    } else {
                        this.whenThemeManagerReady(callback);
                    }
                }, 100);
            }
        },

        applyTheme(theme) {
            console.log('Applying theme in appearance component:', theme);
            this.currentTheme = theme;

            if (this.themeManagerReady && window.ThemeManager) {
                try {
                    window.ThemeManager.setTheme(theme);
                    console.log('Theme applied via ThemeManager:', theme);
                } catch (error) {
                    console.error('Error applying theme via ThemeManager:', error);
                    this.fallbackApplyTheme(theme);
                }
            } else if (window.setTheme) {
                try {
                    window.setTheme(theme);
                    console.log('Theme applied via global setTheme:', theme);
                } catch (error) {
                    console.error('Error applying theme via global setTheme:', error);
                    this.fallbackApplyTheme(theme);
                }
            } else {
                this.fallbackApplyTheme(theme);
            }

            // プレビューを更新
            this.updatePreview(this.getEffectiveTheme(theme));
        },

        fallbackApplyTheme(theme) {
            try {
                localStorage.setItem('theme', theme);
                console.log('Theme saved to localStorage (fallback):', theme);

                // 手動でDOMクラスを更新
                const html = document.documentElement;
                html.classList.remove('dark', 'light');
                html.setAttribute('data-theme', theme);

                if (theme === 'dark') {
                    html.classList.add('dark');
                } else if (theme === 'light') {
                    html.classList.add('light');
                } else if (theme === 'system') {
                    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                    html.classList.add(prefersDark ? 'dark' : 'light');
                }

                // カスタムイベントを発火
                window.dispatchEvent(new CustomEvent('theme-applied', {
                    detail: { theme, effectiveTheme: this.getEffectiveTheme(theme) }
                }));
            } catch (error) {
                console.error('Error in fallback theme application:', error);
            }
        },

        getEffectiveTheme(theme) {
            if (theme === 'system') {
                return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            return theme;
        },

        updatePreview(effectiveTheme) {
            // プレビューエリアのテーマを更新
            const preview = document.getElementById('theme-preview');
            if (preview) {
                const cards = preview.querySelectorAll('.border');
                cards.forEach(card => {
                    if (effectiveTheme === 'dark') {
                        card.classList.add('dark:border-gray-700', 'dark:bg-gray-800');
                        card.classList.remove('border-gray-200', 'bg-white');
                    } else {
                        card.classList.remove('dark:border-gray-700', 'dark:bg-gray-800');
                        card.classList.add('border-gray-200', 'bg-white');
                    }
                });
            }

            // デバッグ表示の更新
            const debugElement = document.getElementById('debug-current-theme');
            if (debugElement) {
                debugElement.textContent = this.currentTheme;
            }
        },

        handleThemeChange(theme) {
            console.log('Theme change event received in appearance component:', theme);
            this.applyTheme(theme);
        },

        handleAppearanceUpdate(data) {
            console.log('Appearance update event received:', data);
            
            if (data.theme) {
                this.applyTheme(data.theme);
            }
            
            // 成功メッセージを表示
            this.showSuccessMessage();
        },

        handleAnimationsUpdate(animations) {
            console.log('Animations setting updated:', animations);
            // アニメーション設定の更新処理
        },

        handleSoundNotificationsUpdate(soundNotifications) {
            console.log('Sound notifications setting updated:', soundNotifications);
            // 音声通知設定の更新処理
        },

        showSuccessMessage() {
            const message = document.getElementById('save-message');
            if (message) {
                message.classList.remove('hidden');
                setTimeout(() => {
                    message.classList.add('hidden');
                }, 3000);
            }
        }
    };
}

// DOM loaded イベントリスナー
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM loaded - setting up appearance listeners');
});

// Livewire初期化イベントリスナー
document.addEventListener('livewire:init', () => {
    console.log('Livewire initialized for appearance component');
});

// ページナビゲーション時の処理
document.addEventListener('livewire:navigated', () => {
    console.log('Navigated to appearance page');

    // テーマの再適用
    if (window.ThemeManager && window.ThemeManager.isInitialized) {
        setTimeout(() => {
            window.ThemeManager.forceReapply();
        }, 50);
    }
});

console.log('Appearance component JavaScript loaded');
</script>

<style>
/* ラジオボタンの選択状態を視覚的に示す */
input[type="radio"]:checked + * {
    border-color: rgb(59 130 246) !important;
    background-color: rgb(239 246 255) !important;
}

.dark input[type="radio"]:checked + * {
    background-color: rgba(59, 130, 246, 0.2) !important;
}

/* フォーム要素のダークモード対応 */
.form-checkbox:checked {
    background-color: rgb(59 130 246);
    border-color: rgb(59 130 246);
}

.dark .form-checkbox {
    background-color: rgb(55 65 81);
    border-color: rgb(75 85 99);
}

.dark .form-checkbox:checked {
    background-color: rgb(59 130 246);
    border-color: rgb(59 130 246);
}

/* アニメーション効果 */
.transition-colors {
    transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}
</style>
@endpush