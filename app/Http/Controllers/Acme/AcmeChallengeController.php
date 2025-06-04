<?php

namespace App\Http\Controllers\Acme;

use App\Http\Controllers\Controller;
use App\Models\{AcmeAccount, AcmeChallenge, AcmeAuthorization};
use App\Jobs\ValidateAcmeChallenge;
use Illuminate\Http\{Request, JsonResponse, Response};
use Illuminate\Support\Facades\{Cache, Log};
use App\Services\AcmeJwsService;

/**
 * ACME Challenge Controller  
 */
class AcmeChallengeController extends Controller
{
    public function __construct(
        private readonly AcmeJwsService $jwsService
    ) {}

    public function challenge(Request $request, AcmeChallenge $challenge): JsonResponse
    {
        try {
            $jws = $this->jwsService->verifyJws($request->getContent());
            $account = $this->getAccountFromJws($jws);
            
            // チャレンジの所有権確認
            if ($challenge->authorization->order->account_id !== $account->id) {
                return response()->json([
                    'type' => 'urn:ietf:params:acme:error:unauthorized',
                    'detail' => 'Challenge does not belong to this account'
                ], 403);
            }

            // チャレンジが開始可能か確認
            if ($challenge->status !== 'pending') {
                return response()->json([
                    'type' => 'urn:ietf:params:acme:error:malformed',
                    'detail' => 'Challenge is not in pending status'
                ], 400);
            }
            
            // チャレンジ開始
            $challenge->update(['status' => 'processing']);
            
            // key authorization を生成
            $keyAuthorization = $this->generateKeyAuthorization($challenge, $account);
            $challenge->update(['key_authorization' => $keyAuthorization]);
            
            // バックグラウンドで検証実行
            ValidateAcmeChallenge::dispatch($challenge);
            
            Log::info('ACME challenge initiated', [
                'challenge_id' => $challenge->id,
                'type' => $challenge->type,
                'domain' => $challenge->authorization->identifier['value'] ?? 'unknown'
            ]);
            
            return response()->json([
                'type' => $challenge->type,
                'status' => $challenge->status,
                'url' => route('acme.challenge', $challenge->id),
                'token' => $challenge->token,
                'validated' => $challenge->validated?->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('ACME challenge processing failed', [
                'challenge_id' => $challenge->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'type' => 'urn:ietf:params:acme:error:serverInternal',
                'detail' => 'Failed to process challenge'
            ], 500);
        }
    }

    /**
     * HTTP-01チャレンジのレスポンス
     */
    public function httpChallenge(Request $request, string $token): Response
    {
        try {
            $challenge = AcmeChallenge::where('token', $token)
                                   ->where('type', 'http-01')
                                   ->first();

            if (!$challenge) {
                return response('Challenge not found', 404);
            }

            if (!$challenge->key_authorization) {
                return response('Challenge not ready', 404);
            }

            Log::info('HTTP-01 challenge accessed', [
                'challenge_id' => $challenge->id,
                'token' => $token,
                'domain' => $challenge->authorization->identifier['value'] ?? 'unknown'
            ]);

            return response($challenge->key_authorization, 200)
                ->header('Content-Type', 'text/plain');

        } catch (\Exception $e) {
            Log::error('HTTP-01 challenge response failed', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);

            return response('Internal server error', 500);
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

    /**
     * Key Authorization生成
     */
    private function generateKeyAuthorization(AcmeChallenge $challenge, AcmeAccount $account): string
    {
        $accountKeyThumbprint = $account->public_key_thumbprint;
        return $challenge->token . '.' . $accountKeyThumbprint;
    }
}