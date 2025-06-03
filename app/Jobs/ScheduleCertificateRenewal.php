<?php

namespace App\Jobs;

use App\Models\Certificate;
use App\Services\EnhancedSSLSaaSService;
use App\Events\{CertificateRenewed, CertificateFailed};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;

/**
 * Schedule Certificate Renewal Job
 * Handles automatic certificate renewal for all providers
 */
class ScheduleCertificateRenewal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Certificate $certificate;

    public function __construct(Certificate $certificate)
    {
        $this->certificate = $certificate;
    }

    public function handle(EnhancedSSLSaaSService $sslService): void
    {
        try {
            Log::info('Starting certificate renewal process', [
                'certificate_id' => $this->certificate->id,
                'domain' => $this->certificate->domain,
                'provider' => $this->certificate->provider,
                'expires_at' => $this->certificate->expires_at?->toDateTimeString()
            ]);

            // Check if certificate is still valid for renewal
            if (!$this->shouldRenewCertificate()) {
                Log::info('Certificate renewal skipped - not eligible', [
                    'certificate_id' => $this->certificate->id,
                    'reason' => $this->getRenewalSkipReason()
                ]);
                return;
            }

            // Check if subscription is still active
            if (!$this->certificate->subscription->isActive()) {
                Log::warning('Certificate renewal skipped - subscription not active', [
                    'certificate_id' => $this->certificate->id,
                    'subscription_status' => $this->certificate->subscription->status
                ]);
                return;
            }

            // Perform renewal
            $renewalResult = $sslService->renewCertificate($this->certificate);

            if ($renewalResult['success']) {
                Log::info('Certificate renewed successfully', [
                    'old_certificate_id' => $this->certificate->id,
                    'new_certificate_id' => $renewalResult['new_certificate']->id,
                    'domain' => $this->certificate->domain,
                    'provider' => $renewalResult['provider']
                ]);

                // Dispatch renewal success event
                CertificateRenewed::dispatch(
                    $renewalResult['old_certificate'],
                    $renewalResult['new_certificate']
                );

                // Schedule next renewal for the new certificate
                $this->scheduleNextRenewal($renewalResult['new_certificate']);

            } else {
                $this->handleRenewalFailure('Renewal process returned failure');
            }

        } catch (\Exception $e) {
            Log::error('Certificate renewal failed', [
                'certificate_id' => $this->certificate->id,
                'domain' => $this->certificate->domain,
                'provider' => $this->certificate->provider,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->handleRenewalFailure($e->getMessage());
        }
    }

    /**
     * Check if certificate should be renewed
     */
    private function shouldRenewCertificate(): bool
    {
        // Don't renew if already revoked
        if ($this->certificate->isRevoked()) {
            return false;
        }

        // Don't renew if already replaced
        if ($this->certificate->isReplaced()) {
            return false;
        }

        // Don't renew if not issued
        if ($this->certificate->status !== Certificate::STATUS_ISSUED) {
            return false;
        }

        // Don't renew if already expired (should have been done earlier)
        if ($this->certificate->expires_at && $this->certificate->expires_at->isPast()) {
            return false;
        }

        // Check if within renewal window
        $renewalDays = $this->certificate->subscription->renewal_before_days ?? 30;
        $renewalDate = $this->certificate->expires_at?->subDays($renewalDays);

        return $renewalDate && $renewalDate->isPast();
    }

    /**
     * Get reason why renewal was skipped
     */
    private function getRenewalSkipReason(): string
    {
        if ($this->certificate->isRevoked()) {
            return 'Certificate is revoked';
        }

        if ($this->certificate->isReplaced()) {
            return 'Certificate has been replaced';
        }

        if ($this->certificate->status !== Certificate::STATUS_ISSUED) {
            return 'Certificate is not in issued status';
        }

        if ($this->certificate->expires_at && $this->certificate->expires_at->isPast()) {
            return 'Certificate has already expired';
        }

        return 'Certificate is not within renewal window';
    }

    /**
     * Handle renewal failure
     */
    private function handleRenewalFailure(string $error): void
    {
        // Update certificate with renewal failure info
        $providerData = $this->certificate->provider_data ?? [];
        $providerData['renewal_failed_at'] = now()->toISOString();
        $providerData['renewal_error'] = $error;
        
        $this->certificate->update([
            'provider_data' => $providerData
        ]);

        // Update subscription statistics
        $this->certificate->subscription->recordCertificateFailure();

        // Dispatch failure event
        CertificateFailed::dispatch(
            $this->certificate, 
            "Renewal failed: {$error}", 
            $this->certificate->provider
        );

        // Schedule retry if appropriate
        $this->scheduleRenewalRetry();
    }

    /**
     * Schedule next renewal for new certificate
     */
    private function scheduleNextRenewal(Certificate $newCertificate): void
    {
        $renewalDays = $newCertificate->subscription->renewal_before_days ?? 30;
        $renewalDate = $newCertificate->expires_at?->subDays($renewalDays);

        if ($renewalDate && $renewalDate->isFuture()) {
            self::dispatch($newCertificate)->delay($renewalDate);

            Log::info('Next renewal scheduled', [
                'certificate_id' => $newCertificate->id,
                'renewal_date' => $renewalDate->toDateTimeString()
            ]);
        }
    }

    /**
     * Schedule renewal retry
     */
    private function scheduleRenewalRetry(): void
    {
        $retryDelay = config('ssl-enhanced.redundancy.retry_delay_seconds', 60) * 60; // Convert to seconds for delay
        $maxRetries = config('ssl-enhanced.redundancy.max_retry_attempts', 3);

        if ($this->attempts() < $maxRetries) {
            Log::info('Scheduling renewal retry', [
                'certificate_id' => $this->certificate->id,
                'attempt' => $this->attempts(),
                'max_attempts' => $maxRetries,
                'retry_in_minutes' => $retryDelay / 60
            ]);

            self::dispatch($this->certificate)->delay(now()->addSeconds($retryDelay));
        } else {
            Log::error('Maximum renewal retry attempts exceeded', [
                'certificate_id' => $this->certificate->id,
                'attempts' => $this->attempts(),
                'max_attempts' => $maxRetries
            ]);

            // Send notification about failed renewal
            $this->sendRenewalFailureNotification();
        }
    }

    /**
     * Send notification about renewal failure
     */
    private function sendRenewalFailureNotification(): void
    {
        // Implementation for sending renewal failure notifications
        // This could be email, Slack, webhook, etc.
        
        Log::critical('Certificate renewal completely failed - manual intervention required', [
            'certificate_id' => $this->certificate->id,
            'domain' => $this->certificate->domain,
            'expires_at' => $this->certificate->expires_at?->toDateTimeString(),
            'user_email' => $this->certificate->subscription->user->email
        ]);

        // Trigger notification job or send immediate notification
        // NotifyRenewalFailure::dispatch($this->certificate);
    }

    /**
     * Calculate next retry delay
     */
    private function getRetryDelay(): int
    {
        $baseDelay = config('ssl-enhanced.redundancy.retry_delay_seconds', 60);
        $attempt = $this->attempts();
        
        // Exponential backoff: 1 hour, 4 hours, 16 hours
        return $baseDelay * pow(4, $attempt - 1);
    }

    /**
     * Get renewal window information
     */
    private function getRenewalWindowInfo(): array
    {
        $renewalDays = $this->certificate->subscription->renewal_before_days ?? 30;
        $renewalDate = $this->certificate->expires_at?->subDays($renewalDays);
        
        return [
            'renewal_days' => $renewalDays,
            'renewal_date' => $renewalDate?->toDateTimeString(),
            'expires_at' => $this->certificate->expires_at?->toDateTimeString(),
            'days_until_expiry' => $this->certificate->getDaysUntilExpiration(),
            'is_in_renewal_window' => $renewalDate && $renewalDate->isPast()
        ];
    }

    /**
     * Job failed handler
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Certificate renewal job failed completely', [
            'certificate_id' => $this->certificate->id,
            'domain' => $this->certificate->domain,
            'provider' => $this->certificate->provider,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        $this->handleRenewalFailure("Job failed: {$exception->getMessage()}");
    }
}