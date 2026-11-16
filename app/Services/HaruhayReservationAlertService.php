<?php

namespace App\Services;

use App\Models\HaruhayReservation;
use App\Models\User;
use App\Notifications\HaruhayReservationRequested;

class HaruhayReservationAlertService
{
    public function notify(HaruhayReservation $reservation): void
    {
        User::where('status', true)->with(['division', 'accessRole'])->chunkById(100, function ($users) use ($reservation) {
            foreach ($users as $user) {
                if ($user->canManageAllCompanies() || $user->isExecutiveViewer() || strcasecmp((string) $user->division?->name, 'Haruhay') === 0) {
                    $user->notify(new HaruhayReservationRequested($reservation));
                }
            }
        });
    }
}
