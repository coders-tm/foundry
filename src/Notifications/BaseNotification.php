<?php

namespace Foundry\Notifications;

use Foundry\Mail\NotificationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * @phpstan-consistent-constructor
 */
class BaseNotification extends Notification
{
    use Queueable;

    public $subject;

    public $message;

    public $fromAddress;

    public $fromName;

    public $attachments = [];

    /**
     * Create a new notification instance.
     *
     * @param  string  $subject
     * @param  string  $message
     * @return void
     */
    public function __construct($subject, $message)
    {
        $this->subject = $subject;
        $this->message = $message;
    }

    /**
     * Create notification from database template
     */
    public static function fromTemplate(string $templateType, array $data = []): static
    {
        $template = \Foundry\Models\Notification::default($templateType);
        $rendered = $template->render($data);

        return new static($rendered['subject'], $rendered['content']);
    }

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
    public function toMail(object $notifiable): NotificationMail
    {
        return new NotificationMail(
            emailSubject: $this->subject,
            htmlContent: (string) $this->message,
            notifiable: $notifiable,
            fromAddress: $this->fromAddress,
            fromName: $this->fromName ?? config('app.name'),
            mailAttachments: $this->attachments
        );
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
