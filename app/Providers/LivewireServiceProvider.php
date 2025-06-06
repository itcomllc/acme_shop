<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class LivewireServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
        Livewire::component('ssl-dashboard', \App\Livewire\SslDashboard::class);
        Livewire::component('create-certificate', \App\Livewire\CreateCertificate::class);
        Livewire::component('create-subscription', \App\Livewire\CreateSubscription::class);
        Livewire::component('validation-instructions', \App\Livewire\ValidationInstructions::class);
        Livewire::component('subscription-selection', \App\Livewire\SubscriptionSelection::class);
        Livewire::component('settings.appearance', \App\Livewire\Settings\Appearance::class);
        Livewire::component('settings.profile', \App\Livewire\Settings\Profile::class);
        Livewire::component('settings.password', \App\Livewire\Settings\Password::class);
    }
}
