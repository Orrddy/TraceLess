<?php

namespace App\Notifications;

use App\Models\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlertNotification extends Notification
{
    use Queueable;

    public function __construct(public Alert $alert) {}

    public function via(object $notifiable): array
    {
        // Email is the only channel that reaches a user who is not in the app.
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'alert',
            'alert_id' => $this->alert->id,
            'title' => $this->alert->title,
            'message' => $this->alert->message,
            'url' => $this->alert->action_url ?: route('dashboard'),
            'severity' => $this->alert->severity,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->alert->title)
            ->line($this->alert->message)
            ->action('Open TraceLess', $this->alert->action_url ?: route('dashboard'));
    }
}
