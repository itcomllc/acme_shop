<?php

namespace App\Http\Controllers\Acme;

use App\Http\Controllers\Controller;
use App\Models\{AcmeAccount, AcmeAuthorization};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\Log;
use App\Services\AcmeJwsService;

/**
 * ACME Authorization Controller
 */
class AcmeAuthorizationController extends Controller
{
    public function __construct(
        private readonly AcmeJwsService $jwsService
    ) {}

    public function getAuthorization(Request $request, AcmeAuthorization $authorization): JsonResponse
    {
        try {
            // JWS検証・アカウント認証
            $jws = $this->jwsService->verifyJws($request->getContent());
            $account = $this->getAccountFromJws($jws);
            
            // Authorization所有権確認
            if ($authorization->order->account_id !== $account->id) {
                return response()->json([
                    'type' => 'urn:ietf:params:acme:error:unauthorized',
                    'detail' => 'Authorization does not belong to this account'
                ], 403);
            }

            // チャレンジ情報を含むレスポンス構築
            $challenges = $authorization->challenges->map(function ($challenge) {
                return [
                    'type' => $challenge->type,
                    'status' => $challenge->status,
                    'url' => route('acme.challenge', $challenge->id),
                    'token' => $challenge->token,
                    'validated' => $challenge->validated?->toISOString()
                ];
            });

            $response = [
                'status' => $authorization->status,
                'expires' => $authorization->expires->toISOString(),
                'identifier' => $authorization->identifier,
                'challenges' => $challenges->toArray()
            ];

            // 有効な場合は検証日時を追加
            if ($authorization->status === 'valid') {
                $validChallenge = $authorization->challenges->where('status', 'valid')->first();
                if ($validChallenge && $validChallenge->validated) {
                    $response['validated'] = $validChallenge->validated->toISOString();
                }
            }

            Log::info('ACME authorization retrieved', [
                'authorization_id' => $authorization->id,
                'status' => $authorization->status,
                'domain' => $authorization->identifier['value'] ?? 'unknown'
            ]);

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('ACME authorization retrieval failed', [
                'authorization_id' => $authorization->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'type' => 'urn:ietf:params:acme:error:serverInternal',
                'detail' => 'Failed to retrieve authorization'
            ], 500);
        }
    }

    /**
     * JWSからアカウントを取得
     */
    private function getAccountFromJws(array $jws): AcmeAccount
    {
        $protected = $jws['header'];
        
        if (isset($protected['kid'])) {
            // アカウントURL経由
            $accountId = basename($protected['kid']);
            $account = AcmeAccount::findOrFail($accountId);
        } elseif (isset($protected['jwk'])) {
            // 公開鍵経由
            $thumbprint = $this->jwsService->getKeyThumbprint($protected['jwk']);
            $account = AcmeAccount::where('public_key_thumbprint', $thumbprint)->firstOrFail();
        } else {
            throw new \Exception('Account identification required', 400);
        }

        if ($account->status !== 'valid') {
            throw new \Exception('Account is not valid', 403);
        }

        return $account;
    }
}