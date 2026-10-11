<?php

namespace App\Mail;

use App\Models\Reception;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Avis au fournisseur des erreurs d'expédition d'une réception : articles manquants (demande de crédit) et articles
 * reçus en trop.
 */
class ReceptionDiscrepancyReported extends Mailable
{
    use SerializesModels;

    public function __construct(public Reception $reception) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Erreur d\'expédition — réception :number', ['number' => $this->reception->number]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.reception-discrepancy-reported', with: [
            'shortLines' => $this->reception->shortLines(),
            'overshipLines' => $this->reception->overshipLines(),
        ]);
    }
}
