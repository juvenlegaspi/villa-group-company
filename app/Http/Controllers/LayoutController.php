<?php

namespace App\Http\Controllers;

use App\Models\BookingDetail;
use App\Models\HyveRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LayoutController extends Controller
{
    private const LEFT_ROOMS = [
        ['name' => 'Conference Room', 'size' => 'room-large'],
        ['name' => 'Room 6', 'size' => 'room-medium'],
        ['name' => 'Room 5', 'size' => 'room-medium'],
        ['name' => 'Room 4', 'size' => 'room-medium'],
        ['name' => 'Room 3', 'size' => 'room-medium'],
        ['name' => 'Room 2', 'size' => 'room-medium'],
        ['name' => 'Room 1', 'size' => 'room-medium'],
    ];

    private const RIGHT_ROOMS = [
        ['name' => 'Room 7', 'size' => 'room-large'],
    ];

    private const TABLES = [
        ['name' => 'Table 8', 'rows' => [['A', 'C'], ['B', 'D']]],
        ['name' => 'Table 7', 'rows' => [['A', 'C'], ['B', 'D']]],
        ['name' => 'Table 6', 'rows' => [['A', 'C'], ['B', 'D']]],
        ['name' => 'Table 5', 'rows' => [['A', 'D', 'B', 'E', 'C', 'F']]],
        ['name' => 'Table 3', 'rows' => [['A', 'C', 'E'], ['B', 'D', 'F']]],
        ['name' => 'Table 2', 'rows' => [['A', 'C', 'E'], ['B', 'D', 'F']]],
        ['name' => 'Table 4', 'rows' => [['A'], ['B'], ['C']], 'stacked' => true],
        ['name' => 'Table 1', 'rows' => [['A', 'C'], ['B', 'D']]],
    ];

    public function index(Request $request)
    {
        $selectedDate = $request->string('date')->toString() ?: Carbon::today()->toDateString();

        $rooms = HyveRoom::query()
            ->visible()
            ->orderBy('room_name')
            ->get()
            ->keyBy('room_name');

        $allRooms = $rooms->map(fn (HyveRoom $room): array => $this->roomPayload($room, $selectedDate));
        $roomStatusSummary = $allRooms->map(fn (array $room): string => $room['status']);

        return view('hyve.layout.index', [
            'selectedDate' => $selectedDate,
            'legend' => HyveRoom::legend(),
            'leftRooms' => $this->buildRooms(self::LEFT_ROOMS, $allRooms),
            'rightRooms' => $this->buildRooms(self::RIGHT_ROOMS, $allRooms),
            'tables' => $this->buildTables($allRooms),
            'summary' => [
                'available' => $roomStatusSummary->filter(fn (string $status): bool => $status === 'available')->count(),
                'occupied' => $roomStatusSummary->filter(fn (string $status): bool => $status === 'occupied')->count(),
                'booked' => $roomStatusSummary->filter(fn (string $status): bool => $status === 'booked')->count(),
                'total' => $roomStatusSummary->count(),
            ],
        ]);
    }

    private function buildRooms(array $definitions, Collection $rooms): array
    {
        return array_map(
            fn (array $definition) => $rooms->get($definition['name'])
                ?? $this->fallbackPayload($definition['name'], $definition['size']),
            $definitions
        );
    }

    private function buildTables(Collection $rooms): array
    {
        return array_map(function (array $table) use ($rooms) {
            return [
                'name' => $table['name'],
                'stacked' => $table['stacked'] ?? false,
                'rows' => array_map(function (array $row) use ($rooms, $table) {
                    return array_map(
                        fn (string $seat) => $rooms->get($table['name'].'-'.$seat)
                            ?? $this->fallbackPayload($table['name'].'-'.$seat, '', $seat),
                        $row
                    );
                }, $table['rows']),
            ];
        }, self::TABLES);
    }

    private function roomPayload(HyveRoom $room, string $selectedDate): array
    {
        $snapshot = $this->bookingSnapshotForRoom($room, $selectedDate);
        $statusMap = [
            'available' => ['label' => 'Available', 'class' => 'available'],
            'booked' => ['label' => 'Booked', 'class' => 'reserved'],
            'occupied' => ['label' => 'Occupied', 'class' => 'occupied'],
        ];
        $statusMeta = $statusMap[$snapshot['status']] ?? $statusMap['available'];

        return [
            'id' => $room->id,
            'name' => $room->room_name,
            'label' => $room->room_name,
            'status' => $snapshot['status'],
            'status_label' => $statusMeta['label'],
            'status_class' => $statusMeta['class'],
            'size_class' => '',
            'description' => $room->description,
            'space_label' => $this->spaceLabel($room),
            'available_slots' => $snapshot['available_slots']->values()->all(),
            'booked_slots' => $snapshot['booked_slots']->values()->all(),
            'booking_details' => $this->bookingDetailsForLayout($snapshot),
        ];
    }

    private function fallbackPayload(string $name, string $sizeClass = '', ?string $label = null): array
    {
        return [
            'id' => null,
            'name' => $name,
            'label' => $label ?? $name,
            'status' => 'available',
            'status_label' => 'Available',
            'status_class' => 'available',
            'size_class' => $sizeClass,
            'description' => 'No room description available.',
            'space_label' => $this->spaceLabelByName($name),
            'available_slots' => [],
            'booked_slots' => [],
            'booking_details' => [],
        ];
    }

    private function bookingSnapshotForRoom(HyveRoom $room, string $bookingDate): array
    {
        $dayStart = $this->slotBoundary($bookingDate, '00:00');
        $dayEnd = $this->slotBoundary($bookingDate, '24:00');
        $minimumDuration = 60;
        $effectiveStart = $this->effectiveDayStart($bookingDate, $dayStart);
        $blockedRanges = $this->blockedRangesForRoom($room, $bookingDate);
        $startTimes = $this->availableStartTimesForRoom($blockedRanges, $effectiveStart, $dayEnd, $minimumDuration);
        $availableWindows = $this->availableWindowsForLayout($blockedRanges, $effectiveStart, $dayEnd, $minimumDuration);
        $bookedWindows = $blockedRanges->map(fn (array $range): array => $this->windowPayload($range['start'], $range['end']));

        $status = 'available';

        if ($blockedRanges->isNotEmpty() && $startTimes->isNotEmpty()) {
            $status = 'booked';
        }

        if ($startTimes->isEmpty()) {
            $status = 'occupied';
        }

        return [
            'start_times' => $startTimes,
            'available_slots' => $availableWindows,
            'booked_slots' => $bookedWindows,
            'status' => $status,
        ];
    }

    private function effectiveDayStart(string $bookingDate, Carbon $dayStart): Carbon
    {
        if ($bookingDate !== Carbon::today()->toDateString()) {
            return $dayStart->copy();
        }

        $roundedNow = $this->roundUpToNextHalfHour(Carbon::now());

        return $roundedNow->gt($dayStart) ? $roundedNow : $dayStart->copy();
    }

    private function roundUpToNextHalfHour(Carbon $dateTime): Carbon
    {
        $rounded = $dateTime->copy()->second(0);

        if ($rounded->minute % 30 === 0) {
            return $rounded;
        }

        return $rounded->addMinutes(30 - ($rounded->minute % 30))->second(0);
    }

    private function blockedStatuses(): array
    {
        return ['pending', 'confirmed'];
    }

    private function blockedRangesForRoom(HyveRoom $room, string $bookingDate): Collection
    {
        $ranges = BookingDetail::query()
            ->whereDate('booking_date', $bookingDate)
            ->whereIn('status', $this->blockedStatuses())
            ->where('hyve_room_id', $room->id)
            ->get(['start_time', 'end_time'])
            ->map(function (BookingDetail $detail) use ($bookingDate): array {
                [$start, $end] = $this->dateRange($bookingDate, $detail->start_time, $detail->end_time);

                return [
                    'start' => $start,
                    'end' => $end,
                ];
            })
            ->sortBy(fn (array $range): int => $range['start']->timestamp)
            ->values();

        return $this->mergeRanges($ranges);
    }

    private function mergeRanges(Collection $ranges): Collection
    {
        return $ranges->reduce(function (Collection $carry, array $range): Collection {
            if ($carry->isEmpty()) {
                $carry->push([
                    'start' => $range['start']->copy(),
                    'end' => $range['end']->copy(),
                ]);

                return $carry;
            }

            $lastIndex = $carry->keys()->last();
            $lastRange = $carry->get($lastIndex);

            if ($range['start']->lte($lastRange['end'])) {
                if ($range['end']->gt($lastRange['end'])) {
                    $lastRange['end'] = $range['end']->copy();
                    $carry->put($lastIndex, $lastRange);
                }

                return $carry;
            }

            $carry->push([
                'start' => $range['start']->copy(),
                'end' => $range['end']->copy(),
            ]);

            return $carry;
        }, collect());
    }

    private function availableStartTimesForRoom(Collection $blockedRanges, Carbon $effectiveStart, Carbon $dayEnd, int $minimumDuration): Collection
    {
        $slotIntervalMinutes = 30;
        $startTimes = collect();
        $cursor = $effectiveStart->copy();

        while ($cursor->copy()->addMinutes($minimumDuration)->lte($dayEnd)) {
            $availableUntil = $this->availableUntil($blockedRanges, $cursor, $dayEnd);

            if ($availableUntil && $cursor->diffInMinutes($availableUntil, true) >= $minimumDuration) {
                $startTimes->push([
                    'value' => $cursor->format('H:i'),
                    'label' => $cursor->format('g:i A'),
                ]);
            }

            $cursor->addMinutes($slotIntervalMinutes);
        }

        return $startTimes;
    }

    private function availableWindowsForLayout(Collection $blockedRanges, Carbon $effectiveStart, Carbon $dayEnd, int $minimumDuration): Collection
    {
        $windows = collect();
        $cursor = $effectiveStart->copy();

        foreach ($blockedRanges as $range) {
            if ($range['end']->lte($cursor)) {
                continue;
            }

            $windowEnd = $range['start']->copy();

            if ($cursor->diffInMinutes($windowEnd, true) >= $minimumDuration) {
                $windows->push($this->windowPayload($cursor, $windowEnd));
            }

            if ($range['end']->gt($cursor)) {
                $cursor = $range['end']->copy();
            }
        }

        if ($cursor->diffInMinutes($dayEnd, true) >= $minimumDuration) {
            $windows->push($this->windowPayload($cursor, $dayEnd));
        }

        return $windows;
    }

    private function availableUntil(Collection $blockedRanges, Carbon $start, Carbon $dayEnd): ?Carbon
    {
        foreach ($blockedRanges as $range) {
            if ($start->gte($range['start']) && $start->lt($range['end'])) {
                return null;
            }

            if ($range['start']->gt($start)) {
                return $range['start']->copy();
            }
        }

        return $dayEnd->copy();
    }

    private function dateRange(string $bookingDate, string $startTime, string $endTime): array
    {
        $start = Carbon::parse($bookingDate.' '.$startTime);
        $end = Carbon::parse($bookingDate.' '.$endTime);

        if ($end->lte($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    private function slotBoundary(string $bookingDate, string $time): Carbon
    {
        if ($time === '24:00') {
            return Carbon::createFromFormat('Y-m-d H:i', $bookingDate.' 00:00')->addDay();
        }

        return Carbon::createFromFormat('Y-m-d H:i', $bookingDate.' '.$time);
    }

    private function windowPayload(Carbon $start, Carbon $end): array
    {
        return [
            'value' => $start->format('H:i'),
            'label' => $start->format('g:i A').' - '.$this->displayTimeLabel($end, $start),
            'end_time' => $end->format('H:i'),
        ];
    }

    private function displayTimeLabel(Carbon $time, Carbon $start): string
    {
        $label = $time->format('g:i A');

        if ($time->isSameDay($start)) {
            return $label;
        }

        return $label.' next day';
    }

    private function bookingDetailsForLayout(array $snapshot): array
    {
        return $snapshot['available_slots']
            ->map(fn (array $slot): array => [
                'label' => $slot['label'],
                'type' => 'available',
            ])
            ->concat(
                $snapshot['booked_slots']->map(fn (array $slot): array => [
                    'label' => $slot['label'],
                    'type' => 'booked',
                ])
            )
            ->values()
            ->all();
    }

    private function spaceLabel(HyveRoom $room): string
    {
        return $this->spaceLabelByName($room->room_name);
    }

    private function spaceLabelByName(string $roomName): string
    {
        if ($roomName === 'Conference Room') {
            return 'Zeal Room (8 Seats)';
        }

        if ($roomName === 'Room 7') {
            return 'Tenacity Office (4 Seats)';
        }

        if (str_starts_with($roomName, 'Room ')) {
            return 'Fortitude Office (2 Seats)';
        }

        return 'Common Area';
    }
}
