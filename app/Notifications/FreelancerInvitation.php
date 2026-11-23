<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FreelancerInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token, public int $validForDays = 7) {}

    /**
     * Audit-log metadata that exposes the activation link so accounts can be activated while
     * testing without reading the mail log.
     *
     * The invitation token is a bearer credential that is normally stored only as a hash, so
     * the link is recorded in the `local` environment only and never in production.
     *
     * @return array<string, string>
     */
    public static function localTestingMetadata(string $token): array
    {
        return app()->environment('local')
            ? ['activation_url' => route('freelancer.activate', $token)]
            : [];
    }

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
            ->subject('Your application was approved — activate your account')
            ->greeting('Welcome to '.config('app.name').'!')
            ->line('Your freelancer application has been approved. Activate your account to complete your profile.')
            ->action('Activate account', route('freelancer.activate', $this->token))
            ->line("This link is valid for {$this->validForDays} days and can only be used once.");
    }
}
