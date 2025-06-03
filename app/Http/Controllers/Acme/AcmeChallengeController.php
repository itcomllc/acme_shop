<?php

namespace App\Http\Controllers\Acme;

use App\Http\Controllers\Controller;
use App\Models\{AcmeChallenge, AcmeOrder, Subscription, EabCredential};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{Cache, Log};
use App\Services\{AcmeJwsService, GoGetSSLService, GoogleCertificateManagerService};


/**
 * ACME Challenge Controller  
 */
class AcmeChallengeController extends Controller
{
    public function challenge(Request $request, string $challengeId): JsonResponse
    {
        $jws = $this->jwsService->verifyJws($request->getContent());
        $challenge = AcmeChallenge::findOrFail($challengeId);
        
        // チャレンジ開始
        $challenge->update(['status' => 'processing']);
        
        // バックグラウンドで検証実行
        dispatch(new ValidateAcmeChallenge($challenge));
        
        return response()->json([
            'type' => $challenge->type,
            'status' => $challenge->status,
            'url' => route('acme.challenge', $challenge->id),
            'token' => $challenge->token
        ]);
    }
}

