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

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Update Password')" :subheading="__('Ensure your account is using a long, random password to stay secure.')">
        <form wire:submit="updatePassword" class="my-6 w-full space-y-6">
            <flux:input 
                wire:model="current_password" 
                :label="__('Current Password')"
                name="current_password" 
                type="password" 
                autocomplete="current-password"
                required
            />

            <flux:input 
                wire:model="password" 
                :label="__('New Password')"
                name="password" 
                type="password" 
                autocomplete="new-password"
                required
            />

            <flux:input 
                wire:model="password_confirmation" 
                :label="__('Confirm Password')"
                name="password_confirmation" 
                type="password" 
                autocomplete="new-password"
                required
            />

            <div class="flex items-center justify-between pt-4">
                <flux:button type="submit" variant="primary">
                    {{ __('Update Password') }}
                </flux:button>
                
                <div class="flex items-center gap-4">
                    <flux:link href="{{ route('settings.profile') }}" variant="ghost">
                        {{ __('Back to Profile') }}
                    </flux:link>
                    
                    <x-action-message class="me-3" on="password-updated">
                        {{ __('Password updated.') }}
                    </x-action-message>
                </div>
            </div>
        </form>

        <!-- Password Security Tips -->
        <div class="mt-8 pt-8 border-t border-gray-200 dark:border-gray-700">
            <div class="mb-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ __('Password Security Tips') }}</h3>
            </div>

            <div class="space-y-4">
                <div class="flex items-start space-x-3">
                    <flux:icon.check-circle class="h-5 w-5 text-green-500 mt-0.5 flex-shrink-0" />
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Use at least 12 characters with a mix of uppercase, lowercase, numbers, and symbols') }}
                    </p>
                </div>
                
                <div class="flex items-start space-x-3">
                    <flux:icon.check-circle class="h-5 w-5 text-green-500 mt-0.5 flex-shrink-0" />
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Avoid using personal information like your name, birthday, or common words') }}
                    </p>
                </div>
                
                <div class="flex items-start space-x-3">
                    <flux:icon.check-circle class="h-5 w-5 text-green-500 mt-0.5 flex-shrink-0" />
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Consider using a password manager to generate and store strong, unique passwords') }}
                    </p>
                </div>
                
                <div class="flex items-start space-x-3">
                    <flux:icon.check-circle class="h-5 w-5 text-green-500 mt-0.5 flex-shrink-0" />
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Never reuse passwords across different accounts, especially for important services') }}
                    </p>
                </div>
            </div>
        </div>
    </x-settings.layout>
</section>

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