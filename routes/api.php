<?php

use App\Http\Controllers\HaruhayPublicRoomController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/haruhay/rooms', HaruhayPublicRoomController::class)
    ->middleware('throttle:60,1')
    ->name('public.haruhay.rooms');

Route::prefix('v1/haruhay/reservations')->middleware('throttle:60,1')->name('public.haruhay.reservations.')->group(function (): void {
    Route::post('/', [\App\Http\Controllers\HaruhayPublicReservationController::class, 'store'])->name('store');
    Route::get('/{reference}', [\App\Http\Controllers\HaruhayPublicReservationController::class, 'show'])->name('show');
    Route::post('/{reference}/proof', [\App\Http\Controllers\HaruhayPublicReservationController::class, 'proof'])->name('proof');
    Route::post('/{reference}/cancel', [\App\Http\Controllers\HaruhayPublicReservationController::class, 'cancel'])->name('cancel');
});
