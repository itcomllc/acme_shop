<?php

use Illuminate\Support\Facades\{Hash, Auth};
use Livewire\Volt\Component;

new class extends Component
{
    public string $theme = 'system';
    public string $language = 'en';
    public string $timezone = 'UTC';
    public bool $animations = true;
    public bool $sound_notifications = false;
    
    public function mount(): void
    {
        // Load user preferences from session or database
        $this->theme = session('theme', 'system');
        $this->language = session('locale', 'en');
        $this->timezone = Auth::user()->timezone ?? config('app.timezone', 'UTC');
        $this->animations = session('animations', true);
        $this->sound_notifications = session('sound_notifications', false);
    }
    
    // テーマ変更時にリアルタイムで適用
    public function updatedTheme($value): void
    {
        session(['theme' => $value]);
        $this->dispatch('theme-changed', theme: $value);
    }
    
    public function updateAppearance(): void
    {
        // Save preferences to session
        session(['theme' => $this->theme]);
        session(['locale' => $this->language]);
        session(['animations' => $this->animations]);
        session(['sound_notifications' => $this->sound_notifications]);
        
        // Update user timezone in database
        if (Auth::user()) {
            Auth::user()->update(['timezone' => $this->timezone]);
        }

        $this->dispatch('appearance-updated');
    }
    
    public function resetToDefaults(): void
    {
        $this->theme = 'system';
        $this->language = 'en';
        $this->timezone = 'UTC';
        $this->animations = true;
        $this->sound_notifications = false;
        
        $this->updateAppearance();
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Appearance')" :subheading="__('Customize the look and feel of your SSL SaaS Platform experience.')">
        <form wire:submit="updateAppearance" class="my-6 w-full space-y-6">
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
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Choose your preferred theme. System will match your device settings.') }}</p>
            </div>

            <!-- Language Selection -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Language') }}</label>
                <select wire:model="language" name="language" class="form-input bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-600">
                    <option value="en">English</option>
                    <option value="ja">日本語 (Japanese)</option>
                    <option value="es">Español (Spanish)</option>
                    <option value="fr">Français (French)</option>
                    <option value="de">Deutsch (German)</option>
                    <option value="zh">中文 (Chinese)</option>
                </select>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Select your preferred language for the interface.') }}</p>
            </div>

            <!-- Timezone Selection -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Timezone') }}</label>
                <select wire:model="timezone" name="timezone" class="form-input bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-600">
                    <option value="UTC">UTC</option>
                    <option value="America/New_York">Eastern Time (US)</option>
                    <option value="America/Chicago">Central Time (US)</option>
                    <option value="America/Denver">Mountain Time (US)</option>
                    <option value="America/Los_Angeles">Pacific Time (US)</option>
                    <option value="Europe/London">London</option>
                    <option value="Europe/Paris">Paris</option>
                    <option value="Europe/Berlin">Berlin</option>
                    <option value="Asia/Tokyo">Tokyo</option>
                    <option value="Asia/Shanghai">Shanghai</option>
                    <option value="Asia/Seoul">Seoul</option>
                    <option value="Australia/Sydney">Sydney</option>
                </select>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Your timezone affects how dates and times are displayed.') }}</p>
            </div>

            <!-- Animation Preferences -->
            <div class="space-y-4">
                <h3 class="text-md font-medium text-gray-900 dark:text-gray-100">{{ __('Interface Preferences') }}</h3>
                
                <div class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-600 rounded-lg">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Enable Animations') }}</label>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Show smooth transitions and animations throughout the interface.') }}</p>
                    </div>
                    <label class="inline-flex items-center">
                        <input type="checkbox" wire:model="animations" class="form-checkbox h-5 w-5 text-blue-600 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500">
                    </label>
                </div>

                <div class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-600 rounded-lg">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Sound Notifications') }}</label>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Play sounds for important notifications and alerts.') }}</p>
                    </div>
                    <label class="inline-flex items-center">
                        <input type="checkbox" wire:model="sound_notifications" class="form-checkbox h-5 w-5 text-blue-600 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500">
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
                <button type="button" wire:click="resetToDefaults" class="btn-outline">
                    {{ __('Reset to Defaults') }}
                </button>
                
                <div class="flex items-center gap-4">
                    <button type="submit" class="btn-primary">{{ __('Save Changes') }}</button>
                    
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
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('See how your settings will look in the SSL dashboard.') }}</p>
            </div>

            <div class="space-y-4">
                <!-- Sample SSL Certificate Card -->
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-white dark:bg-gray-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-medium text-gray-900 dark:text-white">
                                example.com
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                DV Certificate • GoGetSSL
                            </p>
                        </div>
                        <span class="status-badge status-badge-issued">
                            Active
                        </span>
                    </div>
                </div>

                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('This is how certificate cards will appear with your current theme selection.') }}
                </p>
            </div>
        </div>
    </x-settings.layout>
</section>

<script>
document.addEventListener('livewire:init', () => {
    // テーマ変更の即座反映
    Livewire.on('theme-changed', (event) => {
        console.log('Theme changed to:', event.theme);
        applyTheme(event.theme);
    });

    Livewire.on('appearance-updated', () => {
        const theme = @json($theme);
        console.log('Appearance updated, applying theme:', theme);
        applyTheme(theme);

        // 成功メッセージ表示
        showSaveMessage();
    });

    // 初期テーマ適用
    document.addEventListener('DOMContentLoaded', function() {
        const currentTheme = @json($theme);
        console.log('Initial theme application:', currentTheme);
        applyTheme(currentTheme);
    });

    function applyTheme(theme) {
        const html = document.documentElement;
        const body = document.body;
        
        console.log('Applying theme:', theme);
        
        // 既存のテーマクラスを削除
        html.classList.remove('dark', 'light');
        body.classList.remove('dark', 'light');
        
        if (theme === 'dark') {
            html.classList.add('dark');
            body.classList.add('dark');
            console.log('Dark mode applied');
        } else if (theme === 'light') {
            html.classList.add('light');
            body.classList.add('light');
            console.log('Light mode applied');
        } else if (theme === 'system') {
            // システム設定に従う
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                html.classList.add('dark');
                body.classList.add('dark');
                console.log('System dark mode applied');
            } else {
                html.classList.add('light');
                body.classList.add('light');
                console.log('System light mode applied');
            }
        }
        
        // ストレージに保存
        try {
            localStorage.setItem('theme', theme);
            console.log('Theme saved to localStorage:', theme);
        } catch (e) {
            console.warn('Could not save theme to localStorage:', e);
        }
    }

    function showSaveMessage() {
        const message = document.getElementById('save-message');
        if (message) {
            message.classList.remove('hidden');
            setTimeout(() => {
                message.classList.add('hidden');
            }, 3000);
        }
    }

    // システムテーマ変更の監視
    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            const currentTheme = @json($theme);
            if (currentTheme === 'system') {
                console.log('System theme changed, reapplying');
                applyTheme('system');
            }
        });
    }
});
</script>

<style>
/* ラジオボタンの選択状態を視覚的に示す */
input[type="radio"]:checked + * {
    border-color: rgb(59 130 246);
    background-color: rgb(239 246 255);
}

.dark input[type="radio"]:checked + * {
    background-color: rgba(59, 130, 246, 0.2);
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
</style>