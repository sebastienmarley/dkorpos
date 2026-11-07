<?php

namespace App\Mail;

use App\Models\CustomerOrder;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Commande envoyée au client : même gabarit que la commande imprimée.
 */
class CustomerOrderSummary extends Mailable
{
    use SerializesModels;

    public function __construct(public CustomerOrder $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: filled($this->order->store?->email) ? [new Address($this->order->store->email, $this->order->store->name)] : [],
            subject: __('Votre commande #:id', ['id' => $this->order->id]).($this->order->store ? ' — '.$this->order->store->name : ''),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'documents.customer-order');
    }
}
