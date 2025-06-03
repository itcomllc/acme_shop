<?php

namespace App\Http\Controllers\Acme;

use App\Http\Controllers\Controller;
use App\Models\{AcmeAccount, AcmeOrder, Subscription, EabCredential};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{Cache, Log};
use App\Services\{AcmeJwsService, GoGetSSLService, GoogleCertificateManagerService};

/**
 * EAB対応 ACME Directory Controller
 * RFC 8555 + RFC 8739 (External Account Binding)
 */
class AcmeDirectoryController extends Controller
{
    public function directory(): JsonResponse
    {
        return response()->json([
            'newNonce' => route('acme.new-nonce'),
            'newAccount' => route('acme.new-account'), 
            'newOrder' => route('acme.new-order'),
            'revokeCert' => route('acme.revoke-cert'),
            'keyChange' => route('acme.key-change'),
            'meta' => [
                'termsOfService' => route('acme.terms'),
                'website' => config('app.url'),
                'caaIdentities' => [parse_url(config('app.url'))['host']],
                'externalAccountRequired' => true, // EAB必須
                'website' => route('ssl.dashboard')
            ]
        ]);
    }

    public function newNonce(): JsonResponse
    {
        $nonce = $this->generateNonce();
        
        return response()->json(null, 204)
            ->header('Replay-Nonce', $nonce)
            ->header('Cache-Control', 'no-store');
    }

    private function generateNonce(): string
    {
        $nonce = base64url_encode(random_bytes(32));
        Cache::put("acme_nonce_{$nonce}", true, now()->addMinutes(5));
        return $nonce;
    }
}