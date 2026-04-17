<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Vessel;
use App\Models\VesselCertificate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CertificateExpiryNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public VesselCertificate $certificate,
        public Vessel $vessel,
        public User $recipient
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Certificate Expiring Soon',
        );
    }

    public function content(): Content
    {
        $fullName = trim(collect([$this->recipient->name, $this->recipient->lastname])->filter()->implode(' '));

        return new Content(
            view: 'emails.certificate-expiry-notification',
            with: [
                'recipientName' => $fullName,
                'vesselName' => $this->vessel->vessel_name,
                'certificateName' => $this->certificate->certificate_name,
                'expiryDate' => optional($this->certificate->expiry_date)->format('F d, Y'),
                'daysRemaining' => now()->diffInDays($this->certificate->expiry_date, false),
                'remarks' => $this->certificate->remarks,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
