<?php

namespace App\Mail;

use App\Models\Shop\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderClientMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Order $order,
        public string $locale = 'uk',
    ) {
        $this->locale = in_array($this->locale, ['uk', 'ru', 'en'], true)
            ? $this->locale
            : 'uk';

        app()->setLocale($this->locale);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        app()->setLocale($this->locale);

        $subjectPrefix = match ($this->locale) {
            'ru' => 'Ваш заказ №',
            'en' => 'Your order #',
            default => 'Ваше замовлення №',
        };

        $subject = $subjectPrefix . ($this->order->number ?? $this->order->id);

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        app()->setLocale($this->locale);

        return new Content(
            markdown: 'emails.order-client',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
