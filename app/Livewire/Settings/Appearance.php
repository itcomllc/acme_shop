<?php

namespace App\Livewire\Settings;

use Illuminate\Support\Facades\{Hash, Auth};
use Livewire\Component;

class Appearance extends Component
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

        // JavaScriptのThemeManagerに即座に通知
        $this->dispatch('theme-changed', theme: $value);
    }

    public function updateAppearance(): void
    {
        // Save preferences to session
        session([
            'theme' => $this->theme,
            'locale' => $this->language,
            'animations' => $this->animations,
            'sound_notifications' => $this->sound_notifications,
        ]);

        // Update user timezone in database
        if (Auth::user()) {
            Auth::user()->update(['timezone' => $this->timezone]);
        }

        $this->dispatch('appearance-updated', theme: $this->theme);
        session()->flash('status', 'appearance-updated');
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

    public function render()
    {
        /** @var \Livewire\Volt\Component $view */
        $view = view('livewire.settings.appearance');
        return $view->extends('layouts.app');
    }
}