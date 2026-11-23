<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProfileChangesRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $comment) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Changes requested on your profile')
            ->line('An administrator reviewed your profile and requested changes:')
            ->line($this->comment)
            ->action('Edit your profile', route('freelancer.profile'));
    }
}
