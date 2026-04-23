<?php

namespace Workbench\App\Notifications;

use Foundry\Mail\NotificationMail;
use Foundry\Models\Notification as Template;
use Foundry\Models\Order;
use Foundry\Notifications\BaseNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;

class SendOrderInvoice extends BaseNotification
{
    use Queueable;

    public $order;

    public $subject;

    public $message;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
        $template = Template::default('user:invoice-sent');

        // Render using Blade template renderer
        $rendered = $template->render([
            'order' => $this->order->getShortCodes(),
        ]);

        parent::__construct($rendered['subject'], $rendered['content']);
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     */
    public function toMail(object $notifiable): NotificationMail
    {
        $mail = parent::toMail($notifiable);

        // Get the PDF as a string instead of a response object
        try {
            $pdf = $this->order->receiptPdf();
            $pdfContent = $pdf->output();

            // Attach the order receipt as PDF
            $mail->attachData(
                $pdfContent,
                "receipt-{$this->order->id}.pdf",
                ['mime' => 'application/pdf']
            );
        } catch (\Throwable $e) {
            // Log the error but don't break the email
            Log::error('Failed to attach receipt PDF: '.$e->getMessage());
        }

        return $mail;
    }

    /**
     * Array representation for database channel or test inspection.
     */
    public function toArray(object $notifiable): array
    {
        return $this->order->getShortCodes();
    }
}
