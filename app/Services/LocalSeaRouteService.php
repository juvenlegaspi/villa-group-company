<?php

namespace App\Services;

use RuntimeException;
use SplPriorityQueue;

class LocalSeaRouteService
{
    /** @var array<string, mixed>|null */
    private static ?array $grid = null;

    /**
     * Generate an offline, land-avoiding planning route within Philippine waters.
     *
     * @return array{geojson: array<string, mixed>, distance_nm: float, duration_hours: float|null, provider: string}
     */
    public function generate(
        float $originLatitude,
        float $originLongitude,
        float $destinationLatitude,
        float $destinationLongitude,
        ?float $speedInKnots = null,
    ): array {
        $this->assertWithinCoverage($originLatitude, $originLongitude);
        $this->assertWithinCoverage($destinationLatitude, $destinationLongitude);

        $originCell = $this->nearestWaterCell($this->toCell($originLatitude, $originLongitude));
        $destinationCell = $this->nearestWaterCell($this->toCell($destinationLatitude, $destinationLongitude));
        $cellPath = $this->findPath($originCell, $destinationCell);
        $cellPath = $this->smoothPath($cellPath);

        $coordinates = [[$originLongitude, $originLatitude]];
        foreach ($cellPath as $cell) {
            [$latitude, $longitude] = $this->toCoordinate($cell);
            $coordinates[] = [$longitude, $latitude];
        }
        $coordinates[] = [$destinationLongitude, $destinationLatitude];
        $coordinates = $this->deduplicateCoordinates($coordinates);

        $distanceNm = 0.0;
        for ($index = 1; $index < count($coordinates); $index++) {
            $distanceNm += $this->distanceInNauticalMiles($coordinates[$index - 1], $coordinates[$index]);
        }

        $durationHours = $speedInKnots !== null && $speedInKnots > 0
            ? $distanceNm / $speedInKnots
            : null;

        return [
            'geojson' => [
                'type' => 'Feature',
                'properties' => [
                    'notice' => 'Offline planning reference only. Captain review is required.',
                    'source' => 'Villa Local Sea Grid',
                ],
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => $coordinates,
                ],
            ],
            'distance_nm' => round($distanceNm, 2),
            'duration_hours' => $durationHours !== null ? round($durationHours, 2) : null,
            'provider' => 'local_grid',
        ];
    }

    /** @return array{x: int, y: int} */
    private function toCell(float $latitude, float $longitude): array
    {
        $grid = $this->grid();

        return [
            'x' => (int) round(($longitude - $grid['bounds']['min_longitude']) / $grid['step_degrees']),
            'y' => (int) round(($latitude - $grid['bounds']['min_latitude']) / $grid['step_degrees']),
        ];
    }

    /** @param array{x: int, y: int} $cell
     *  @return array{0: float, 1: float}
     */
    private function toCoordinate(array $cell): array
    {
        $grid = $this->grid();

        return [
            (float) $grid['bounds']['min_latitude'] + ($cell['y'] * (float) $grid['step_degrees']),
            (float) $grid['bounds']['min_longitude'] + ($cell['x'] * (float) $grid['step_degrees']),
        ];
    }

    private function assertWithinCoverage(float $latitude, float $longitude): void
    {
        $bounds = $this->grid()['bounds'];
        if (
            $latitude < $bounds['min_latitude'] || $latitude > $bounds['max_latitude']
            || $longitude < $bounds['min_longitude'] || $longitude > $bounds['max_longitude']
        ) {
            throw new RuntimeException('The offline route generator currently supports Philippine waters only. Pin locations between 3-22°N and 115-132°E.');
        }
    }

    /** @param array{x: int, y: int} $cell
     *  @return array{x: int, y: int}
     */
    private function nearestWaterCell(array $cell): array
    {
        if (! $this->isLand($cell['x'], $cell['y'])) {
            return $cell;
        }

        for ($radius = 1; $radius <= 12; $radius++) {
            $candidates = [];
            for ($offset = -$radius; $offset <= $radius; $offset++) {
                $candidates[] = ['x' => $cell['x'] + $offset, 'y' => $cell['y'] - $radius];
                $candidates[] = ['x' => $cell['x'] + $offset, 'y' => $cell['y'] + $radius];
                $candidates[] = ['x' => $cell['x'] - $radius, 'y' => $cell['y'] + $offset];
                $candidates[] = ['x' => $cell['x'] + $radius, 'y' => $cell['y'] + $offset];
            }

            usort($candidates, fn (array $first, array $second): int =>
                (($first['x'] - $cell['x']) ** 2 + ($first['y'] - $cell['y']) ** 2)
                <=> (($second['x'] - $cell['x']) ** 2 + ($second['y'] - $cell['y']) ** 2)
            );

            foreach ($candidates as $candidate) {
                if ($this->insideGrid($candidate['x'], $candidate['y']) && ! $this->isLand($candidate['x'], $candidate['y'])) {
                    return $candidate;
                }
            }
        }

        throw new RuntimeException('A nearby navigable water cell could not be found. Move the pin closer to the shoreline and try again.');
    }

    /**
     * @param array{x: int, y: int} $start
     * @param array{x: int, y: int} $goal
     * @return array<int, array{x: int, y: int}>
     */
    private function findPath(array $start, array $goal): array
    {
        $grid = $this->grid();
        $width = (int) $grid['width'];
        $startIndex = ($start['y'] * $width) + $start['x'];
        $goalIndex = ($goal['y'] * $width) + $goal['x'];

        if ($startIndex === $goalIndex) {
            return [$start];
        }

        $queue = new SplPriorityQueue;
        $queue->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
        $queue->insert($startIndex, -$this->cellDistance($start, $goal));
        $scores = [$startIndex => 0.0];
        $previous = [];
        $closed = [];
        $directions = [[1, 0], [-1, 0], [0, 1], [0, -1], [1, 1], [1, -1], [-1, 1], [-1, -1]];

        while (! $queue->isEmpty()) {
            $entry = $queue->extract();
            $currentIndex = (int) $entry['data'];
            if (isset($closed[$currentIndex])) {
                continue;
            }
            $closed[$currentIndex] = true;

            if ($currentIndex === $goalIndex) {
                return $this->reconstructPath($previous, $currentIndex, $width);
            }

            $current = ['x' => $currentIndex % $width, 'y' => intdiv($currentIndex, $width)];
            foreach ($directions as [$deltaX, $deltaY]) {
                $next = ['x' => $current['x'] + $deltaX, 'y' => $current['y'] + $deltaY];
                if (! $this->insideGrid($next['x'], $next['y']) || $this->isLand($next['x'], $next['y'])) {
                    continue;
                }
                if ($deltaX !== 0 && $deltaY !== 0 && (
                    $this->isLand($current['x'] + $deltaX, $current['y'])
                    || $this->isLand($current['x'], $current['y'] + $deltaY)
                )) {
                    continue;
                }

                $nextIndex = ($next['y'] * $width) + $next['x'];
                if (isset($closed[$nextIndex])) {
                    continue;
                }

                $coastPenalty = 1 + (0.08 * $this->adjacentLandCount($next['x'], $next['y']));
                $tentativeScore = $scores[$currentIndex] + ($this->cellDistance($current, $next) * $coastPenalty);
                if ($tentativeScore >= ($scores[$nextIndex] ?? INF)) {
                    continue;
                }

                $previous[$nextIndex] = $currentIndex;
                $scores[$nextIndex] = $tentativeScore;
                $priority = -($tentativeScore + $this->cellDistance($next, $goal));
                $queue->insert($nextIndex, $priority);
            }
        }

        throw new RuntimeException('The offline route generator could not connect these two locations by water. Review the pins and try again.');
    }

    /** @return array<int, array{x: int, y: int}> */
    private function reconstructPath(array $previous, int $currentIndex, int $width): array
    {
        $path = [];
        while (true) {
            $path[] = ['x' => $currentIndex % $width, 'y' => intdiv($currentIndex, $width)];
            if (! isset($previous[$currentIndex])) {
                break;
            }
            $currentIndex = $previous[$currentIndex];
        }

        return array_reverse($path);
    }

    /**
     * @param array<int, array{x: int, y: int}> $path
     * @return array<int, array{x: int, y: int}>
     */
    private function smoothPath(array $path): array
    {
        if (count($path) < 3) {
            return $path;
        }

        $smoothed = [$path[0]];
        $current = 0;
        $last = count($path) - 1;

        while ($current < $last) {
            $candidate = $last;
            while ($candidate > $current + 1 && ! $this->hasClearWaterLine($path[$current], $path[$candidate])) {
                $candidate--;
            }
            $smoothed[] = $path[$candidate];
            $current = $candidate;
        }

        return $smoothed;
    }

    /** @param array{x: int, y: int} $start
     *  @param array{x: int, y: int} $end
     */
    private function hasClearWaterLine(array $start, array $end): bool
    {
        $deltaX = $end['x'] - $start['x'];
        $deltaY = $end['y'] - $start['y'];
        $samples = max(1, (int) ceil(max(abs($deltaX), abs($deltaY)) * 2));

        for ($sample = 0; $sample <= $samples; $sample++) {
            $fraction = $sample / $samples;
            $x = (int) round($start['x'] + ($deltaX * $fraction));
            $y = (int) round($start['y'] + ($deltaY * $fraction));
            if ($this->isLand($x, $y)) {
                return false;
            }
        }

        return true;
    }

    /** @param array{x: int, y: int} $first
     *  @param array{x: int, y: int} $second
     */
    private function cellDistance(array $first, array $second): float
    {
        [$firstLatitude, $firstLongitude] = $this->toCoordinate($first);
        [$secondLatitude, $secondLongitude] = $this->toCoordinate($second);
        $middleLatitude = deg2rad(($firstLatitude + $secondLatitude) / 2);
        $longitudeNm = ($secondLongitude - $firstLongitude) * 60 * cos($middleLatitude);
        $latitudeNm = ($secondLatitude - $firstLatitude) * 60;

        return hypot($longitudeNm, $latitudeNm);
    }

    private function adjacentLandCount(int $x, int $y): int
    {
        $count = 0;
        for ($deltaY = -1; $deltaY <= 1; $deltaY++) {
            for ($deltaX = -1; $deltaX <= 1; $deltaX++) {
                if (($deltaX !== 0 || $deltaY !== 0) && $this->isLand($x + $deltaX, $y + $deltaY)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function insideGrid(int $x, int $y): bool
    {
        $grid = $this->grid();

        return $x >= 0 && $x < $grid['width'] && $y >= 0 && $y < $grid['height'];
    }

    private function isLand(int $x, int $y): bool
    {
        if (! $this->insideGrid($x, $y)) {
            return true;
        }

        $grid = $this->grid();
        $index = ($y * $grid['width']) + $x;
        $byte = ord($grid['land_bits'][intdiv($index, 8)]);

        return ($byte & (1 << ($index % 8))) !== 0;
    }

    /**
     * Coordinates are [longitude, latitude].
     *
     * @param array{0: float, 1: float} $start
     * @param array{0: float, 1: float} $end
     */
    private function distanceInNauticalMiles(array $start, array $end): float
    {
        $latitudeDelta = deg2rad($end[1] - $start[1]);
        $longitudeDelta = deg2rad($end[0] - $start[0]);
        $startLatitude = deg2rad($start[1]);
        $endLatitude = deg2rad($end[1]);
        $haversine = sin($latitudeDelta / 2) ** 2
            + cos($startLatitude) * cos($endLatitude) * sin($longitudeDelta / 2) ** 2;

        return 3440.065 * 2 * asin(min(1, sqrt($haversine)));
    }

    /** @param array<int, array{0: float, 1: float}> $coordinates
     *  @return array<int, array{0: float, 1: float}>
     */
    private function deduplicateCoordinates(array $coordinates): array
    {
        $result = [];
        foreach ($coordinates as $coordinate) {
            $last = $result[array_key_last($result)] ?? null;
            if ($last === null || abs($last[0] - $coordinate[0]) > 0.0000001 || abs($last[1] - $coordinate[1]) > 0.0000001) {
                $result[] = [(float) $coordinate[0], (float) $coordinate[1]];
            }
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function grid(): array
    {
        if (self::$grid !== null) {
            return self::$grid;
        }

        $path = resource_path('data/philippines_sea_grid.json');
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $bits = is_array($data) ? base64_decode((string) ($data['land_bits_base64'] ?? ''), true) : false;

        if (! is_array($data) || $bits === false || $bits === '') {
            throw new RuntimeException('The offline sea-routing map is unavailable. Contact the system administrator.');
        }

        unset($data['land_bits_base64']);
        $data['land_bits'] = $bits;

        return self::$grid = $data;
    }
}
