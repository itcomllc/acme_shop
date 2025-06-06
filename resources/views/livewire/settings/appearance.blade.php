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

<x-layouts.app.sidebar title="Appearance Settings">
    <flux:main>
        <div class="space-y-6">
            @include('partials.settings-heading')

            <div class="max-w-2xl">
                <flux:card>
                    <flux:card.header>
                        <flux:heading size="lg">{{ __('Appearance') }}</flux:heading>
                        <flux:subheading>{{ __('Customize the look and feel of your SSL SaaS Platform experience.') }}</flux:subheading>
                    </flux:card.header>

                    <form wire:submit="updateAppearance" class="space-y-6">
                        <!-- Theme Selection -->
                        <flux:field>
                            <flux:label>{{ __('Theme') }}</flux:label>
                            <flux:radio.group wire:model.live="theme" name="theme" class="grid grid-cols-3 gap-4">
                                <flux:radio value="light" class="flex flex-col items-center p-4 border rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                    <flux:icon.sun class="h-8 w-8 mb-2 text-yellow-500" />
                                    <span class="text-sm font-medium">{{ __('Light') }}</span>
                                </flux:radio>
                                
                                <flux:radio value="dark" class="flex flex-col items-center p-4 border rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                    <flux:icon.moon class="h-8 w-8 mb-2 text-zinc-600" />
                                    <span class="text-sm font-medium">{{ __('Dark') }}</span>
                                </flux:radio>
                                
                                <flux:radio value="system" class="flex flex-col items-center p-4 border rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                    <flux:icon.computer-desktop class="h-8 w-8 mb-2 text-zinc-500" />
                                    <span class="text-sm font-medium">{{ __('System') }}</span>
                                </flux:radio>
                            </flux:radio.group>
                            <flux:description>{{ __('Choose your preferred theme. System will match your device settings.') }}</flux:description>
                        </flux:field>

                        <!-- Language Selection -->
                        <flux:field>
                            <flux:label>{{ __('Language') }}</flux:label>
                            <flux:select wire:model="language" name="language">
                                <option value="en">English</option>
                                <option value="ja">日本語 (Japanese)</option>
                                <option value="es">Español (Spanish)</option>
                                <option value="fr">Français (French)</option>
                                <option value="de">Deutsch (German)</option>
                                <option value="zh">中文 (Chinese)</option>
                            </flux:select>
                            <flux:description>{{ __('Select your preferred language for the interface.') }}</flux:description>
                        </flux:field>

                        <!-- Timezone Selection -->
                        <flux:field>
                            <flux:label>{{ __('Timezone') }}</flux:label>
                            <flux:select wire:model="timezone" name="timezone">
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
                            </flux:select>
                            <flux:description>{{ __('Your timezone affects how dates and times are displayed.') }}</flux:description>
                        </flux:field>

                        <!-- Animation Preferences -->
                        <div class="space-y-4">
                            <flux:heading size="md">{{ __('Interface Preferences') }}</flux:heading>
                            
                            <flux:field>
                                <div class="flex items-center justify-between">
                                    <div>
                                        <flux:label>{{ __('Enable Animations') }}</flux:label>
                                        <flux:description>{{ __('Show smooth transitions and animations throughout the interface.') }}</flux:description>
                                    </div>
                                    <flux:switch wire:model="animations" name="animations" />
                                </div>
                            </flux:field>

                            <flux:field>
                                <div class="flex items-center justify-between">
                                    <div>
                                        <flux:label>{{ __('Sound Notifications') }}</flux:label>
                                        <flux:description>{{ __('Play sounds for important notifications and alerts.') }}</flux:description>
                                    </div>
                                    <flux:switch wire:model="sound_notifications" name="sound_notifications" />
                                </div>
                            </flux:field>
                        </div>

                        <div class="flex items-center justify-between pt-4 border-t">
                            <flux:button type="button" wire:click="resetToDefaults" variant="ghost">
                                {{ __('Reset to Defaults') }}
                            </flux:button>
                            
                            <div class="flex space-x-3">
                                <flux:link href="{{ route('settings.profile') }}" variant="ghost">
                                    {{ __('Back to Profile') }}
                                </flux:link>
                                <flux:button type="submit" variant="primary">
                                    {{ __('Save Changes') }}
                                </flux:button>
                            </div>
                        </div>
                    </form>
                </flux:card>

                <!-- Preview Section -->
                <flux:card class="mt-6">
                    <flux:card.header>
                        <flux:heading size="lg">{{ __('Preview') }}</flux:heading>
                        <flux:subheading>{{ __('See how your settings will look in the SSL dashboard.') }}</flux:subheading>
                    </flux:card.header>

                    <div class="space-y-4">
                        <!-- Sample SSL Certificate Card -->
                        <div class="border rounded-lg p-4 {{ $theme === 'dark' ? 'bg-zinc-800 border-zinc-700' : 'bg-white border-zinc-200' }}">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-medium {{ $theme === 'dark' ? 'text-white' : 'text-zinc-900' }}">
                                        example.com
                                    </h3>
                                    <p class="text-sm {{ $theme === 'dark' ? 'text-zinc-400' : 'text-zinc-600' }}">
                                        DV Certificate • GoGetSSL
                                    </p>
                                </div>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Active
                                </span>
                            </div>
                        </div>

                        <p class="text-sm {{ $theme === 'dark' ? 'text-zinc-400' : 'text-zinc-600' }}">
                            {{ __('This is how certificate cards will appear with your current theme selection.') }}
                        </p>
                    </div>
                </flux:card>
            </div>
        </div>
    </flux:main>
</x-layouts.app.sidebar>

<script>
document.addEventListener('livewire:init', () => {
    Livewire.on('appearance-updated', () => {
        // Apply theme changes immediately
        const theme = @json($theme);
        if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        window.dispatchEvent(new CustomEvent('banner-message', {
            detail: { 
                style: 'success', 
                message: 'Appearance settings updated successfully.' 
            }
        }));
    });
});
</script>