<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\SSLSaaSService;
use Illuminate\Support\Facades\Auth;

class CreateCertificate extends Component
{
   public $domain = '';
    public $loading = false;
    public $error = '';

    protected $rules = [
        'domain' => 'required|regex:/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/'
    ];

    protected $messages = [
        'domain.required' => 'Domain name is required.',
        'domain.regex' => 'Please enter a valid domain name.'
    ];

    public function createCertificate(SSLSaaSService $sslService)
    {
        $this->validate();
        
        $this->loading = true;
        $this->error = '';

        try {
            $user = Auth::user();
            $subscription = $user->activeSubscription;

            if (!$subscription) {
                throw new \Exception('No active subscription found');
            }

            if ($subscription->certificates()->count() >= $subscription->max_domains) {
                throw new \Exception('Domain limit reached for current plan');
            }

            $certificate = $sslService->issueCertificate($subscription, $this->domain);

            $this->emit('certificateCreated');
            $this->reset(['domain']);
            
            session()->flash('message', 'Certificate creation started for ' . $this->domain);

        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    public function render()
    {
        return view('livewire.create-certificate');
    }
}
