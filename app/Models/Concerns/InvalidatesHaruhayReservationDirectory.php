<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Cache;

trait InvalidatesHaruhayReservationDirectory
{
    public static function bootInvalidatesHaruhayReservationDirectory(): void
    {
        static::saved(function (): void {
            Cache::forget('haruhay.reservations.directory-revision');
        });
        static::deleted(function (): void {
            Cache::forget('haruhay.reservations.directory-revision');
        });
    }
}
