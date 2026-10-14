<?php

namespace App\Services;

use RuntimeException;

class SuggestedRouteService
{
    public function __construct(
        private readonly LocalSeaRouteService $localRoutes,
        private readonly SeaRouteService $seaRoutes,
    ) {}

    /**
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
        $provider = strtolower((string) config('services.voyage_routes.provider', 'local'));

        if ($provider === 'searoutes') {
            try {
                return $this->seaRoutes->generate(
                    $originLatitude,
                    $originLongitude,
                    $destinationLatitude,
                    $destinationLongitude,
                    $speedInKnots,
                    $imoNumber,
                );
            } catch (RuntimeException $exception) {
                if (! config('services.voyage_routes.local_fallback', true)) {
                    throw $exception;
                }
            }
        }

        return $this->localRoutes->generate(
            $originLatitude,
            $originLongitude,
            $destinationLatitude,
            $destinationLongitude,
            $speedInKnots,
        );
    }
}
