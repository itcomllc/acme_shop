<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\SlackMessage;

class SSLSystemAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $alertType,
        private string $message,
        private array $details = [],
        private string $severity = 'warning'
    ) {}

    public function via($notifiable): array
    {
        return ['slack'];
    }

    public function toSlack($notifiable): SlackMessage
    {
        $icon = match($this->severity) {
            'critical' => '🚨',
            'error' => '❌',
            'warning' => '⚠️',
            'success' => '✅',
            default => 'ℹ️'
        };

        $color = match($this->severity) {
            'critical' => 'danger',
            'error' => 'danger', 
            'warning' => 'warning',
            'success' => 'good',
            default => '#439FE0'
        };

        return (new SlackMessage)
            ->to(config('services.slack.notifications.ssl_alerts_channel'))
            ->content("{$icon} **SSL System Alert**")
            ->attachment(function ($attachment) use ($color) {
                $attachment->title($this->alertType)
                          ->content($this->message)
                          ->color($color)
                          ->fields($this->formatDetails());
            });
    }

    private function formatDetails(): array
    {
        $fields = [];
        foreach ($this->details as $key => $value) {
            $fieldName = ucfirst(str_replace('_', ' ', $key));
            $fieldValue = is_array($value) ? implode(', ', $value) : (string) $value;
            $fields[$fieldName] = $fieldValue;
        }
        
        $fields['Time'] = now()->format('Y-m-d H:i:s T');
        return $fields;
    }
}
