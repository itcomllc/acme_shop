<?php

namespace App\Livewire\Settings;

use Livewire\Component;
use Illuminate\Support\Facades\{Auth, Hash};

class Password extends Component
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

    public function render()
    {
        /** @var \Livewire\Component $view */
        $view = view('livewire.settings.password');
        return $view->extends('layouts.app');
    }
}
