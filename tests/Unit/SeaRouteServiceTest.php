<?php

namespace Tests\Unit;

use App\Services\SeaRouteService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class SeaRouteServiceTest extends TestCase
{
    public function test_it_generates_a_searoutes_route_without_exposing_the_key(): void
    {
        config([
            'services.searoutes.api_key' => 'private-test-key',
            'services.searoutes.endpoint' => 'https://api.searoutes.test/route/v2/sea',
        ]);
        Http::fake([
            'api.searoutes.test/*' => Http::response([
                'type' => 'FeatureCollection',
                'features' => [[
                    'type' => 'Feature',
                    'properties' => [],
                    'geometry' => [
                        'type' => 'MultiLineString',
                        'coordinates' => [
                            [[123.9185, 10.3077], [123.1, 10.5]],
                            [[123.1, 10.5], [122.5813, 10.695]],
                        ],
                    ],
                ]],
                'properties' => [
                    'distance' => 185200,
                    'duration' => 36000000,
                ],
            ]),
        ]);

        $route = app(SeaRouteService::class)->generate(
            10.3077,
            123.9185,
            10.695,
            122.5813,
            10,
            'IMO 1234567',
        );

        $this->assertSame('searoutes', $route['provider']);
        $this->assertSame('LineString', $route['geojson']['geometry']['type']);
        $this->assertCount(3, $route['geojson']['geometry']['coordinates']);
        $this->assertSame(100.0, $route['distance_nm']);
        $this->assertSame(10.0, $route['duration_hours']);
        Http::assertSent(function ($request) {
            $url = rawurldecode($request->url());

            return $request->hasHeader('x-api-key', 'private-test-key')
                && str_contains($url, '123.9185,10.3077;122.5813,10.695')
                && str_contains($url, 'speedInKts=10')
                && str_contains($url, 'imo=1234567')
                && ! str_contains($url, 'private-test-key');
        });
    }

    public function test_it_reports_a_missing_api_key_safely(): void
    {
        config(['services.searoutes.api_key' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SeaRoutes is not configured');

        app(SeaRouteService::class)->generate(10.3077, 123.9185, 10.695, 122.5813);
    }
}
