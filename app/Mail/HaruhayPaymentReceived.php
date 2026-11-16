<?php

namespace App\Mail;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class HaruhayPaymentReceived extends Mailable
{
    public function __construct(public array $receipt) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Haruhay Space | Payment received | '.$this->receipt['code']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.haruhay-payment-received');
    }

    public function receiptPdf(): string
    {
        return Pdf::loadView('emails.haruhay-payment-receipt-pdf', ['receipt' => $this->receipt])->setPaper('a4')->output();
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->receiptPdf(), 'Haruhay-Receipt-'.$this->receipt['code'].'.pdf')->withMime('application/pdf')];
    }
}
