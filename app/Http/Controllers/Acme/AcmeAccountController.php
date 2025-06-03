<?php

namespace App\Http\Controllers\Acme;

use App\Http\Controllers\Controller;
use App\Models\{AcmeAccount, AcmeOrder, Subscription, EabCredential};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{Cache, Log};
use App\Services\{AcmeJwsService, GoGetSSLService, GoogleCertificateManagerService};



/**
 * EAB対応 ACME Account Controller
 */
class AcmeAccountController extends Controller
{
    public function __construct(
        private readonly AcmeJwsService $jwsService
    ) {}

    public function newAccount(Request $request): JsonResponse
    {
        // 1. JWS検証
        $jws = $this->jwsService->verifyJws($request->getContent());
        
        // 2. EAB検証（必須）
        $eabCredential = $this->verifyExternalAccountBinding($jws['payload']);
        
        // 3. アカウント作成
        $account = AcmeAccount::create([
            'subscription_id' => $eabCredential->subscription_id,
            'public_key' => $jws['header']['jwk'],
            'public_key_thumbprint' => $this->jwsService->getKeyThumbprint($jws['header']['jwk']),
            'contacts' => $jws['payload']['contact'] ?? [],
            'terms_of_service_agreed' => $jws['payload']['termsOfServiceAgreed'] ?? false,
            'status' => 'valid',
            'eab_credential_id' => $eabCredential->id
        ]);

        $accountUrl = route('acme.account', $account->id);

        return response()->json([
            'status' => 'valid',
            'contact' => $account->contacts,
            'termsOfServiceAgreed' => $account->terms_of_service_agreed,
            'orders' => route('acme.account.orders', $account->id)
        ], 201)->header('Location', $accountUrl);
    }

    /**
     * EAB検証 (RFC 8739)
     */
    private function verifyExternalAccountBinding(array $payload): EabCredential
    {
        if (!isset($payload['externalAccountBinding'])) {
            throw new \Exception('External Account Binding required', 400);
        }

        $eab = $payload['externalAccountBinding'];
        
        // EAB JWS検証
        $eabJws = $this->jwsService->verifyEabJws($eab);
        
        // MAC ID確認
        $macId = $eabJws['header']['kid'];
        $eabCredential = EabCredential::where('mac_id', $macId)
            ->where('is_active', true)
            ->first();

        if (!$eabCredential) {
            throw new \Exception('Invalid MAC ID', 403);
        }

        // MAC KEY検証
        if (!$this->jwsService->verifyEabMac($eab, $eabCredential->mac_key)) {
            throw new \Exception('Invalid MAC signature', 403);
        }

        // サブスクリプション状態確認
        if (!$eabCredential->subscription->isActive()) {
            throw new \Exception('Subscription not active', 403);
        }

        return $eabCredential;
    }
}

