<?php

namespace App\Services;

use App\Models\{AcmeAccount, AcmeOrder, AcmeAuthorization, AcmeChallenge};
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AcmeService
{
    /**
     * Create new ACME order
     */
    public function createOrder(array $orderData): AcmeOrder
    {
        // Create order record
        $order = AcmeOrder::create([
            'identifiers' => $orderData['identifiers'],
            'profile' => $orderData['profile'] ?? 'classic',
            'status' => 'pending',
            'expires' => now()->addDays(7)
        ]);

        // Create authorizations for each identifier
        foreach ($orderData['identifiers'] as $identifier) {
            $auth = AcmeAuthorization::create([
                'order_id' => $order->id,
                'identifier' => $identifier,
                'status' => 'pending',
                'expires' => now()->addDays(1)
            ]);

            // Create challenges
            $this->createChallengesForAuthorization($auth);
        }

        return $order;
    }

    /**
     * Create challenges for authorization
     */
    private function createChallengesForAuthorization(AcmeAuthorization $auth): void
    {
        $identifier = $auth->identifier;
        
        // HTTP-01 Challenge
        if ($identifier['type'] === 'dns') {
            AcmeChallenge::create([
                'authorization_id' => $auth->id,
                'type' => 'http-01',
                'status' => 'pending',
                'token' => $this->generateToken(),
            ]);
        }

        // DNS-01 Challenge
        AcmeChallenge::create([
            'authorization_id' => $auth->id,
            'type' => 'dns-01',
            'status' => 'pending',
            'token' => $this->generateToken(),
        ]);
    }

    /**
     * Generate challenge token
     */
    private function generateToken(): string
    {
        return base64url_encode(random_bytes(32));
    }

    /**
     * Generate nonce for ACME requests
     */
    public function generateNonce(): string
    {
        return base64url_encode(random_bytes(16));
    }

    /**
     * Get key thumbprint for ACME
     */
    public function getKeyThumbprint(array $publicKey): string
    {
        $jwk = [
            'kty' => $publicKey['kty']
        ];
        
        if ($publicKey['kty'] === 'RSA') {
            $jwk['e'] = $publicKey['e'];
            $jwk['n'] = $publicKey['n'];
        }
        
        ksort($jwk);
        return base64url_encode(hash('sha256', json_encode($jwk), true));
    }
}