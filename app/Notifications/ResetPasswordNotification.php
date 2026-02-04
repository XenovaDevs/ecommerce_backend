<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * @ai-context ResetPasswordNotification sends password reset email to users.
 *             Implements ShouldQueue for better performance (asynchronous sending).
 *
 * @ai-security Token is passed as plain text only in email (never stored plain).
 *              Link includes both token and email for validation.
 */
class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param string $token Plain password reset token
     * @param string $email User's email address
     */
    public function __construct(
        private readonly string $token,
        private readonly string $email
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        $resetUrl = sprintf(
            '%s/reset-password?token=%s&email=%s',
            $frontendUrl,
            $this->token,
            urlencode($this->email)
        );

        return (new MailMessage)
            ->subject('Reset Your Password')
            ->greeting('Hello!')
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->action('Reset Password', $resetUrl)
            ->line('This password reset link will expire in 60 minutes.')
            ->line('If you did not request a password reset, no further action is required.')
            ->salutation('Regards, ' . config('app.name'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'email' => $this->email,
            'sent_at' => now()->toDateTimeString(),
        ];
    }
}
