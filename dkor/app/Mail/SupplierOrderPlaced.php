<?php

namespace App\Mail;

use App\Models\SupplierOrder;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupplierOrderPlaced extends Mailable
{
    use SerializesModels;

    public function __construct(public SupplierOrder $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Commande :number', ['number' => $this->order->number]).($this->order->quote_number ? ' — '.__('Quote #').' '.$this->order->quote_number : ''),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.supplier-order-placed');
    }
}
