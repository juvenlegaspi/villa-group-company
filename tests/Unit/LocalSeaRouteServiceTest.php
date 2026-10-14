<?php

namespace Tests\Unit;

use App\Services\LocalSeaRouteService;
use App\Services\SuggestedRouteService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class LocalSeaRouteServiceTest extends TestCase
{
    public function test_it_generates_an_offline_land_avoiding_route_in_philippine_waters(): void
    {
        $route = app(LocalSeaRouteService::class)->generate(
            10.3157,
            123.8854,
            14.5995,
            120.9842,
            10,
        );

        $coordinates = $route['geojson']['geometry']['coordinates'];

        $this->assertSame('local_grid', $route['provider']);
        $this->assertSame('LineString', $route['geojson']['geometry']['type']);
        $this->assertGreaterThan(2, count($coordinates));
        $this->assertSame([123.8854, 10.3157], $coordinates[0]);
        $this->assertSame([120.9842, 14.5995], $coordinates[array_key_last($coordinates)]);
        $this->assertGreaterThan(300, $route['distance_nm']);
        $this->assertEqualsWithDelta($route['distance_nm'] / 10, $route['duration_hours'], 0.02);
    }

    public function test_it_rejects_locations_outside_the_offline_coverage_area(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Philippine waters only');

        app(LocalSeaRouteService::class)->generate(1.29, 103.85, 14.59, 120.98);
    }

    public function test_it_connects_representative_domestic_voyage_corridors(): void
    {
        $corridors = [
            [[13.7567, 121.0584], [8.9475, 125.5406]], // Batangas to Butuan
            [[14.8292, 120.2828], [10.3157, 123.8854]], // Olongapo to Cebu
            [[10.3157, 123.8854], [12.0674, 124.6042]], // Cebu to Calbayog
        ];

        foreach ($corridors as [$origin, $destination]) {
            $route = app(LocalSeaRouteService::class)->generate(
                $origin[0],
                $origin[1],
                $destination[0],
                $destination[1],
                10,
            );

            $this->assertSame('local_grid', $route['provider']);
            $this->assertGreaterThan(2, count($route['geojson']['geometry']['coordinates']));
            $this->assertGreaterThan(0, $route['distance_nm']);
        }
    }

    public function test_suggested_routes_fall_back_locally_when_searoutes_is_forbidden(): void
    {
        config([
            'services.voyage_routes.provider' => 'searoutes',
            'services.voyage_routes.local_fallback' => true,
            'services.searoutes.api_key' => 'expired-test-key',
            'services.searoutes.endpoint' => 'https://api.searoutes.test/route/v2/sea',
        ]);
        Http::fake([
            'api.searoutes.test/*' => Http::response(['message' => 'Forbidden'], 403),
        ]);

        $route = app(SuggestedRouteService::class)->generate(
            10.3157,
            123.8854,
            14.5995,
            120.9842,
            10,
        );

        $this->assertSame('local_grid', $route['provider']);
    }
}
