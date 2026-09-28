<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class MapGeocodingController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        abort(410, 'Location text search is disabled. Select a route point and pin it directly on the map.');
    }

    public function reverse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);
        $cacheKey = 'nominatim-reverse:'.hash('sha256', round((float) $data['latitude'], 6).','.round((float) $data['longitude'], 6));
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return response()->json(['name' => $cached]);
        }

        try {
            $payload = RateLimiter::attempt('nominatim-reverse-geocoding', 1, function () use ($data): array {
                $response = Http::withHeaders([
                    'User-Agent' => config('services.nominatim.user_agent'),
                    'Accept-Language' => 'en',
                ])->timeout(10)->retry(1, 300)->get(config('services.nominatim.endpoint').'/reverse', [
                    'format' => 'jsonv2',
                    'lat' => $data['latitude'],
                    'lon' => $data['longitude'],
                    'zoom' => 10,
                    'addressdetails' => 1,
                ]);

                abort_unless($response->successful(), 502, 'The map location service is temporarily unavailable.');

                return $response->json();
            }, 1);
        } catch (ConnectionException) {
            abort(502, 'Unable to identify the pinned place right now. Check the internet connection and try again.');
        }

        abort_if($payload === false, 429, 'Please wait one second before selecting another map point.');

        $name = trim((string) ($payload['display_name'] ?? ''));
        if ($name === '') {
            $name = sprintf('Pinned location (%.5f, %.5f)', $data['latitude'], $data['longitude']);
        }
        Cache::put($cacheKey, $name, now()->addDays(30));

        return response()->json(['name' => $name]);
    }
}
