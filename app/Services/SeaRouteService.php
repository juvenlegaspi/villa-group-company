<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SeaRouteService
{
    /**
     * Generate a vessel-aware suggested route from the SeaRoutes routing API.
     *
     * @return array{geojson: array<string, mixed>, distance_nm: float|null, duration_hours: float|null, provider: string}
     */
    public function generate(
        float $originLatitude,
        float $originLongitude,
        float $destinationLatitude,
        float $destinationLongitude,
        ?float $speedInKnots = null,
        ?string $imoNumber = null,
    ): array {
        $apiKey = trim((string) config('services.searoutes.api_key'));
        if ($apiKey === '') {
            throw new RuntimeException('SeaRoutes is not configured. Add SEAROUTES_API_KEY to the server environment.');
        }

        $locations = $this->coordinate($originLongitude, $originLatitude)
            .';'.$this->coordinate($destinationLongitude, $destinationLatitude);
        $endpoint = rtrim((string) config('services.searoutes.endpoint'), '/').'/'.$locations;
        $query = ['continuousCoordinates' => 'false'];

        if ($speedInKnots !== null && $speedInKnots > 0) {
            $query['speedInKts'] = round($speedInKnots, 2);
        }

        $imo = preg_replace('/\D/', '', (string) $imoNumber);
        if (strlen($imo) === 7) {
            $query['imo'] = $imo;
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['x-api-key' => $apiKey])
                ->connectTimeout((int) config('services.searoutes.connect_timeout', 5))
                ->timeout((int) config('services.searoutes.timeout', 25))
                ->get($endpoint, $query);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('SeaRoutes could not be reached. Check the server internet connection and try again.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException($this->failureMessage($response));
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('SeaRoutes returned an unreadable response. Please generate the route again.');
        }

        $feature = $this->routeFeature($payload);
        $coordinates = $this->lineCoordinates($feature);
        if (count($coordinates) < 2) {
            throw new RuntimeException('SeaRoutes did not return a valid sea route for the selected locations.');
        }

        $properties = array_merge(
            is_array($payload['properties'] ?? null) ? $payload['properties'] : [],
            is_array($feature['properties'] ?? null) ? $feature['properties'] : [],
        );
        $distanceMeters = $this->distanceMeters($properties);
        $durationHours = $this->durationHours($properties);
        $distanceNm = $distanceMeters !== null ? $distanceMeters / 1852 : null;

        if ($durationHours === null && $distanceNm !== null && $speedInKnots !== null && $speedInKnots > 0) {
            $durationHours = $distanceNm / $speedInKnots;
        }

        return [
            'geojson' => [
                'type' => 'Feature',
                'properties' => [
                    'notice' => 'Planning reference only. Captain review is required.',
                    'source' => 'SeaRoutes',
                ],
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => array_values($coordinates),
                ],
            ],
            'distance_nm' => $distanceNm !== null ? round($distanceNm, 2) : null,
            'duration_hours' => $durationHours !== null ? round($durationHours, 2) : null,
            'provider' => 'searoutes',
        ];
    }

    private function coordinate(float $longitude, float $latitude): string
    {
        return rtrim(rtrim(sprintf('%.7F', $longitude), '0'), '.')
            .','.rtrim(rtrim(sprintf('%.7F', $latitude), '0'), '.');
    }

    /** @param array<string, mixed> $payload */
    private function routeFeature(array $payload): array
    {
        if (($payload['type'] ?? null) === 'Feature') {
            return $payload;
        }

        $feature = data_get($payload, 'features.0');

        return is_array($feature) ? $feature : [];
    }

    /**
     * SeaRoutes can return either a LineString or a one/multi-part MultiLineString.
     * The application stores one continuous ordered line for Leaflet rendering.
     *
     * @param  array<string, mixed>  $feature
     * @return array<int, array{0: float|int, 1: float|int}>
     */
    private function lineCoordinates(array $feature): array
    {
        $type = data_get($feature, 'geometry.type');
        $rawCoordinates = data_get($feature, 'geometry.coordinates');
        if (! is_array($rawCoordinates)) {
            return [];
        }

        $lines = $type === 'LineString'
            ? [$rawCoordinates]
            : ($type === 'MultiLineString' ? $rawCoordinates : []);
        $coordinates = [];

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            foreach ($line as $point) {
                if (! is_array($point) || count($point) < 2 || ! is_numeric($point[0]) || ! is_numeric($point[1])) {
                    continue;
                }

                $normalized = [(float) $point[0], (float) $point[1]];
                if ($coordinates === [] || $coordinates[array_key_last($coordinates)] !== $normalized) {
                    $coordinates[] = $normalized;
                }
            }
        }

        return $coordinates;
    }

    /** @param array<string, mixed> $properties */
    private function distanceMeters(array $properties): ?float
    {
        $distance = $properties['distance'] ?? null;
        if (is_array($distance)) {
            $distance = $distance['meters'] ?? null;
        }

        return is_numeric($distance) && (float) $distance >= 0 ? (float) $distance : null;
    }

    /** @param array<string, mixed> $properties */
    private function durationHours(array $properties): ?float
    {
        $duration = $properties['duration'] ?? null;

        if (is_numeric($duration)) {
            return (float) $duration / 3600000;
        }
        if (! is_array($duration)) {
            return null;
        }
        if (is_numeric($duration['ms'] ?? null)) {
            return (float) $duration['ms'] / 3600000;
        }
        if (is_numeric($duration['seconds'] ?? null)) {
            return (float) $duration['seconds'] / 3600;
        }
        if (is_numeric($duration['days'] ?? null)) {
            return (float) $duration['days'] * 24;
        }

        return null;
    }

    private function failureMessage(Response $response): string
    {
        return match ($response->status()) {
            401, 403 => 'SeaRoutes rejected the API key. Check the server configuration or key restrictions.',
            429 => 'The SeaRoutes request limit has been reached. Please try again later.',
            default => 'SeaRoutes could not generate this route (HTTP '.$response->status().'). Check the selected pins and try again.',
        };
    }
}
