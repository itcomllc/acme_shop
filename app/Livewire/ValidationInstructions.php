<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Certificate;
use Illuminate\Support\Facades\Auth;

class ValidationInstructions extends Component
{
    public $certificate;
    public $showInstructions = false;
    public $validationData = null;

    public function mount(Certificate $certificate)
    {
        $this->certificate = $certificate;
    }

    public function showValidationInstructions()
    {
        if ($this->certificate->subscription->user_id !== Auth::id()) {
            return;
        }

        $validationRecords = $this->certificate->validationRecords;
        $instructions = [];

        foreach ($validationRecords as $record) {
            switch ($record->type) {
                case 'http-01':
                    $instructions[] = [
                        'type' => 'HTTP',
                        'description' => 'Place the following file on your web server',
                        'file_path' => "/.well-known/acme-challenge/{$record->token}",
                        'file_content' => $record->key_authorization,
                        'verification_url' => "http://{$this->certificate->domain}/.well-known/acme-challenge/{$record->token}"
                    ];
                    break;
                
                case 'dns-01':
                    $instructions[] = [
                        'type' => 'DNS',
                        'description' => 'Add the following DNS TXT record',
                        'record_name' => "_acme-challenge.{$this->certificate->domain}",
                        'record_value' => base64url_encode(hash('sha256', $record->key_authorization, true)),
                        'ttl' => 300
                    ];
                    break;
            }
        }

        $this->validationData = [
            'certificate' => $this->certificate,
            'validation_instructions' => $instructions,
            'status' => $this->certificate->status
        ];

        $this->showInstructions = true;
    }

    public function closeInstructions()
    {
        $this->showInstructions = false;
    }

    public function render()
    {
        return view('livewire.validation-instructions');
    }
}
