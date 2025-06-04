<?php

namespace App\Jobs;

use App\Models\{Certificate, Subscription, User, Payment};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\{Log, Storage, Mail};
use Carbon\Carbon;

/**
 * Generate SSL Reports Job
 * SSL関連レポートの生成
 */
class GenerateSSLReportsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $reportType;
    private array $options;
    private ?string $emailTo;

    public function __construct(string $reportType, array $options = [], ?string $emailTo = null)
    {
        $this->reportType = $reportType;
        $this->options = $options;
        $this->emailTo = $emailTo;
    }

    public function handle(): void
    {
        try {
            Log::info('Starting SSL report generation', [
                'report_type' => $this->reportType,
                'options' => $this->options,
                'email_to' => $this->emailTo
            ]);

            $reportData = $this->generateReport();
            $filePath = $this->saveReport($reportData);

            if ($this->emailTo) {
                $this->emailReport($filePath, $reportData);
            }

            Log::info('SSL report generation completed', [
                'report_type' => $this->reportType,
                'file_path' => $filePath,
                'data_points' => count($reportData['data'] ?? [])
            ]);

        } catch (\Exception $e) {
            Log::error('SSL report generation failed', [
                'report_type' => $this->reportType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * レポートを生成
     */
    private function generateReport(): array
    {
        return match ($this->reportType) {
            'certificate_usage' => $this->generateCertificateUsageReport(),
            'provider_performance' => $this->generateProviderPerformanceReport(),
            'subscription_analysis' => $this->generateSubscriptionAnalysisReport(),
            'security_audit' => $this->generateSecurityAuditReport(),
            'financial_summary' => $this->generateFinancialSummaryReport(),
            'expiry_forecast' => $this->generateExpiryForecastReport(),
            default => throw new \InvalidArgumentException("Unknown report type: {$this->reportType}")
        };
    }

    /**
     * 証明書利用状況レポート
     */
    private function generateCertificateUsageReport(): array
    {
        $startDate = isset($this->options['start_date']) 
            ? Carbon::parse($this->options['start_date'])
            : now()->subMonth();
        $endDate = isset($this->options['end_date'])
            ? Carbon::parse($this->options['end_date'])
            : now();

        $certificates = Certificate::whereBetween('created_at', [$startDate, $endDate])->get();

        $data = [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString()
            ],
            'summary' => [
                'total_certificates' => $certificates->count(),
                'by_status' => $certificates->groupBy('status')->map->count(),
                'by_provider' => $certificates->groupBy('provider')->map->count(),
                'by_type' => $certificates->groupBy('type')->map->count(),
            ],
            'trends' => [
                'daily_issuance' => $this->getCertificateIssuanceTrend($startDate, $endDate),
                'provider_adoption' => $this->getProviderAdoptionTrend($startDate, $endDate),
            ],
            'top_domains' => $certificates->groupBy('domain')
                                        ->map->count()
                                        ->sortDesc()
                                        ->take(10),
        ];

        return [
            'title' => 'Certificate Usage Report',
            'generated_at' => now()->toISOString(),
            'data' => $data
        ];
    }

    /**
     * プロバイダーパフォーマンスレポート
     */
    private function generateProviderPerformanceReport(): array
    {
        $startDate = isset($this->options['start_date']) 
            ? Carbon::parse($this->options['start_date'])
            : now()->subMonth();
        $endDate = isset($this->options['end_date'])
            ? Carbon::parse($this->options['end_date'])
            : now();

        $certificates = Certificate::whereBetween('created_at', [$startDate, $endDate])->get();
        
        $providerStats = [];
        foreach ($certificates->groupBy('provider') as $provider => $certs) {
            $issued = $certs->where('status', Certificate::STATUS_ISSUED);
            $failed = $certs->where('status', Certificate::STATUS_FAILED);
            
            $avgIssuanceTime = $this->calculateAverageIssuanceTime($certs);
            
            $providerStats[$provider] = [
                'total_requests' => $certs->count(),
                'successful_issuance' => $issued->count(),
                'failed_issuance' => $failed->count(),
                'success_rate' => $certs->count() > 0 ? round(($issued->count() / $certs->count()) * 100, 2) : 0,
                'average_issuance_time_minutes' => $avgIssuanceTime,
                'average_validity_days' => $issued->avg(function ($cert) {
                    return $cert->expires_at && $cert->issued_at 
                        ? $cert->expires_at->diffInDays($cert->issued_at)
                        : null;
                }),
            ];
        }

        return [
            'title' => 'Provider Performance Report',
            'generated_at' => now()->toISOString(),
            'data' => [
                'period' => [
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString()
                ],
                'provider_stats' => $providerStats,
                'recommendations' => $this->generateProviderRecommendations($providerStats)
            ]
        ];
    }

    /**
     * サブスクリプション分析レポート
     */
    private function generateSubscriptionAnalysisReport(): array
    {
        $subscriptions = Subscription::with(['certificates', 'payments'])->get();
        
        $data = [
            'total_subscriptions' => $subscriptions->count(),
            'by_status' => $subscriptions->groupBy('status')->map->count(),
            'by_plan' => $subscriptions->groupBy('plan_type')->map->count(),
            'revenue_metrics' => [
                'total_mrr' => $subscriptions->where('status', 'active')->sum('price') / 100,
                'avg_revenue_per_user' => $subscriptions->where('status', 'active')->avg('price') / 100,
                'total_certificates' => $subscriptions->sum(function ($sub) {
                    return $sub->certificates->count();
                }),
            ],
            'usage_metrics' => [
                'avg_certificates_per_subscription' => $subscriptions->avg(function ($sub) {
                    return $sub->certificates->count();
                }),
                'domain_utilization' => $subscriptions->map(function ($sub) {
                    $used = $sub->certificates->count();
                    $max = $sub->max_domains;
                    return $max > 0 ? ($used / $max) * 100 : 0;
                })->avg(),
            ],
            'churn_analysis' => [
                'cancelled_this_month' => $subscriptions->where('cancelled_at', '>=', now()->startOfMonth())->count(),
                'at_risk_subscriptions' => $subscriptions->where('payment_failed_attempts', '>=', 2)->count(),
            ]
        ];

        return [
            'title' => 'Subscription Analysis Report',
            'generated_at' => now()->toISOString(),
            'data' => $data
        ];
    }

    /**
     * セキュリティ監査レポート
     */
    private function generateSecurityAuditReport(): array
    {
        $certificates = Certificate::all();
        
        $securityIssues = [];
        $recommendations = [];

        // 期限切れ間近の証明書
        $expiringSoon = $certificates->filter(function ($cert) {
            return $cert->isExpiringSoon(7);
        });

        if ($expiringSoon->isNotEmpty()) {
            $securityIssues[] = [
                'type' => 'expiring_certificates',
                'severity' => 'high',
                'count' => $expiringSoon->count(),
                'description' => 'Certificates expiring within 7 days'
            ];
            $recommendations[] = 'Schedule immediate renewal for expiring certificates';
        }

        // 失効済み証明書の確認
        $revokedCerts = $certificates->where('status', Certificate::STATUS_REVOKED);
        if ($revokedCerts->isNotEmpty()) {
            $securityIssues[] = [
                'type' => 'revoked_certificates',
                'severity' => 'medium',
                'count' => $revokedCerts->count(),
                'description' => 'Revoked certificates still in system'
            ];
        }

        // 弱いキーサイズの検出
        $weakKeys = $certificates->filter(function ($cert) {
            $certData = $cert->certificate_data;
            if (isset($certData['key_size'])) {
                return $certData['key_size'] < 2048;
            }
            return false;
        });

        if ($weakKeys->isNotEmpty()) {
            $securityIssues[] = [
                'type' => 'weak_keys',
                'severity' => 'high',
                'count' => $weakKeys->count(),
                'description' => 'Certificates with key size less than 2048 bits'
            ];
            $recommendations[] = 'Replace certificates with weak key sizes';
        }

        return [
            'title' => 'Security Audit Report',
            'generated_at' => now()->toISOString(),
            'data' => [
                'security_score' => $this->calculateSecurityScore($securityIssues),
                'issues_found' => count($securityIssues),
                'security_issues' => $securityIssues,
                'recommendations' => $recommendations,
                'compliance_status' => $this->checkComplianceStatus($certificates)
            ]
        ];
    }

    /**
     * 財務サマリーレポート
     */
    private function generateFinancialSummaryReport(): array
    {
        $startDate = isset($this->options['start_date']) 
            ? Carbon::parse($this->options['start_date'])
            : now()->startOfMonth();
        $endDate = isset($this->options['end_date'])
            ? Carbon::parse($this->options['end_date'])
            : now()->endOfMonth();

        $payments = Payment::whereBetween('paid_at', [$startDate, $endDate])
                          ->where('status', 'completed')
                          ->get();

        $subscriptions = Subscription::where('status', 'active')->get();

        return [
            'title' => 'Financial Summary Report',
            'generated_at' => now()->toISOString(),
            'data' => [
                'period' => [
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString()
                ],
                'revenue' => [
                    'total_revenue' => $payments->sum('amount') / 100,
                    'average_payment' => $payments->avg('amount') / 100,
                    'payment_count' => $payments->count(),
                    'mrr' => $subscriptions->sum('price') / 100,
                    'arr_projection' => ($subscriptions->sum('price') / 100) * 12,
                ],
                'by_plan' => $subscriptions->groupBy('plan_type')->map(function ($subs) {
                    return [
                        'count' => $subs->count(),
                        'revenue' => $subs->sum('price') / 100
                    ];
                }),
                'payment_methods' => $payments->groupBy('payment_method')->map->count(),
                'failed_payments' => Payment::whereBetween('created_at', [$startDate, $endDate])
                                           ->where('status', 'failed')
                                           ->count(),
            ]
        ];
    }

    /**
     * 期限切れ予測レポート
     */
    private function generateExpiryForecastReport(): array
    {
        $certificates = Certificate::where('status', Certificate::STATUS_ISSUED)
                                  ->whereNotNull('expires_at')
                                  ->get();

        $forecast = [];
        for ($i = 1; $i <= 12; $i++) {
            $targetMonth = now()->addMonths($i);
            $expiring = $certificates->filter(function ($cert) use ($targetMonth) {
                return $cert->expires_at->isSameMonth($targetMonth);
            });

            $forecast[] = [
                'month' => $targetMonth->format('Y-m'),
                'expiring_count' => $expiring->count(),
                'by_provider' => $expiring->groupBy('provider')->map->count(),
                'renewal_workload' => $this->estimateRenewalWorkload($expiring)
            ];
        }

        return [
            'title' => 'Certificate Expiry Forecast Report',
            'generated_at' => now()->toISOString(),
            'data' => [
                'forecast_period' => '12 months',
                'monthly_forecast' => $forecast,
                'total_renewals_needed' => $certificates->count(),
                'peak_renewal_month' => collect($forecast)->sortByDesc('expiring_count')->first()
            ]
        ];
    }

    /**
     * レポートを保存
     */
    private function saveReport(array $reportData): string
    {
        $fileName = sprintf(
            'ssl_reports/%s_%s_%s.json',
            $this->reportType,
            now()->format('Y-m-d_H-i-s'),
            uniqid()
        );

        Storage::disk('local')->put($fileName, json_encode($reportData, JSON_PRETTY_PRINT));
        
        return $fileName;
    }

    /**
     * レポートをメール送信
     */
    private function emailReport(string $filePath, array $reportData): void
    {
        try {
            Mail::raw(
                sprintf(
                    "SSL Report: %s\n\nGenerated at: %s\n\nPlease find the detailed report attached.",
                    $reportData['title'],
                    $reportData['generated_at']
                ),
                function ($message) use ($filePath, $reportData) {
                    $message->to($this->emailTo)
                           ->subject("SSL Report: {$reportData['title']}")
                           ->attach(storage_path('app/' . $filePath));
                }
            );

            Log::info('SSL report emailed successfully', [
                'report_type' => $this->reportType,
                'email_to' => $this->emailTo,
                'file_path' => $filePath
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to email SSL report', [
                'report_type' => $this->reportType,
                'email_to' => $this->emailTo,
                'error' => $e->getMessage()
            ]);
        }
    }

    // ヘルパーメソッド

    private function getCertificateIssuanceTrend(Carbon $start, Carbon $end): array
    {
        $trend = [];
        $current = $start->copy();
        
        while ($current->lte($end)) {
            $dayCount = Certificate::whereDate('created_at', $current)->count();
            $trend[$current->toDateString()] = $dayCount;
            $current->addDay();
        }
        
        return $trend;
    }

    private function getProviderAdoptionTrend(Carbon $start, Carbon $end): array
    {
        $certificates = Certificate::whereBetween('created_at', [$start, $end])->get();
        
        return $certificates->groupBy(function ($cert) {
            return $cert->created_at->format('Y-m-d');
        })->map(function ($dayCerts) {
            return $dayCerts->groupBy('provider')->map->count();
        });
    }

    private function calculateAverageIssuanceTime($certificates): ?float
    {
        $times = $certificates->filter(function ($cert) {
            return $cert->issued_at && $cert->created_at;
        })->map(function ($cert) {
            return $cert->created_at->diffInMinutes($cert->issued_at);
        });

        return $times->isNotEmpty() ? $times->avg() : null;
    }

    private function generateProviderRecommendations(array $providerStats): array
    {
        $recommendations = [];
        
        foreach ($providerStats as $provider => $stats) {
            if ($stats['success_rate'] < 95) {
                $recommendations[] = "Consider reviewing {$provider} configuration - success rate is {$stats['success_rate']}%";
            }
            
            if ($stats['average_issuance_time_minutes'] > 60) {
                $recommendations[] = "{$provider} has high issuance time ({$stats['average_issuance_time_minutes']} minutes)";
            }
        }
        
        return $recommendations;
    }

    private function calculateSecurityScore(array $issues): int
    {
        $score = 100;
        
        foreach ($issues as $issue) {
            $penalty = match ($issue['severity']) {
                'high' => 20,
                'medium' => 10,
                'low' => 5,
                default => 5
            };
            $score -= $penalty;
        }
        
        return max(0, $score);
    }

    private function checkComplianceStatus($certificates): array
    {
        return [
            'pci_dss_compliant' => $certificates->where('type', 'EV')->count() > 0,
            'min_key_size_met' => $certificates->filter(function ($cert) {
                $certData = $cert->certificate_data;
                return isset($certData['key_size']) && $certData['key_size'] >= 2048;
            })->count() === $certificates->count(),
            'auto_renewal_coverage' => $certificates->filter(function ($cert) {
                return $cert->supportsAutoRenewal();
            })->count() / max(1, $certificates->count()) * 100,
        ];
    }

    private function estimateRenewalWorkload($certificates): string
    {
        $manualRenewals = $certificates->filter(function ($cert) {
            return !$cert->supportsAutoRenewal();
        })->count();
        
        if ($manualRenewals === 0) {
            return 'Low (All automatic)';
        } elseif ($manualRenewals < 5) {
            return 'Medium';
        } else {
            return 'High (' . $manualRenewals . ' manual renewals)';
        }
    }

    /**
     * Job失敗時の処理
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Generate SSL reports job failed completely', [
            'report_type' => $this->reportType,
            'options' => $this->options,
            'email_to' => $this->emailTo,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}