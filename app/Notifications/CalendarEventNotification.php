<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CalendarEventNotification extends Notification
{
    use Queueable;

    public function __construct(public array $payload, public array $channels = ['database']) {}

    public function via(object $notifiable): array
    {
        return array_values(array_filter($this->channels, fn (string $channel) => $channel !== 'mail' || filled($notifiable->email)));
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->payload['title'] ?? 'Villa Shipping Checklist Reminder')
            ->greeting('Hello '.trim($notifiable->name.' '.$notifiable->lastname).',')
            ->line($this->payload['message'] ?? 'You have a calendar update.')
            ->action('Open Vessel Checklist Calendar', $this->payload['url'] ?? route('shipping.calendar'))
            ->line('Villa Shipping Lines Checklist Reminder System');
    }
}
