<?php

namespace Tests\Feature;

use App\Http\Controllers\MapGeocodingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class MapGeocodingTest extends TestCase
{
    public function test_text_search_is_disabled_for_pin_only_route_builder(): void
    {
        $this->withoutExceptionHandling();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(MapGeocodingController::class)->search(Request::create('/shipping/voyage-logs/map/search'));
    }

    public function test_reverse_lookup_returns_the_pinned_place_name(): void
    {
        Cache::flush();
        RateLimiter::clear('nominatim-reverse-geocoding');
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response(['display_name' => 'Cebu Strait, Philippines']),
        ]);

        $response = app(MapGeocodingController::class)->reverse(
            Request::create('/shipping/voyage-logs/map/reverse', 'GET', ['latitude' => 10.42, 'longitude' => 123.70])
        );

        $this->assertSame('Cebu Strait, Philippines', $response->getData(true)['name']);
        Http::assertSent(fn ($request) => $request['format'] === 'jsonv2'
            && (float) $request['lat'] === 10.42
            && str_contains($request->header('User-Agent')[0] ?? '', 'VillaGroupVesselTracking'));
    }
}
