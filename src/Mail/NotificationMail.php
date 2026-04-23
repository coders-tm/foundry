<?php

namespace Foundry\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Queue\SerializesModels;

class NotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $emailSubject,
        public string $htmlContent,
        public object $notifiable,
        public ?string $fromAddress = null,
        public ?string $fromName = null,
        public $mailAttachments = []
    ) {
        // Set recipients from notifiable
        $this->setRecipients($notifiable);
    }

    /**
     * Set recipients from notifiable
     */
    protected function setRecipients(object $notifiable): void
    {
        if ($notifiable instanceof AnonymousNotifiable) {
            $address = $notifiable->routeNotificationFor('mail');

            // Handle array format [email => name] from Notification::route()
            if (is_array($address)) {
                foreach ($address as $email => $name) {
                    if (! empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $this->to($email, $name);
                    }
                }
            } elseif ($address) {
                $this->to($address);
            }
        } else {
            $address = $notifiable->email ?? null;
            if ($address) {
                $this->to($address);
            }
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $envelope = new Envelope(
            subject: $this->emailSubject
        );

        // Set custom from address if provided
        if ($this->fromAddress) {
            $envelope->from($this->fromAddress, $this->fromName ?? config('app.name'));
        }

        return $envelope;
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtmlEmail()
        );
    }

    /**
     * Build the HTML email using Blade template
     */
    protected function buildHtmlEmail(): string
    {
        return view('emails.notification', [
            'htmlContent' => $this->htmlContent,
        ])->render();
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return $this->mailAttachments;
    }
}
