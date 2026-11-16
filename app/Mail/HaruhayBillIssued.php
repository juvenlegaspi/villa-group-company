<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class HaruhayBillIssued extends Mailable
{
    public function __construct(public array $bill) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Haruhay Space | '.$this->bill['code'].' | Monthly bill');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.haruhay-bill-issued');
    }
}
