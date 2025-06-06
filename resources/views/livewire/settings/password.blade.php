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

<x-layouts.app.sidebar title="Password Settings">
    <flux:main>
        <div class="space-y-6">
            @include('partials.settings-heading')

            <div class="max-w-2xl">
                <flux:card>
                    <flux:card.header>
                        <flux:heading size="lg">{{ __('Update Password') }}</flux:heading>
                        <flux:subheading>{{ __('Ensure your account is using a long, random password to stay secure.') }}</flux:subheading>
                    </flux:card.header>

                    <form wire:submit="updatePassword" class="space-y-6">
                        <flux:field>
                            <flux:label>{{ __('Current Password') }}</flux:label>
                            <flux:input 
                                wire:model="current_password" 
                                name="current_password" 
                                type="password" 
                                autocomplete="current-password"
                                required
                            />
                            <flux:error name="current_password" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('New Password') }}</flux:label>
                            <flux:input 
                                wire:model="password" 
                                name="password" 
                                type="password" 
                                autocomplete="new-password"
                                required
                            />
                            <flux:error name="password" />
                            <flux:description>
                                {{ __('Password must be at least 8 characters long and contain a mix of letters, numbers, and symbols.') }}
                            </flux:description>
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Confirm Password') }}</flux:label>
                            <flux:input 
                                wire:model="password_confirmation" 
                                name="password_confirmation" 
                                type="password" 
                                autocomplete="new-password"
                                required
                            />
                            <flux:error name="password_confirmation" />
                        </flux:field>

                        <div class="flex items-center justify-between pt-4">
                            <flux:button type="submit" variant="primary">
                                {{ __('Update Password') }}
                            </flux:button>
                            
                            <flux:link href="{{ route('settings.profile') }}" variant="ghost">
                                {{ __('Back to Profile') }}
                            </flux:link>
                        </div>
                    </form>
                </flux:card>

                <!-- Password Security Tips -->
                <flux:card class="mt-6">
                    <flux:card.header>
                        <flux:heading size="lg">{{ __('Password Security Tips') }}</flux:heading>
                    </flux:card.header>

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
                </flux:card>
            </div>
        </div>
    </flux:main>
</x-layouts.app.sidebar>

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