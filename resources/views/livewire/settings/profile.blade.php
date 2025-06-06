<?php

use Illuminate\Support\Facades\{Auth, Hash};
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';
    
    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
    }
    
    public function updateProfile(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . Auth::id()],
        ]);

        Auth::user()->update([
            'name' => $this->name,
            'email' => $this->email,
        ]);

        $this->dispatch('profile-updated');
    }
    
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

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfile" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if (Auth::user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! Auth::user()->hasVerifiedEmail())
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full">{{ __('Save') }}</flux:button>
                </div>

                <x-action-message class="me-3" on="profile-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>

        <!-- Password Update Section -->
        <div class="mt-8 pt-8 border-t border-gray-200 dark:border-gray-700">
            <div class="mb-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ __('Update Password') }}</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Ensure your account is using a long, random password to stay secure.') }}</p>
            </div>

            <form wire:submit="updatePassword" class="space-y-6">
                <flux:input 
                    wire:model="current_password" 
                    :label="__('Current Password')" 
                    type="password" 
                    autocomplete="current-password"
                />

                <flux:input 
                    wire:model="password" 
                    :label="__('New Password')" 
                    type="password" 
                    autocomplete="new-password"
                />

                <flux:input 
                    wire:model="password_confirmation" 
                    :label="__('Confirm Password')" 
                    type="password" 
                    autocomplete="new-password"
                />

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit">{{ __('Update Password') }}</flux:button>
                    
                    <x-action-message class="me-3" on="password-updated">
                        {{ __('Password updated.') }}
                    </x-action-message>
                </div>
            </form>
        </div>

        <!-- SSL Certificate Preferences -->
        @auth
            @if(Auth::user()->activeSubscription)
                <div class="mt-8 pt-8 border-t border-gray-200 dark:border-gray-700">
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ __('SSL Certificate Preferences') }}</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Configure your SSL certificate and notification preferences.') }}</p>
                    </div>

                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <flux:select name="default_provider" :label="__('Default Provider')">
                                    <option value="gogetssl">GoGetSSL</option>
                                    <option value="google_certificate_manager">Google Certificate Manager</option>
                                    <option value="lets_encrypt">Let's Encrypt</option>
                                </flux:select>
                            </div>

                            <div>
                                <flux:select name="certificate_type" :label="__('Certificate Type')" disabled>
                                    <option value="{{ Auth::user()->activeSubscription->certificate_type }}">
                                        {{ Auth::user()->activeSubscription->certificate_type }}
                                    </option>
                                </flux:select>
                                <flux:text class="mt-1 text-sm text-gray-500">{{ __('Determined by your subscription plan') }}</flux:text>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <flux:checkbox name="email_notifications" :label="__('Email notifications for certificate expiry')" checked />
                            <flux:checkbox name="auto_renewal" :label="__('Enable automatic certificate renewal')" checked />
                        </div>
                    </div>
                </div>
            @else
                <div class="mt-8 pt-8 border-t border-gray-200 dark:border-gray-700">
                    <div class="text-center py-8">
                        <div class="mx-auto w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">{{ __('No SSL Subscription') }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('You need an active SSL subscription to configure certificate preferences.') }}</p>
                        <div class="mt-4">
                            <flux:button href="{{ route('ssl.dashboard') }}" variant="primary">
                                {{ __('Get SSL Subscription') }}
                            </flux:button>
                        </div>
                    </div>
                </div>
            @endif
        @endauth
    </x-settings.layout>
</section>

<script>
document.addEventListener('livewire:init', () => {
    Livewire.on('profile-updated', () => {
        window.dispatchEvent(new CustomEvent('banner-message', {
            detail: { style: 'success', message: 'Profile updated successfully.' }
        }));
    });

    Livewire.on('password-updated', () => {
        window.dispatchEvent(new CustomEvent('banner-message', {
            detail: { style: 'success', message: 'Password updated successfully.' }
        }));
    });
});
</script>