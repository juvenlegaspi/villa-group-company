<?php

namespace App\Notifications;

use App\Models\HaruhayReservation;
use Illuminate\Notifications\Notification;

class HaruhayReservationRequested extends Notification
{
    public function __construct(public HaruhayReservation $reservation) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['module' => 'haruhay_reservations', 'title' => 'New Haruhay reservation', 'message' => $this->reservation->name.' requested '.$this->reservation->unit->unit_no.'.', 'reservation_id' => $this->reservation->id, 'reference' => $this->reservation->reference, 'applicant' => $this->reservation->name, 'room' => $this->reservation->unit->unit_no, 'url' => route('haruhay.reservations.show', $this->reservation, false)];
    }
}
