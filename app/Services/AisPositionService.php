<?php

namespace App\Services;

use App\Models\Vessel;
use App\Models\VesselPositionLog;
use App\Models\VoyageLogHeader;
use Illuminate\Support\Facades\DB;

class AisPositionService
{
    private const POSITION_MESSAGE_TYPES = [
        'PositionReport',
        'StandardClassBPositionReport',
        'ExtendedClassBPositionReport',
    ];

    /**
     * @return array{status:string, reason?:string, vessel?:string, voyage_id?:int, mmsi?:string}
     */
    public function ingest(array $payload): array
    {
        $messageType = (string) data_get($payload, 'MessageType', '');
        if (! in_array($messageType, self::POSITION_MESSAGE_TYPES, true)) {
            return ['status' => 'ignored', 'reason' => 'not_a_position_report'];
        }

        $message = data_get($payload, "Message.{$messageType}", []);
        if (! is_array($message)) {
            return ['status' => 'ignored', 'reason' => 'invalid_message'];
        }

        if (array_key_exists('Valid', $message) && ! (bool) $message['Valid']) {
            return ['status' => 'ignored', 'reason' => 'invalid_position_report'];
        }

        $mmsi = $this->normalizeMmsi(data_get($payload, 'MetaData.MMSI', $message['UserID'] ?? null));
        $latitude = $this->numeric(data_get($payload, 'MetaData.Latitude', $message['Latitude'] ?? null));
        $longitude = $this->numeric(data_get($payload, 'MetaData.Longitude', $message['Longitude'] ?? null));

        if ($mmsi === null) {
            return ['status' => 'ignored', 'reason' => 'invalid_mmsi'];
        }

        if ($latitude === null || $longitude === null || abs($latitude) > 90 || abs($longitude) > 180) {
            return ['status' => 'ignored', 'reason' => 'invalid_coordinates', 'mmsi' => $mmsi];
        }

        $vessel = Vessel::query()
            ->where('mmsi', $mmsi)
            ->where('ais_enabled', true)
            ->first();

        if (! $vessel) {
            return ['status' => 'ignored', 'reason' => 'unregistered_vessel', 'mmsi' => $mmsi];
        }

        $receivedAt = now();
        $vessel->forceFill(['ais_last_seen_at' => $receivedAt])->save();

        return DB::transaction(function () use (
            $vessel,
            $payload,
            $message,
            $messageType,
            $mmsi,
            $latitude,
            $longitude,
            $receivedAt
        ): array {
            $voyage = VoyageLogHeader::query()
                ->where('vessel_id', $vessel->id)
                ->whereNull('date_completed')
                ->where(fn ($status) => $status->whereNull('status')->orWhereRaw('UPPER(status) <> ?', ['COMPLETED']))
                ->latest('voyage_id')
                ->lockForUpdate()
                ->first();

            if (! $voyage) {
                return [
                    'status' => 'ignored',
                    'reason' => 'no_ongoing_voyage',
                    'vessel' => $vessel->vessel_name,
                    'mmsi' => $mmsi,
                ];
            }

            $shipName = trim((string) data_get($payload, 'MetaData.ShipName', $vessel->vessel_name));
            $locationName = sprintf(
                'AIS position%s (%.5f, %.5f)',
                $shipName !== '' ? ' - '.$shipName : '',
                $latitude,
                $longitude
            );

            // Keep the visible marker live on every valid report, even when the history point is deduplicated.
            $voyage->update([
                'current_location_id' => null,
                'current_location' => mb_substr($locationName, 0, 255),
                'current_latitude' => $latitude,
                'current_longitude' => $longitude,
            ]);

            $lastPosition = VesselPositionLog::query()
                ->where('voyage_id', $voyage->voyage_id)
                ->latest('recorded_at')
                ->latest('id')
                ->first();

            if ($lastPosition && ! $this->shouldRecordHistoryPoint($lastPosition, $latitude, $longitude, $receivedAt)) {
                return [
                    'status' => 'refreshed',
                    'reason' => 'history_point_deduplicated',
                    'vessel' => $vessel->vessel_name,
                    'voyage_id' => (int) $voyage->voyage_id,
                    'mmsi' => $mmsi,
                ];
            }

            VesselPositionLog::create([
                'vessel_id' => $vessel->id,
                'voyage_id' => $voyage->voyage_id,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'accuracy_meters' => null,
                'speed_over_ground' => $this->numeric($message['Sog'] ?? null),
                'course_over_ground' => $this->numeric($message['Cog'] ?? null),
                'true_heading' => $this->validHeading($message['TrueHeading'] ?? null),
                'ais_message_type' => $messageType,
                'location_name' => mb_substr($locationName, 0, 255),
                'source' => 'aisstream',
                'recorded_by' => null,
                'reporter_name' => 'AISStream',
                'recorded_at' => $receivedAt,
            ]);

            return [
                'status' => 'recorded',
                'vessel' => $vessel->vessel_name,
                'voyage_id' => (int) $voyage->voyage_id,
                'mmsi' => $mmsi,
            ];
        });
    }

    private function shouldRecordHistoryPoint(
        VesselPositionLog $lastPosition,
        float $latitude,
        float $longitude,
        $receivedAt
    ): bool {
        $minimumInterval = max(0, (int) config('services.aisstream.minimum_log_interval_seconds', 120));
        $minimumDistance = max(0, (float) config('services.aisstream.minimum_log_distance_meters', 100));
        $secondsSinceLast = $lastPosition->recorded_at
            ? $lastPosition->recorded_at->diffInSeconds($receivedAt)
            : PHP_INT_MAX;
        $distance = $this->distanceMeters(
            (float) $lastPosition->latitude,
            (float) $lastPosition->longitude,
            $latitude,
            $longitude
        );

        return $secondsSinceLast >= $minimumInterval || $distance >= $minimumDistance;
    }

    private function distanceMeters(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $earthRadius = 6371000;
        $latDelta = deg2rad($toLat - $fromLat);
        $lngDelta = deg2rad($toLng - $fromLng);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($lngDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(max(0, 1 - $a)));
    }

    private function normalizeMmsi(mixed $value): ?string
    {
        $mmsi = preg_replace('/\D/', '', (string) $value);

        return is_string($mmsi) && strlen($mmsi) === 9 ? $mmsi : null;
    }

    private function numeric(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function validHeading(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $heading = (int) $value;

        return $heading >= 0 && $heading <= 359 ? $heading : null;
    }
}
