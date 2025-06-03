<?php

namespace App\Livewire;

use Livewire\Component;

class SubscriptionSelection extends Component
{
    public $plans = [];

    protected $listeners = ['openSubscriptionModal'];

    public function mount($plans)
    {
        $this->plans = $plans;
    }

    public function openSubscriptionModal()
    {
        $this->emit('openSubscriptionModal');
    }

    public function render()
    {
        return view('livewire.subscription-selection');
    }
}
