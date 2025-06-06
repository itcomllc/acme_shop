<?php

use Illuminate\Support\Facades\{Auth, Hash};
use Livewire\Volt\Component;

new class extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';
    
    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        Auth::user()->update([
            'password' => Hash::make($this->password),
        ]);

        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->dispatch('password-updated');
    }
}; ?>

<!-- メインのapp.blade.phpレイアウトを使用 -->
<x-layouts.app title="Password Settings">
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
                        <!-- Password Section Header -->
                        <div class="mb-6">
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ __('Update Password') }}</h2>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Ensure your account is using a long, random password to stay secure.') }}</p>
                        </div>

                        <!-- Password Update Form -->
                        <form wire:submit="updatePassword" class="space-y-6">
                            <div>
                                <label for="current_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Current Password') }}</label>
                                <input wire:model="current_password" type="password" id="current_password" name="current_password" required autocomplete="current-password"
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200">
                                @error('current_password') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('New Password') }}</label>
                                <input wire:model="password" type="password" id="password" name="password" required autocomplete="new-password"
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200">
                                @error('password') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Confirm Password') }}</label>
                                <input wire:model="password_confirmation" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200">
                                @error('password_confirmation') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>

                            <div class="flex items-center justify-between pt-4">
                                <a href="{{ route('settings.profile') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition-colors duration-200">
                                    {{ __('Back to Profile') }}
                                </a>
                                
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-800 text-white font-medium py-2 px-4 rounded-lg transition-colors duration-200">
                                    {{ __('Update Password') }}
                                </button>
                            </div>
                        </form>

                        <!-- Password Security Tips -->
                        <div class="mt-8 pt-8 border-t border-gray-200 dark:border-gray-700">
                            <div class="mb-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ __('Password Security Tips') }}</h3>
                            </div>

                            <div class="space-y-4">
                                <div class="flex items-start space-x-3">
                                    <svg class="h-5 w-5 text-green-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ __('Use at least 12 characters with a mix of uppercase, lowercase, numbers, and symbols') }}
                                    </p>
                                </div>
                                
                                <div class="flex items-start space-x-3">
                                    <svg class="h-5 w-5 text-green-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ __('Avoid using personal information like your name, birthday, or common words') }}
                                    </p>
                                </div>
                                
                                <div class="flex items-start space-x-3">
                                    <svg class="h-5 w-5 text-green-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ __('Consider using a password manager to generate and store strong, unique passwords') }}
                                    </p>
                                </div>
                                
                                <div class="flex items-start space-x-3">
                                    <svg class="h-5 w-5 text-green-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ __('Never reuse passwords across different accounts, especially for important services') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>

<script>
document.addEventListener('livewire:init', () => {
    Livewire.on('password-updated', () => {
        window.dispatchEvent(new CustomEvent('banner-message', {
            detail: { 
                style: 'success', 
                message: 'Password updated successfully. Please use your new password for future logins.' 
            }
        }));
    });
});
</script>