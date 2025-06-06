<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;

class SubscriptionSelection extends Component
{
    public $plans = [];

    public function mount($plans)
    {
        $this->plans = $plans;
    }

    #[On('openSubscriptionModal')]
    public function openSubscriptionModal()
    {
        $this->dispatch('openSubscriptionModal');
    }

    public function render()
    {
        return view('livewire.subscription-selection');
    }
}