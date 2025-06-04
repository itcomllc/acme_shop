<?php

namespace App\Http\Controllers;

use App\Models\{EabCredential, Subscription};
use App\Services\EabCredentialService;
use Illuminate\Http\{Request, JsonResponse, RedirectResponse};
use Illuminate\Support\Facades\{Auth, Log};
use Illuminate\View\View;

/**
 * EAB (External Account Binding) Controller
 * ACME EAB認証情報の管理
 */
class EabController extends Controller
{
    public function __construct(
        private readonly EabCredentialService $eabService
    ) {}

    /**
     * EAB管理画面表示
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $subscription = $user->activeSubscription;

        if (!$subscription) {
            return view('ssl.eab.no-subscription');
        }

        $eabCredentials = $subscription->eabCredentials()
                                     ->orderBy('created_at', 'desc')
                                     ->paginate(10);

        $stats = [
            'total_credentials' => $subscription->eabCredentials()->count(),
            'active_credentials' => $subscription->eabCredentials()->where('is_active', true)->count(),
            'total_usage' => $subscription->eabCredentials()->sum('usage_count'),
            'last_used' => $subscription->eabCredentials()
                                     ->whereNotNull('last_used_at')
                                     ->latest('last_used_at')
                                     ->first()?->last_used_at
        ];

        return view('ssl.eab.index', [
            'subscription' => $subscription,
            'eabCredentials' => $eabCredentials,
            'stats' => $stats,
            'acmeDirectoryUrl' => route('acme.directory')
        ]);
    }

    /**
     * 新しいEAB認証情報を生成
     */
    public function generate(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $subscription = $user->activeSubscription;

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Active subscription required'
                ], 400);
            }

            if (!$subscription->isActive()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription is not active'
                ], 400);
            }

            // アクティブなEAB認証情報の数制限チェック
            $activeCredentials = $subscription->eabCredentials()->where('is_active', true)->count();
            $maxCredentials = $this->getMaxCredentialsForPlan($subscription->plan_type);

            if ($activeCredentials >= $maxCredentials) {
                return response()->json([
                    'success' => false,
                    'message' => "Maximum EAB credentials limit reached ({$maxCredentials})"
                ], 400);
            }

            // 新しいEAB認証情報を生成
            $eabCredential = $this->eabService->createEabCredentials($subscription);

            Log::info('EAB credential generated', [
                'subscription_id' => $subscription->id,
                'eab_credential_id' => $eabCredential->id,
                'mac_id' => $eabCredential->mac_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'EAB credentials generated successfully',
                'data' => [
                    'id' => $eabCredential->id,
                    'mac_id' => $eabCredential->mac_id,
                    'mac_key' => $eabCredential->mac_key, // 初回表示のみ
                    'created_at' => $eabCredential->created_at->toISOString(),
                    'is_active' => $eabCredential->is_active,
                    'usage_count' => $eabCredential->usage_count
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('EAB credential generation failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate EAB credentials',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * EAB認証情報を無効化
     */
    public function revoke(Request $request, EabCredential $credential): JsonResponse
    {
        try {
            $user = Auth::user();

            // 所有権確認
            if ($credential->subscription->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'EAB credential not found'
                ], 404);
            }

            if (!$credential->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'EAB credential is already revoked'
                ], 400);
            }

            // 認証情報を無効化
            $credential->revoke();

            // 関連するACMEアカウントも無効化
            $credential->acmeAccounts()->update(['status' => 'revoked']);

            Log::info('EAB credential revoked', [
                'eab_credential_id' => $credential->id,
                'mac_id' => $credential->mac_id,
                'subscription_id' => $credential->subscription_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'EAB credential revoked successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('EAB credential revocation failed', [
                'eab_credential_id' => $credential->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke EAB credential',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * EAB認証情報詳細表示
     */
    public function show(Request $request, EabCredential $credential): JsonResponse
    {
        try {
            $user = Auth::user();

            // 所有権確認
            if ($credential->subscription->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'EAB credential not found'
                ], 404);
            }

            $acmeAccounts = $credential->acmeAccounts()
                                     ->with(['orders' => function ($query) {
                                         $query->latest()->limit(5);
                                     }])
                                     ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $credential->id,
                    'mac_id' => $credential->mac_id,
                    'is_active' => $credential->is_active,
                    'usage_count' => $credential->usage_count,
                    'last_used_at' => $credential->last_used_at?->toISOString(),
                    'created_at' => $credential->created_at->toISOString(),
                    'acme_accounts' => $acmeAccounts->map(function ($account) {
                        return [
                            'id' => $account->id,
                            'status' => $account->status,
                            'contacts' => $account->contacts,
                            'created_at' => $account->created_at->toISOString(),
                            'orders_count' => $account->orders->count(),
                            'recent_orders' => $account->orders->map(function ($order) {
                                return [
                                    'id' => $order->id,
                                    'status' => $order->status,
                                    'identifiers' => $order->identifiers,
                                    'created_at' => $order->created_at->toISOString()
                                ];
                            })
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('EAB credential details retrieval failed', [
                'eab_credential_id' => $credential->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve EAB credential details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ACME使用方法説明ページ
     */
    public function instructions(Request $request): View
    {
        $user = Auth::user();
        $subscription = $user->activeSubscription;

        if (!$subscription) {
            return view('ssl.eab.no-subscription');
        }

        $activeCredential = $subscription->eabCredentials()
                                        ->where('is_active', true)
                                        ->latest()
                                        ->first();

        return view('ssl.eab.instructions', [
            'subscription' => $subscription,
            'activeCredential' => $activeCredential,
            'acmeDirectoryUrl' => route('acme.directory'),
            'clientExamples' => $this->getClientExamples()
        ]);
    }

    /**
     * EAB認証情報統計API
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $subscription = $user->activeSubscription;

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Active subscription required'
                ], 400);
            }

            $stats = [
                'total_credentials' => $subscription->eabCredentials()->count(),
                'active_credentials' => $subscription->eabCredentials()->where('is_active', true)->count(),
                'revoked_credentials' => $subscription->eabCredentials()->where('is_active', false)->count(),
                'total_usage' => $subscription->eabCredentials()->sum('usage_count'),
                'max_credentials' => $this->getMaxCredentialsForPlan($subscription->plan_type),
                'recent_activity' => $subscription->eabCredentials()
                                               ->whereNotNull('last_used_at')
                                               ->latest('last_used_at')
                                               ->limit(5)
                                               ->get()
                                               ->map(function ($credential) {
                                                   return [
                                                       'mac_id' => $credential->mac_id,
                                                       'last_used_at' => $credential->last_used_at->toISOString(),
                                                       'usage_count' => $credential->usage_count
                                                   ];
                                               })
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('EAB statistics retrieval failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 一括EAB認証情報無効化
     */
    public function bulkRevoke(Request $request): JsonResponse
    {
        $request->validate([
            'credential_ids' => 'required|array|max:50',
            'credential_ids.*' => 'integer|exists:eab_credentials,id'
        ]);

        try {
            $user = Auth::user();
            $results = [];

            $credentials = EabCredential::whereIn('id', $request->credential_ids)
                                      ->whereHas('subscription', function ($query) use ($user) {
                                          $query->where('user_id', $user->id);
                                      })
                                      ->get();

            foreach ($credentials as $credential) {
                try {
                    if ($credential->is_active) {
                        $credential->revoke();
                        $credential->acmeAccounts()->update(['status' => 'revoked']);
                        
                        $results[] = [
                            'credential_id' => $credential->id,
                            'mac_id' => $credential->mac_id,
                            'status' => 'revoked'
                        ];
                    } else {
                        $results[] = [
                            'credential_id' => $credential->id,
                            'mac_id' => $credential->mac_id,
                            'status' => 'already_revoked'
                        ];
                    }
                } catch (\Exception $e) {
                    $results[] = [
                        'credential_id' => $credential->id,
                        'mac_id' => $credential->mac_id,
                        'status' => 'failed',
                        'error' => $e->getMessage()
                    ];
                }
            }

            Log::info('Bulk EAB credential revocation completed', [
                'user_id' => $user->id,
                'processed_count' => count($results)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bulk revocation completed',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk EAB credential revocation failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bulk revocation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * プラン別の最大EAB認証情報数を取得
     */
    private function getMaxCredentialsForPlan(string $planType): int
    {
        return match ($planType) {
            'basic' => 2,
            'professional' => 5,
            'enterprise' => 20,
            default => 1
        };
    }

    /**
     * ACMEクライアント使用例を取得
     */
    private function getClientExamples(): array
    {
        return [
            'certbot' => [
                'name' => 'Certbot',
                'command' => 'certbot certonly --server {{ACME_DIRECTORY}} --eab-kid {{MAC_ID}} --eab-hmac-key {{MAC_KEY}} -d {{DOMAIN}}',
                'description' => 'EFF Certbot with EAB support'
            ],
            'acme.sh' => [
                'name' => 'acme.sh',
                'command' => 'acme.sh --issue --server {{ACME_DIRECTORY}} --eab-kid {{MAC_ID}} --eab-hmac-key {{MAC_KEY}} -d {{DOMAIN}}',
                'description' => 'acme.sh client with EAB support'
            ],
            'lego' => [
                'name' => 'Lego',
                'command' => 'lego --server={{ACME_DIRECTORY}} --eab --kid={{MAC_ID}} --hmac={{MAC_KEY}} --domains={{DOMAIN}} run',
                'description' => 'Go-based ACME client'
            ],
            'custom' => [
                'name' => 'Custom Implementation',
                'description' => 'Use the MAC ID and MAC Key with any RFC 8555 + RFC 8739 compliant ACME client'
            ]
        ];
    }
}
