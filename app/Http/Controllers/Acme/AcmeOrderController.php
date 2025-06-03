<?php

namespace App\Http\Controllers\Acme;

use App\Http\Controllers\Controller;
use App\Models\{AcmeAccount, AcmeOrder, Subscription, EabCredential};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{Cache, Log};
use App\Services\{AcmeJwsService, GoGetSSLService, GoogleCertificateManagerService};


/**
 * ACME Order Controller
 */
class AcmeOrderController extends Controller
{
    public function __construct(
        private readonly AcmeJwsService $jwsService,
        private readonly GoGetSSLService $goGetSSL,
        private readonly GoogleCertificateManagerService $googleCM
    ) {}

    public function newOrder(Request $request): JsonResponse
    {
        // JWS検証・アカウント認証
        $jws = $this->jwsService->verifyJws($request->getContent());
        $account = $this->getAccountFromJws($jws);
        
        $payload = $jws['payload'];
        $identifiers = $payload['identifiers'];
        
        // ドメイン制限確認
        $this->validateDomainLimits($account->subscription, $identifiers);
        
        // ACME Order作成
        $order = AcmeOrder::create([
            'account_id' => $account->id,
            'subscription_id' => $account->subscription_id,
            'identifiers' => $identifiers,
            'status' => 'pending',
            'expires' => now()->addDays(7)
        ]);

        // Authorization & Challenge作成
        $authorizations = [];
        foreach ($identifiers as $identifier) {
            $authz = $this->createAuthorization($order, $identifier);
            $authorizations[] = route('acme.authorization', $authz->id);
        }

        // バックエンドCA選択
        $provider = $this->selectProvider($account->subscription, $identifiers);
        $order->update(['selected_provider' => $provider]);

        $orderUrl = route('acme.order', $order->id);

        return response()->json([
            'status' => $order->status,
            'expires' => $order->expires->toISOString(),
            'identifiers' => $order->identifiers,
            'authorizations' => $authorizations,
            'finalize' => route('acme.order.finalize', $order->id)
        ], 201)->header('Location', $orderUrl);
    }

    private function selectProvider(Subscription $subscription, array $identifiers): string
    {
        // サブスクリプションプランに基づくプロバイダー選択
        return match ($subscription->plan_type) {
            'basic' => 'google_certificate_manager', // 無料証明書
            'professional', 'enterprise' => 'gogetssl', // 有料証明書
            default => 'google_certificate_manager'
        };
    }

    private function validateDomainLimits(Subscription $subscription, array $identifiers): void
    {
        $domainCount = count($identifiers);
        if ($domainCount > $subscription->max_domains) {
            throw new \Exception("Domain limit exceeded: {$domainCount}/{$subscription->max_domains}", 429);
        }
    }
}
