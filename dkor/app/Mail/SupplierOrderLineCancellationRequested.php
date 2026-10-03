<?php

namespace App\Mail;

use App\Models\SupplierOrderLine;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupplierOrderLineCancellationRequested extends Mailable
{
    use SerializesModels;

    public function __construct(public SupplierOrderLine $line) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Demande d\'annulation — commande :number', ['number' => $this->line->order->number]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.supplier-order-line-cancellation-requested');
    }
}
