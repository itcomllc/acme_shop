<?php

use Illuminate\Support\Facades\Auth;
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

<x-layouts.app.sidebar title="Settings">
    <flux:main>
        <div class="space-y-6">
            @include('partials.settings-heading')

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Profile Information -->
                <div class="lg:col-span-2">
                    <flux:card>
                        <flux:card.header>
                            <flux:heading size="lg">{{ __('Profile Information') }}</flux:heading>
                            <flux:subheading>{{ __("Update your account's profile information and email address.") }}</flux:subheading>
                        </flux:card.header>

                        <form wire:submit="updateProfile" class="space-y-6">
                            <flux:field>
                                <flux:label>{{ __('Name') }}</flux:label>
                                <flux:input wire:model="name" name="name" required />
                                <flux:error name="name" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Email') }}</flux:label>
                                <flux:input wire:model="email" name="email" type="email" required />
                                <flux:error name="email" />
                            </flux:field>

                            @if (Auth::user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! Auth::user()->hasVerifiedEmail())
                                <div>
                                    <p class="text-sm text-zinc-800 dark:text-zinc-200">
                                        {{ __('Your email address is unverified.') }}
                                        <button type="button" class="underline text-sm text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100">
                                            {{ __('Click here to re-send the verification email.') }}
                                        </button>
                                    </p>
                                </div>
                            @endif

                            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                        </form>
                    </flux:card>
                </div>

                <!-- Update Password -->
                <div>
                    <flux:card>
                        <flux:card.header>
                            <flux:heading size="lg">{{ __('Update Password') }}</flux:heading>
                            <flux:subheading>{{ __('Ensure your account is using a long, random password to stay secure.') }}</flux:subheading>
                        </flux:card.header>

                        <form wire:submit="updatePassword" class="space-y-6">
                            <flux:field>
                                <flux:label>{{ __('Current Password') }}</flux:label>
                                <flux:input wire:model="current_password" name="current_password" type="password" />
                                <flux:error name="current_password" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('New Password') }}</flux:label>
                                <flux:input wire:model="password" name="password" type="password" />
                                <flux:error name="password" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Confirm Password') }}</flux:label>
                                <flux:input wire:model="password_confirmation" name="password_confirmation" type="password" />
                                <flux:error name="password_confirmation" />
                            </flux:field>

                            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                        </form>
                    </flux:card>
                </div>
            </div>

            <!-- SSL-related Settings -->
            <div class="mt-8">
                <flux:card>
                    <flux:card.header>
                        <flux:heading size="lg">{{ __('SSL Certificate Preferences') }}</flux:heading>
                        <flux:subheading>{{ __('Configure your SSL certificate and notification preferences.') }}</flux:subheading>
                    </flux:card.header>

                    <div class="space-y-6">
                        @auth
                            @if(Auth::user()->activeSubscription)
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <flux:field>
                                        <flux:label>{{ __('Default Provider') }}</flux:label>
                                        <flux:select name="default_provider">
                                            <option value="gogetssl">GoGetSSL</option>
                                            <option value="google_certificate_manager">Google Certificate Manager</option>
                                            <option value="lets_encrypt">Let's Encrypt</option>
                                        </flux:select>
                                    </flux:field>

                                    <flux:field>
                                        <flux:label>{{ __('Certificate Type') }}</flux:label>
                                        <flux:select name="certificate_type" disabled>
                                            <option value="{{ Auth::user()->activeSubscription->certificate_type }}">
                                                {{ Auth::user()->activeSubscription->certificate_type }}
                                            </option>
                                        </flux:select>
                                        <flux:description>{{ __('Determined by your subscription plan') }}</flux:description>
                                    </flux:field>
                                </div>

                                <div class="flex items-center space-x-3">
                                    <flux:checkbox name="email_notifications" checked />
                                    <flux:label>{{ __('Email notifications for certificate expiry') }}</flux:label>
                                </div>

                                <div class="flex items-center space-x-3">
                                    <flux:checkbox name="auto_renewal" checked />
                                    <flux:label>{{ __('Enable automatic certificate renewal') }}</flux:label>
                                </div>
                            @else
                                <div class="text-center py-8">
                                    <flux:icon.shield-exclamation class="mx-auto h-12 w-12 text-zinc-400" />
                                    <h3 class="mt-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('No SSL Subscription') }}</h3>
                                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('You need an active SSL subscription to configure certificate preferences.') }}</p>
                                    <div class="mt-6">
                                        <flux:button href="{{ route('ssl.dashboard') }}" variant="primary">
                                            {{ __('Get SSL Subscription') }}
                                        </flux:button>
                                    </div>
                                </div>
                            @endif
                        @endauth
                    </div>
                </flux:card>
            </div>
        </div>
    </flux:main>
</x-layouts.app.sidebar>

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