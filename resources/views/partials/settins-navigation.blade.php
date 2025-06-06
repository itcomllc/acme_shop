<!-- Settings Navigation -->
<div class="mb-6">
    <flux:tabs>
        <flux:tab 
            :href="route('settings.profile')" 
            :current="request()->routeIs('settings.profile')"
            wire:navigate
        >
            {{ __('Profile') }}
        </flux:tab>
        
        <flux:tab 
            :href="route('settings.password')" 
            :current="request()->routeIs('settings.password')"
            wire:navigate
        >
            {{ __('Password') }}
        </flux:tab>
        
        <flux:tab 
            :href="route('settings.appearance')" 
            :current="request()->routeIs('settings.appearance')"
            wire:navigate
        >
            {{ __('Appearance') }}
        </flux:tab>

        @auth
            @if(Auth::user()->activeSubscription)
                <flux:tab 
                    :href="route('ssl.eab.index')" 
                    :current="request()->routeIs('ssl.eab.*')"
                    wire:navigate
                >
                    {{ __('ACME Settings') }}
                </flux:tab>
            @endif
        @endauth
    </flux:tabs>
</div>
