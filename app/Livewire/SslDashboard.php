<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\SSLSaaSService;
use App\Models\{Subscription, Certificate};
use Illuminate\Support\Facades\Auth;

class SslDashboard extends Component
{
    public $showNewCertModal = false;
    public $showSubscriptionModal = false;
    public $dashboardData = null;
    public $loading = true;

    protected $listeners = [
        'certificateCreated' => 'refreshDashboard',
        'subscriptionCreated' => 'refreshDashboard',
        'closeModal' => 'closeModals'
    ];

    public function mount()
    {
        $this->loadDashboardData();
    }

    public function loadDashboardData()
    {
        $user = Auth::user();
        $subscription = $user->activeSubscription;

        if (!$subscription) {
            $this->dashboardData = [
                'has_subscription' => false,
                'plans' => $this->getAvailablePlans()
            ];
        } else {
            $certificates = $subscription->certificates()->with(['validationRecords'])->get();
            
            $this->dashboardData = [
                'has_subscription' => true,
                'subscription' => $subscription,
                'certificates' => $certificates,
                'stats' => [
                    'total_certificates' => $certificates->count(),
                    'active_certificates' => $certificates->where('status', 'issued')->count(),
                    'pending_certificates' => $certificates->where('status', 'pending_validation')->count(),
                    'expiring_soon' => $certificates->where('expires_at', '<=', now()->addDays(30))->count(),
                    'domains_used' => $certificates->count(),
                    'domains_limit' => $subscription->max_domains
                ]
            ];
        }

        $this->loading = false;
    }

    public function openNewCertModal()
    {
        $this->showNewCertModal = true;
    }

    public function openSubscriptionModal()
    {
        $this->showSubscriptionModal = true;
    }

    public function closeModals()
    {
        $this->showNewCertModal = false;
        $this->showSubscriptionModal = false;
    }

    public function refreshDashboard()
    {
        $this->loadDashboardData();
        $this->closeModals();
    }

    private function getAvailablePlans()
    {
        return [
            'basic' => [
                'name' => 'Basic SSL',
                'price' => '$9.99/month',
                'domains' => 1,
                'certificate_type' => 'Domain Validated',
                'features' => [
                    '1 SSL Certificate',
                    'Domain Validation',
                    'ACME Automation',
                    '99.9% Uptime SLA'
                ]
            ],
            'professional' => [
                'name' => 'Professional SSL',
                'price' => '$29.99/month',
                'domains' => 5,
                'certificate_type' => 'Organization Validated',
                'features' => [
                    'Up to 5 SSL Certificates',
                    'Organization Validation',
                    'ACME Automation',
                    'Priority Support',
                    '99.9% Uptime SLA'
                ]
            ],
            'enterprise' => [
                'name' => 'Enterprise SSL',
                'price' => '$99.99/month',
                'domains' => 100,
                'certificate_type' => 'Extended Validation',
                'features' => [
                    'Up to 100 SSL Certificates',
                    'Extended Validation',
                    'ACME Automation',
                    'Dedicated Support',
                    '99.9% Uptime SLA',
                    'Custom Integrations'
                ]
            ]
        ];
    }

    public function render()
    {
        return view('livewire.ssl-dashboard');
    }
}
