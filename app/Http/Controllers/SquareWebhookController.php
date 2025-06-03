<?php

namespace App\Http\Controllers;

use App\Models\{Subscription, Payment, CertificateRenewal, SubscriptionArchive};
use App\Services\SquareWebhookService;
use App\Jobs\{
    SendWelcomeEmail,
    SendPaymentConfirmation,
    NotifyPaymentFailed,
    RevokeCertificate,
    SendCancellationConfirmation,
    ScheduleCertificateRenewal
};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Square Webhook Handler - Laravel 11 Best Practices Implementation
 */
class SquareWebhookController extends Controller
{
    public function __construct(
        private readonly SquareWebhookService $webhookService
    ) {}

    /**
     * Handle Square webhook events
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Square-Hmacsha256-Signature');

        // Verify webhook signature
        if (!$this->webhookService->verifySignature($payload, $signature)) {
            Log::warning('Invalid Square webhook signature');
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $event = json_decode($payload, true);
        
        try {
            match ($event['type']) {
                'subscription.created' => $this->handleSubscriptionCreated($event['data']['object']['subscription']),
                'subscription.updated' => $this->handleSubscriptionUpdated($event['data']['object']['subscription']),
                'invoice.payment_made' => $this->handlePaymentMade($event['data']['object']['invoice']),
                'invoice.payment_failed' => $this->handlePaymentFailed($event['data']['object']['invoice']),
                'subscription.deactivated' => $this->handleSubscriptionDeactivated($event['data']['object']['subscription']),
                'subscription.paused' => $this->handleSubscriptionPaused($event['data']['object']['subscription']),
                'subscription.resumed' => $this->handleSubscriptionResumed($event['data']['object']['subscription']),
                default => Log::info('Unhandled Square webhook event', ['type' => $event['type']])
            };

            return response()->json(['status' => 'success']);

        } catch (\Throwable $e) {
            Log::error('Square webhook processing failed', [
                'event_type' => $event['type'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    private function handleSubscriptionCreated(array $subscriptionData): void
    {
        Log::info('Square subscription created', ['subscription_id' => $subscriptionData['id']]);
        
        // Update local subscription record
        $subscription = Subscription::where('square_subscription_id', $subscriptionData['id'])->first();
        
        if (!$subscription) {
            return;
        }

        $subscription->update([
            'status' => 'active',
            'square_data' => $subscriptionData
        ]);

        // Send welcome email
        SendWelcomeEmail::dispatch($subscription);
    }

    private function handleSubscriptionUpdated(array $subscriptionData): void
    {
        $subscription = Subscription::where('square_subscription_id', $subscriptionData['id'])->first();
        
        if (!$subscription) {
            return;
        }

        $oldStatus = $subscription->status;
        $newStatus = strtolower($subscriptionData['status']);

        $subscription->update([
            'status' => $newStatus,
            'next_billing_date' => $subscriptionData['charged_through'] ?? null,
            'square_data' => $subscriptionData
        ]);

        // Log status change
        Log::info('Subscription status updated', [
            'subscription_id' => $subscription->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus
        ]);

        // Handle status-specific actions
        if ($newStatus !== $oldStatus) {
            $this->handleStatusChange($subscription, $oldStatus, $newStatus);
        }
    }

    private function handlePaymentMade(array $invoiceData): void
    {
        $subscriptionId = $invoiceData['subscription_id'] ?? null;
        
        if (!$subscriptionId) {
            return;
        }

        $subscription = Subscription::where('square_subscription_id', $subscriptionId)->first();
        
        if (!$subscription) {
            return;
        }

        // Update billing date and ensure subscription is active
        $subscription->update([
            'status' => 'active',
            'last_payment_date' => now(),
            'next_billing_date' => $this->calculateNextBillingDate($subscription),
            'payment_failed_attempts' => 0
        ]);

        // Record payment in database
        $this->recordPayment($subscription, $invoiceData);

        // Send payment confirmation
        SendPaymentConfirmation::dispatch($subscription, $invoiceData);

        Log::info('Subscription payment successful', [
            'subscription_id' => $subscription->id,
            'square_subscription_id' => $subscriptionId,
            'amount' => $invoiceData['primary_recipient']['amount']['amount'] ?? 0
        ]);
    }

    private function handlePaymentFailed(array $invoiceData): void
    {
        $subscriptionId = $invoiceData['subscription_id'] ?? null;
        
        if (!$subscriptionId) {
            return;
        }

        $subscription = Subscription::where('square_subscription_id', $subscriptionId)->first();
        
        if (!$subscription) {
            return;
        }

        $failedAttempts = ($subscription->payment_failed_attempts ?? 0) + 1;
        
        $subscription->update([
            'status' => 'past_due',
            'payment_failed_attempts' => $failedAttempts,
            'last_payment_failure' => now()
        ]);

        // Notify user of failed payment
        NotifyPaymentFailed::dispatch($subscription, $failedAttempts);

        // If too many failed attempts, suspend service
        if ($failedAttempts >= 3) {
            $this->suspendSubscriptionServices($subscription);
        }

        Log::warning('Subscription payment failed', [
            'subscription_id' => $subscription->id,
            'square_subscription_id' => $subscriptionId,
            'failed_attempts' => $failedAttempts
        ]);
    }

    private function handleSubscriptionDeactivated(array $subscriptionData): void
    {
        $subscription = Subscription::where('square_subscription_id', $subscriptionData['id'])->first();
        
        if (!$subscription) {
            return;
        }

        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $subscriptionData['cancelled_reason'] ?? 'unknown'
        ]);

        // Revoke all active certificates
        $activeCertificates = $subscription->certificates()
            ->where('status', 'issued')
            ->get();

        $activeCertificates->each(function ($certificate) {
            RevokeCertificate::dispatch($certificate);
        });

        // Send cancellation confirmation
        SendCancellationConfirmation::dispatch($subscription);

        // Archive subscription data
        $this->archiveSubscriptionData($subscription);

        Log::info('Subscription deactivated and services revoked', [
            'subscription_id' => $subscription->id,
            'square_subscription_id' => $subscriptionData['id'],
            'certificates_revoked' => $activeCertificates->count()
        ]);
    }

    private function handleSubscriptionPaused(array $subscriptionData): void
    {
        $subscription = Subscription::where('square_subscription_id', $subscriptionData['id'])->first();
        
        if (!$subscription) {
            return;
        }

        $subscription->update([
            'status' => 'paused',
            'paused_at' => now()
        ]);

        // Pause certificate auto-renewal
        $this->pauseCertificateRenewals($subscription);

        Log::info('Subscription paused', [
            'subscription_id' => $subscription->id,
            'square_subscription_id' => $subscriptionData['id']
        ]);
    }

    private function handleSubscriptionResumed(array $subscriptionData): void
    {
        $subscription = Subscription::where('square_subscription_id', $subscriptionData['id'])->first();
        
        if (!$subscription) {
            return;
        }

        $subscription->update([
            'status' => 'active',
            'paused_at' => null,
            'resumed_at' => now()
        ]);

        // Resume certificate auto-renewal
        $this->resumeCertificateRenewals($subscription);

        Log::info('Subscription resumed', [
            'subscription_id' => $subscription->id,
            'square_subscription_id' => $subscriptionData['id']
        ]);
    }

    private function handleStatusChange(Subscription $subscription, string $oldStatus, string $newStatus): void
    {
        // Handle specific status transitions
        match ($newStatus) {
            'active' => in_array($oldStatus, ['paused', 'past_due']) 
                ? $this->reactivateSubscriptionServices($subscription) 
                : null,
            'past_due' => $this->suspendSubscriptionServices($subscription),
            'cancelled' => $this->terminateSubscriptionServices($subscription),
            default => null
        };
    }

    private function calculateNextBillingDate(Subscription $subscription): Carbon
    {
        return match ($subscription->billing_period) {
            'MONTHLY' => now()->addMonth(),
            'QUARTERLY' => now()->addMonths(3),
            'ANNUALLY' => now()->addYear(),
            default => now()->addMonth()
        };
    }

    private function recordPayment(Subscription $subscription, array $invoiceData): void
    {
        // Record payment in payments table
        Payment::create([
            'subscription_id' => $subscription->id,
            'square_invoice_id' => $invoiceData['id'],
            'amount' => $invoiceData['primary_recipient']['amount']['amount'] ?? 0,
            'currency' => $invoiceData['primary_recipient']['amount']['currency'] ?? 'USD',
            'status' => 'completed',
            'paid_at' => now(),
            'invoice_data' => $invoiceData
        ]);
    }

    private function suspendSubscriptionServices(Subscription $subscription): void
    {
        // Suspend certificate issuance (but don't revoke existing ones)
        $subscription->certificates()
            ->where('status', 'pending_validation')
            ->update(['status' => 'suspended']);

        // Cancel any pending renewal jobs
        $this->cancelPendingRenewals($subscription);
    }

    private function reactivateSubscriptionServices(Subscription $subscription): void
    {
        // Reactivate suspended certificates
        $subscription->certificates()
            ->where('status', 'suspended')
            ->update(['status' => 'pending_validation']);

        // Resume auto-renewal for active certificates
        $this->resumeCertificateRenewals($subscription);
    }

    private function terminateSubscriptionServices(Subscription $subscription): void
    {
        // Mark all certificates as terminated
        $subscription->certificates()->update(['status' => 'terminated']);
        
        // Cancel all renewal jobs
        $this->cancelPendingRenewals($subscription);
    }

    private function pauseCertificateRenewals(Subscription $subscription): void
    {
        // Implementation for pausing auto-renewals
        $activeCertificates = $subscription->certificates()
            ->where('status', 'issued')
            ->get();

        $activeCertificates->each(function ($certificate) {
            // Remove from renewal queue but keep certificate active
            CertificateRenewal::where('certificate_id', $certificate->id)
                ->where('status', 'pending')
                ->update(['status' => 'paused']);
        });
    }

    private function resumeCertificateRenewals(Subscription $subscription): void
    {
        // Resume paused renewals
        $certificates = $subscription->certificates()
            ->where('status', 'issued')
            ->get();

        $certificates->each(function ($certificate) {
            // Re-schedule renewal if certificate is expiring soon
            if ($certificate->isExpiringSoon()) {
                ScheduleCertificateRenewal::dispatch($certificate);
            }
        });
    }

    private function cancelPendingRenewals(Subscription $subscription): void
    {
        CertificateRenewal::whereIn('certificate_id', 
            $subscription->certificates()->pluck('id')
        )->where('status', 'pending')
          ->update(['status' => 'cancelled']);
    }

    private function archiveSubscriptionData(Subscription $subscription): void
    {
        // Archive subscription data for compliance/auditing
        SubscriptionArchive::create([
            'original_subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'subscription_data' => $subscription->toArray(),
            'certificates_data' => $subscription->certificates()->get()->toArray(),
            'archived_at' => now()
        ]);
    }
}