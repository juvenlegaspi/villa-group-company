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
                    if (! app(HaruhayPropertyAccess::class)->canView($user, (int) $reservation->unit->property_id)) {
                        continue;
                    }
                    $user->notify(new HaruhayReservationRequested($reservation));
                }
            }
        });
    }
}
