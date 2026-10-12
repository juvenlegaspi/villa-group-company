<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class VesselWeatherService
{
    public function current(float $latitude, float $longitude): array
    {
        $latitude = round($latitude, 3);
        $longitude = round($longitude, 3);
        $cacheKey = 'vessel-weather:'.hash('sha256', $latitude.','.$longitude);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => config('services.met_norway.user_agent'),
                'Accept' => 'application/json',
            ])->timeout(10)->retry(1, 300)->get(
                rtrim((string) config('services.met_norway.endpoint'), '/').'/compact',
                ['lat' => $latitude, 'lon' => $longitude]
            );
        } catch (ConnectionException) {
            return $this->unavailable();
        }

        if (! $response->successful()) {
            return $this->unavailable();
        }

        $timeSeries = $response->json('properties.timeseries.0');
        $instant = data_get($timeSeries, 'data.instant.details');
        if (! is_array($timeSeries) || ! is_array($instant)) {
            return $this->unavailable();
        }

        $symbolCode = (string) (data_get($timeSeries, 'data.next_1_hours.summary.symbol_code')
            ?? data_get($timeSeries, 'data.next_6_hours.summary.symbol_code')
            ?? 'unknown');
        $windDirection = $this->compassDirection(data_get($instant, 'wind_from_direction'));
        $weather = [
            'available' => true,
            'condition' => $this->conditionLabel($symbolCode),
            'symbol_code' => $symbolCode,
            'temperature_c' => $this->number(data_get($instant, 'air_temperature'), 1),
            'humidity_percent' => $this->number(data_get($instant, 'relative_humidity'), 0),
            'precipitation_mm' => $this->number(
                data_get($timeSeries, 'data.next_1_hours.details.precipitation_amount')
                    ?? data_get($timeSeries, 'data.next_6_hours.details.precipitation_amount'),
                1
            ),
            'wind_speed_knots' => $this->number(((float) data_get($instant, 'wind_speed', 0)) * 1.94384, 1),
            'wind_direction' => $windDirection,
            'forecast_at' => data_get($timeSeries, 'time'),
            'attribution' => 'Weather data: MET Norway',
        ];

        Cache::put($cacheKey, $weather, now()->addMinutes((int) config('services.met_norway.cache_minutes', 10)));

        return $weather;
    }

    private function unavailable(): array
    {
        return [
            'available' => false,
            'message' => 'Weather data is temporarily unavailable.',
            'attribution' => 'Weather data: MET Norway',
        ];
    }

    private function number(mixed $value, int $precision): ?float
    {
        return is_numeric($value) ? round((float) $value, $precision) : null;
    }

    private function compassDirection(mixed $degrees): ?string
    {
        if (! is_numeric($degrees)) {
            return null;
        }

        $directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];

        return $directions[(int) round(((float) $degrees) / 45) % 8];
    }

    private function conditionLabel(string $symbolCode): string
    {
        $code = preg_replace('/_(day|night|polartwilight)$/', '', strtolower($symbolCode));

        return match (true) {
            str_contains($code, 'thunder') => 'Thunderstorms',
            str_contains($code, 'heavyrain') => 'Heavy rain',
            str_contains($code, 'rainshowers') => 'Rain showers',
            str_contains($code, 'lightrain') => 'Light rain',
            str_contains($code, 'rain') => 'Rain',
            str_contains($code, 'snow') || str_contains($code, 'sleet') => 'Wintry weather',
            str_contains($code, 'fog') => 'Foggy',
            str_contains($code, 'partlycloudy') => 'Partly cloudy',
            str_contains($code, 'cloudy') => 'Cloudy',
            str_contains($code, 'fair') => 'Fair',
            str_contains($code, 'clearsky') => 'Clear sky',
            default => 'Weather available',
        };
    }
}
