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
        $days = today()->diffInDays($this->certificate->expiry_date, false);
        return new Envelope(
            subject: $days < 0 ? 'Overdue Vessel Certificate' : 'Vessel Certificate Expiry Reminder',
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
                'daysRemaining' => today()->diffInDays($this->certificate->expiry_date, false),
                'isOverdue' => today()->isAfter($this->certificate->expiry_date),
                'remarks' => $this->certificate->remarks,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
