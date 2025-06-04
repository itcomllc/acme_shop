<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $email_verified = false; // 追加

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        try {
            $validated = $this->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
                'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
                'email_verified' => ['boolean'], // 追加
            ]);

            $validated['password'] = Hash::make($validated['password']);
            
            // email_verified_atの設定を追加
            if ($this->email_verified) {
                $validated['email_verified_at'] = now();
            }

            \DB::beginTransaction();
            
            try {
                /** @var User $user */
                $user = User::create($validated);
                
                // Registeredイベントを発火
                event(new Registered($user));

                // ログ出力でデバッグ
                \Log::info('User registered', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'email_verified_checkbox' => $this->email_verified,
                    'email_verified_at' => $user->email_verified_at
                ]);

                \DB::commit();
                
                Auth::login($user);

                $this->redirectIntended(route('dashboard', absolute: false), navigate: true);
                
            } catch (\Exception $e) {
                \DB::rollBack();
                
                \Log::error('User registration failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                session()->flash('error', 'Registration failed. Please try again.');
                return;
            }
            
        } catch (\Exception $e) {
            \Log::error('Registration validation failed', [
                'error' => $e->getMessage(),
                'input' => $this->only(['name', 'email', 'email_verified'])
            ]);
            
            session()->flash('error', 'Please check your input and try again.');
            return;
        }
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="register" class="flex flex-col gap-6">
        <!-- Name -->
        <flux:input
            wire:model="name"
            :label="__('Name')"
            type="text"
            required
            autofocus
            autocomplete="name"
            :placeholder="__('Full name')"
        />

        <!-- Email Address -->
        <flux:input
            wire:model="email"
            :label="__('Email address')"
            type="email"
            required
            autocomplete="email"
            placeholder="email@example.com"
        />

        <!-- Password -->
        <flux:input
            wire:model="password"
            :label="__('Password')"
            type="password"
            required
            autocomplete="new-password"
            :placeholder="__('Password')"
            viewable
        />

        <!-- Confirm Password -->
        <flux:input
            wire:model="password_confirmation"
            :label="__('Confirm password')"
            type="password"
            required
            autocomplete="new-password"
            :placeholder="__('Confirm password')"
            viewable
        />

        <!-- Email Verified Checkbox -->
        <div class="flex items-center">
            <input 
                type="checkbox" 
                wire:model="email_verified" 
                id="email_verified"
                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
            >
            <label for="email_verified" class="ml-2 block text-sm text-gray-900">
                {{ __('Mark email as verified') }}
            </label>
        </div>

        <div class="flex items-center justify-end">
            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Create account') }}
            </flux:button>
        </div>
    </form>

    <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
        {{ __('Already have an account?') }}
        <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
    </div>
</div>