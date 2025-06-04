<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AdminControllerBase;
use App\Models\{User, Role, Permission, Certificate, Subscription, Payment};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Log, Cache, Auth};
use Carbon\Carbon;

/**
 * Admin Dashboard Controller
 * 管理画面のダッシュボード機能を提供
 */
class AdminDashboardController extends AdminControllerBase
{
    /**
     * Display admin dashboard
     */
    public function index(Request $request)
    {
        $this->authorize('admin.access');

        // 基本統計を取得
        $stats = $this->getDashboardStatistics();
        
        // 最近のアクティビティを取得
        $recentActivity = $this->getRecentActivity();
        
        // システムヘルスをチェック
        $systemHealth = $this->getSystemHealth();
        
        // チャート用データを取得
        $chartData = $this->getChartData();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'recent_activity' => $recentActivity,
                    'system_health' => $systemHealth,
                    'chart_data' => $chartData
                ]
            ]);
        }

        return view('admin.dashboard', compact('stats', 'recentActivity', 'systemHealth', 'chartData'));
    }

    /**
     * Get dashboard statistics
     */
    private function getDashboardStatistics(): array
    {
        $cacheKey = 'admin_dashboard_stats';
        
        return Cache::remember($cacheKey, now()->addMinutes(5), function () {
            $stats = [];

            // ユーザー統計
            $stats['users'] = [
                'total' => User::count(),
                'verified' => User::whereNotNull('email_verified_at')->count(),
                'new_this_month' => User::where('created_at', '>=', now()->startOfMonth())->count(),
                'active_this_week' => User::where('last_login_at', '>=', now()->subWeek())->count(),
            ];

            // サブスクリプション統計
            $stats['subscriptions'] = [
                'total' => Subscription::count(),
                'active' => Subscription::where('status', 'active')->count(),
                'cancelled' => Subscription::where('status', 'cancelled')->count(),
                'revenue_this_month' => Payment::where('status', 'completed')
                    ->where('paid_at', '>=', now()->startOfMonth())
                    ->sum('amount'),
            ];

            // 証明書統計
            $stats['certificates'] = [
                'total' => Certificate::count(),
                'issued' => Certificate::where('status', 'issued')->count(),
                'pending' => Certificate::where('status', 'pending_validation')->count(),
                'expiring_soon' => Certificate::where('status', 'issued')
                    ->where('expires_at', '<=', now()->addDays(30))
                    ->where('expires_at', '>', now())
                    ->count(),
            ];

            // システム統計
            $stats['system'] = [
                'total_roles' => Role::count(),
                'total_permissions' => Permission::count(),
                'system_load' => $this->getSystemLoad(),
                'uptime' => $this->getUptime(),
            ];

            return $stats;
        });
    }

    /**
     * Get recent activity
     */
    private function getRecentActivity(): array
    {
        $activities = [];

        // 最近のユーザー登録
        $recentUsers = User::latest()
            ->take(5)
            ->get(['id', 'name', 'email', 'created_at'])
            ->map(function ($user) {
                return [
                    'type' => 'user_registered',
                    'description' => "New user registered: {$user->name}",
                    'timestamp' => $user->created_at,
                    'user' => $user->only(['id', 'name', 'email'])
                ];
            });

        // 最近のサブスクリプション
        $recentSubscriptions = Subscription::with('user')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($subscription) {
                return [
                    'type' => 'subscription_created',
                    'description' => "New {$subscription->plan_type} subscription by {$subscription->user->name}",
                    'timestamp' => $subscription->created_at,
                    'subscription' => $subscription->only(['id', 'plan_type', 'status']),
                    'user' => $subscription->user->only(['id', 'name'])
                ];
            });

        // 最近の証明書発行
        $recentCertificates = Certificate::with('subscription.user')
            ->where('status', 'issued')
            ->latest('issued_at')
            ->take(5)
            ->get()
            ->map(function ($certificate) {
                return [
                    'type' => 'certificate_issued',
                    'description' => "SSL certificate issued for {$certificate->domain}",
                    'timestamp' => $certificate->issued_at,
                    'certificate' => $certificate->only(['id', 'domain', 'status']),
                    'user' => $certificate->subscription->user->only(['id', 'name'])
                ];
            });

        // アクティビティをマージして時間順にソート
        $allActivities = collect()
            ->merge($recentUsers)
            ->merge($recentSubscriptions)
            ->merge($recentCertificates)
            ->sortByDesc('timestamp')
            ->take(10)
            ->values();

        return $allActivities->toArray();
    }

    /**
     * Get system health information
     */
    private function getSystemHealth(): array
    {
        $health = [
            'overall_status' => 'healthy',
            'checks' => []
        ];

        try {
            // データベース接続チェック
            DB::connection()->getPdo();
            $health['checks']['database'] = [
                'status' => 'healthy',
                'message' => 'Database connection successful',
                'response_time' => $this->measureDbResponseTime()
            ];
        } catch (\Exception $e) {
            $health['checks']['database'] = [
                'status' => 'unhealthy',
                'message' => 'Database connection failed',
                'error' => $e->getMessage()
            ];
            $health['overall_status'] = 'unhealthy';
        }

        // キャッシュチェック
        try {
            Cache::put('health_check', 'test', 10);
            $value = Cache::get('health_check');
            Cache::forget('health_check');
            
            $health['checks']['cache'] = [
                'status' => $value === 'test' ? 'healthy' : 'unhealthy',
                'message' => $value === 'test' ? 'Cache working correctly' : 'Cache not working'
            ];
        } catch (\Exception $e) {
            $health['checks']['cache'] = [
                'status' => 'unhealthy',
                'message' => 'Cache error',
                'error' => $e->getMessage()
            ];
            $health['overall_status'] = 'unhealthy';
        }

        // SSL プロバイダーチェック
        try {
            if (class_exists(\App\Services\CertificateProviderFactory::class)) {
                /** @var \App\Services\CertificateProviderFactory */
                $factory = app(\App\Services\CertificateProviderFactory::class);
                $providerStatus = $factory->getProviderStatus();
                
                $health['checks']['ssl_providers'] = [
                    'status' => $providerStatus['configured_providers'] > 0 ? 'healthy' : 'warning',
                    'message' => "Configured providers: {$providerStatus['configured_providers']}/{$providerStatus['total_providers']}",
                    'providers' => $providerStatus['available_providers']
                ];
            } else {
                $health['checks']['ssl_providers'] = [
                    'status' => 'warning',
                    'message' => 'SSL provider factory not available'
                ];
            }
        } catch (\Exception $e) {
            $health['checks']['ssl_providers'] = [
                'status' => 'unhealthy',
                'message' => 'SSL provider check failed',
                'error' => $e->getMessage()
            ];
        }

        return $health;
    }

    /**
     * Get chart data for dashboard
     */
    private function getChartData(): array
    {
        $chartData = [];

        // 過去30日間のユーザー登録数
        $chartData['user_registrations'] = $this->getUserRegistrationChart();
        
        // 過去30日間の収益
        $chartData['revenue'] = $this->getRevenueChart();
        
        // 証明書発行状況
        $chartData['certificate_status'] = $this->getCertificateStatusChart();
        
        // プロバイダー使用状況
        $chartData['provider_usage'] = $this->getProviderUsageChart();

        return $chartData;
    }

    /**
     * Get user registration chart data
     */
    private function getUserRegistrationChart(): array
    {
        $days = collect(range(29, 0))->map(function ($daysBack) {
            $date = now()->subDays($daysBack);
            $count = User::whereDate('created_at', $date)->count();
            
            return [
                'date' => $date->format('Y-m-d'),
                'label' => $date->format('M j'),
                'count' => $count
            ];
        });

        return [
            'labels' => $days->pluck('label')->toArray(),
            'data' => $days->pluck('count')->toArray(),
            'total' => $days->sum('count')
        ];
    }

    /**
     * Get revenue chart data
     */
    private function getRevenueChart(): array
    {
        $days = collect(range(29, 0))->map(function ($daysBack) {
            $date = now()->subDays($daysBack);
            $revenue = Payment::where('status', 'completed')
                ->whereDate('paid_at', $date)
                ->sum('amount');
            
            return [
                'date' => $date->format('Y-m-d'),
                'label' => $date->format('M j'),
                'revenue' => $revenue / 100 // Convert cents to dollars
            ];
        });

        return [
            'labels' => $days->pluck('label')->toArray(),
            'data' => $days->pluck('revenue')->toArray(),
            'total' => $days->sum('revenue')
        ];
    }

    /**
     * Get certificate status distribution
     */
    private function getCertificateStatusChart(): array
    {
        $statusCounts = Certificate::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $statusLabels = [
            'issued' => 'Issued',
            'pending_validation' => 'Pending Validation',
            'processing' => 'Processing',
            'failed' => 'Failed',
            'expired' => 'Expired',
            'revoked' => 'Revoked'
        ];

        $labels = [];
        $data = [];
        $colors = [
            'issued' => '#10b981',
            'pending_validation' => '#f59e0b', 
            'processing' => '#3b82f6',
            'failed' => '#ef4444',
            'expired' => '#6b7280',
            'revoked' => '#8b5cf6'
        ];

        foreach ($statusCounts as $status => $count) {
            $labels[] = $statusLabels[$status] ?? ucfirst($status);
            $data[] = $count;
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'colors' => array_values($colors)
        ];
    }

    /**
     * Get provider usage distribution
     */
    private function getProviderUsageChart(): array
    {
        $providerCounts = Certificate::select('provider', DB::raw('count(*) as count'))
            ->whereNotNull('provider')
            ->groupBy('provider')
            ->pluck('count', 'provider')
            ->toArray();

        $providerLabels = [
            'gogetssl' => 'GoGetSSL',
            'google_certificate_manager' => 'Google CM',
            'lets_encrypt' => "Let's Encrypt"
        ];

        $labels = [];
        $data = [];

        foreach ($providerCounts as $provider => $count) {
            $labels[] = $providerLabels[$provider] ?? ucfirst($provider);
            $data[] = $count;
        }

        return [
            'labels' => $labels,
            'data' => $data
        ];
    }

    /**
     * Measure database response time
     */
    private function measureDbResponseTime(): float
    {
        $start = microtime(true);
        DB::select('SELECT 1');
        $end = microtime(true);
        
        return round(($end - $start) * 1000, 2); // milliseconds
    }

    /**
     * Get system load (simplified)
     */
    private function getSystemLoad(): string
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return sprintf('%.2f %.2f %.2f', $load[0], $load[1], $load[2]);
        }
        
        return 'N/A';
    }

    /**
     * Get system uptime (simplified)
     */
    private function getUptime(): string
    {
        if (function_exists('shell_exec') && !in_array('shell_exec', explode(',', ini_get('disable_functions')))) {
            $uptime = shell_exec('uptime -p 2>/dev/null');
            if ($uptime) {
                return trim($uptime);
            }
        }
        
        return 'N/A';
    }

    /**
     * Get system overview for detailed system page
     */
    public function systemOverview(Request $request): JsonResponse
    {
        $this->authorize('system.health.view');

        $overview = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database_version' => $this->getDatabaseVersion(),
            'memory_usage' => [
                'current' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'limit' => ini_get('memory_limit')
            ],
            'disk_usage' => $this->getDiskUsage(),
            'queue_status' => $this->getQueueStatus(),
            'cache_status' => $this->getCacheStatus()
        ];

        return response()->json([
            'success' => true,
            'data' => $overview
        ]);
    }

    /**
     * Get database version
     */
    private function getDatabaseVersion(): string
    {
        try {
            $version = DB::select('SELECT VERSION() as version')[0]->version ?? 'Unknown';
            return $version;
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Get disk usage information
     */
    private function getDiskUsage(): array
    {
        $path = base_path();
        
        if (function_exists('disk_free_space') && function_exists('disk_total_space')) {
            $free = disk_free_space($path);
            $total = disk_total_space($path);
            $used = $total - $free;
            
            return [
                'total' => $total,
                'used' => $used,
                'free' => $free,
                'percentage' => round(($used / $total) * 100, 2)
            ];
        }
        
        return [
            'total' => 0,
            'used' => 0,
            'free' => 0,
            'percentage' => 0
        ];
    }

    /**
     * Get queue status
     */
    private function getQueueStatus(): array
    {
        try {
            // Redisの場合の簡単なチェック
            $queueSize = 0;
            if (config('queue.default') === 'redis') {
                // 実際の実装では適切なキューサイズの取得方法を使用
                $queueSize = 0;
            }
            
            return [
                'driver' => config('queue.default'),
                'pending_jobs' => $queueSize,
                'status' => 'healthy'
            ];
        } catch (\Exception $e) {
            return [
                'driver' => config('queue.default'),
                'pending_jobs' => 0,
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get cache status
     */
    private function getCacheStatus(): array
    {
        try {
            $driver = config('cache.default');
            $testKey = 'admin_cache_test_' . time();
            
            Cache::put($testKey, 'test', 10);
            $value = Cache::get($testKey);
            Cache::forget($testKey);
            
            return [
                'driver' => $driver,
                'status' => $value === 'test' ? 'healthy' : 'error',
                'test_successful' => $value === 'test'
            ];
        } catch (\Exception $e) {
            return [
                'driver' => config('cache.default'),
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
}