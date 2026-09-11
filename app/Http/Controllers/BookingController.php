<?php

namespace App\Http\Controllers;

use App\Models\BookingHeader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $selectedDate = $request->string('date')->toString() ?: Carbon::today()->toDateString();
        $selectedStatus = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());

        $bookingsQuery = BookingHeader::query()
            ->with(['details.hyveRoom', 'user'])
            ->whereHas('details', function ($query) use ($selectedDate): void {
                $query->whereDate('booking_date', $selectedDate);
            })
            ->latest('created_at');

        if ($selectedStatus !== '') {
            $bookingsQuery->where('status', $selectedStatus);
        }

        if ($search !== '') {
            $bookingsQuery->where(function ($query) use ($search): void {
                $query
                    ->where('reference_no', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $bookings = $bookingsQuery->get();

        $bookingRows = $bookings
            ->flatMap(function (BookingHeader $header): Collection {
                return $header->details->map(function ($detail) use ($header): array {
                    $proofUrl = $this->resolveProofUrl($header);

                    return [
                        'header_id' => $header->id,
                        'reference_no' => $header->reference_no,
                        'customer_name' => $header->customer_name,
                        'email' => $header->email,
                        'phone' => $header->phone,
                        'booking_type' => $header->booking_type ?: 'guest',
                        'status' => $header->status ?: 'pending',
                        'payment_status' => $header->payment_status ?: 'pending',
                        'payment_method' => $header->payment_method ?: 'N/A',
                        'room_name' => $detail->hyveRoom?->room_name ?? 'Unassigned room',
                        'room_description' => $detail->hyveRoom?->description ?? 'No room description available.',
                        'booking_date' => optional($detail->booking_date)?->format('M d, Y') ?? 'No date',
                        'time_range' => $this->formatTimeRange($detail->start_time, $detail->end_time),
                        'guests' => (int) $detail->guests,
                        'total_amount' => (float) $header->total_amount,
                        'downpayment_amount' => (float) $header->downpayment_amount,
                        'balance_amount' => (float) $header->balance_amount,
                        'notes' => $header->notes,
                        'proof_name' => $header->payment_proof_name,
                        'proof_url' => $proofUrl,
                        'can_approve' => ($header->status ?: 'pending') !== 'confirmed',
                    ];
                });
            })
            ->values();

        $perPage = 10;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $paginatedRows = new LengthAwarePaginator(
            $bookingRows->forPage($currentPage, $perPage)->values(),
            $bookingRows->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return view('hyve.booking.index', [
            'selectedDate' => $selectedDate,
            'selectedStatus' => $selectedStatus,
            'search' => $search,
            'bookingRows' => $paginatedRows,
        ]);
    }

    public function approve(BookingHeader $bookingHeader): RedirectResponse
    {
        DB::transaction(function () use ($bookingHeader): void {
            $lockedBooking = BookingHeader::whereKey($bookingHeader->id)->lockForUpdate()->firstOrFail();
            $details = $lockedBooking->details()->lockForUpdate()->get();

            abort_if($lockedBooking->status === 'confirmed', 422, 'This booking is already confirmed.');
            abort_if($details->isEmpty(), 422, 'A booking without details cannot be approved.');

            foreach ($details as $detail) {
                $hasConflict = \App\Models\BookingDetail::query()
                    ->where('booking_header_id', '!=', $lockedBooking->id)
                    ->where('hyve_room_id', $detail->hyve_room_id)
                    ->whereDate('booking_date', $detail->booking_date)
                    ->where('status', 'confirmed')
                    ->where('start_time', '<', $detail->end_time)
                    ->where('end_time', '>', $detail->start_time)
                    ->lockForUpdate()
                    ->exists();

                if ($hasConflict) {
                    throw ValidationException::withMessages([
                        'booking' => 'This room now conflicts with another confirmed booking.',
                    ]);
                }
            }

            $lockedBooking->update([
                'status' => 'confirmed',
                'payment_status' => in_array($lockedBooking->payment_status, [null, '', 'pending', 'pending_verification'], true)
                    ? 'verified'
                    : $lockedBooking->payment_status,
            ]);
            $lockedBooking->details()->update(['status' => 'confirmed']);
        });

        return back()->with('booking_success', 'Booking '.$bookingHeader->reference_no.' has been approved successfully.');
    }

    public function proof(BookingHeader $bookingHeader): BinaryFileResponse
    {
        abort_unless($bookingHeader->payment_proof_path, 404);

        $filePath = $this->resolveLocalProofPath($bookingHeader->payment_proof_path);
        abort_unless($filePath && is_file($filePath), 404);

        return response()->file($filePath);
    }

    private function formatTimeRange(?string $startTime, ?string $endTime): string
    {
        if (! $startTime || ! $endTime) {
            return 'Time pending';
        }

        return Carbon::createFromFormat('H:i:s', $startTime)->format('g:i A')
            .' - '.
            Carbon::createFromFormat('H:i:s', $endTime)->format('g:i A');
    }

    private function resolveProofUrl(BookingHeader $bookingHeader): ?string
    {
        $path = $bookingHeader->payment_proof_path;

        if (! $path) {
            return null;
        }

        $trimmedPath = ltrim($path, '/');

        return route('hyve.projects.proof', $bookingHeader);
    }

    private function resolveLocalProofPath(string $path): ?string
    {
        if (str_starts_with(strtolower($path), 'http://') || str_starts_with(strtolower($path), 'https://')) {
            return null;
        }

        $trimmedPath = str_replace(['..\\', '../'], '', ltrim($path, '\\/'));
        $hyveStorageRoot = realpath(base_path('..\\hyve\\storage\\app\\public'));

        if (! $hyveStorageRoot) {
            return null;
        }

        $fullPath = realpath($hyveStorageRoot.DIRECTORY_SEPARATOR.$trimmedPath);

        if (! $fullPath) {
            $fullPath = $hyveStorageRoot.DIRECTORY_SEPARATOR.$trimmedPath;
        }

        $normalizedRoot = str_replace('\\', '/', $hyveStorageRoot);
        $normalizedPath = str_replace('\\', '/', $fullPath);

        if ($normalizedPath !== $normalizedRoot && ! str_starts_with($normalizedPath, $normalizedRoot.'/')) {
            return null;
        }

        return $fullPath;
    }
}
