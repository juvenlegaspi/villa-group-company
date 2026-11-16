<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Cache;

trait InvalidatesHaruhayReservationDirectory
{
    public static function bootInvalidatesHaruhayReservationDirectory(): void
    {
        static::saved(fn () => Cache::forget('haruhay.reservations.directory-revision'));
        static::deleted(fn () => Cache::forget('haruhay.reservations.directory-revision'));
    }
}
