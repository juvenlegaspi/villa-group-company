<?php

/**
 * Build the compact offline land mask used by LocalSeaRouteService.
 *
 * Usage:
 *   php scripts/build_sea_grid.php <ne_10m_land.geojson> [output.json]
 *
 * Natural Earth data is public domain: https://www.naturalearthdata.com/about/terms-of-use/
 */

declare(strict_types=1);

$input = $argv[1] ?? null;
$output = $argv[2] ?? __DIR__.'/../resources/data/philippines_sea_grid.json';

if (! $input || ! is_file($input)) {
    fwrite(STDERR, "Provide the path to ne_10m_land.geojson.\n");
    exit(1);
}

$geojson = json_decode((string) file_get_contents($input), true, 512, JSON_THROW_ON_ERROR);
$minLongitude = 115.0;
$maxLongitude = 132.0;
$minLatitude = 3.0;
$maxLatitude = 22.0;
$step = 0.04;
$width = (int) round(($maxLongitude - $minLongitude) / $step) + 1;
$height = (int) round(($maxLatitude - $minLatitude) / $step) + 1;
$land = array_fill(0, $width * $height, false);

$rasterizeRing = static function (array $ring, bool $value) use (
    &$land,
    $minLongitude,
    $maxLongitude,
    $minLatitude,
    $maxLatitude,
    $step,
    $width,
    $height,
): void {
    if (count($ring) < 4) {
        return;
    }

    $latitudes = array_column($ring, 1);
    $longitudes = array_column($ring, 0);
    $ringMinLatitude = max($minLatitude, (float) min($latitudes));
    $ringMaxLatitude = min($maxLatitude, (float) max($latitudes));
    $ringMinLongitude = max($minLongitude, (float) min($longitudes));
    $ringMaxLongitude = min($maxLongitude, (float) max($longitudes));

    if ($ringMinLatitude > $ringMaxLatitude || $ringMinLongitude > $ringMaxLongitude) {
        return;
    }

    $startY = max(0, (int) ceil(($ringMinLatitude - $minLatitude) / $step));
    $endY = min($height - 1, (int) floor(($ringMaxLatitude - $minLatitude) / $step));
    $pointCount = count($ring);

    for ($y = $startY; $y <= $endY; $y++) {
        $latitude = $minLatitude + ($y * $step);
        $intersections = [];

        for ($i = 0, $j = $pointCount - 1; $i < $pointCount; $j = $i++) {
            $first = $ring[$i];
            $second = $ring[$j];
            $firstLatitude = (float) $first[1];
            $secondLatitude = (float) $second[1];

            if (($firstLatitude > $latitude) === ($secondLatitude > $latitude)) {
                continue;
            }

            $longitude = (float) $first[0]
                + (($latitude - $firstLatitude) * ((float) $second[0] - (float) $first[0]) / ($secondLatitude - $firstLatitude));
            $intersections[] = $longitude;
        }

        sort($intersections, SORT_NUMERIC);
        for ($i = 0; $i + 1 < count($intersections); $i += 2) {
            $from = max($ringMinLongitude, (float) $intersections[$i]);
            $to = min($ringMaxLongitude, (float) $intersections[$i + 1]);
            $startX = max(0, (int) ceil(($from - $minLongitude) / $step));
            $endX = min($width - 1, (int) floor(($to - $minLongitude) / $step));

            for ($x = $startX; $x <= $endX; $x++) {
                $land[($y * $width) + $x] = $value;
            }
        }
    }
};

foreach ($geojson['features'] ?? [] as $feature) {
    $geometry = $feature['geometry'] ?? [];
    $type = $geometry['type'] ?? null;
    $coordinates = $geometry['coordinates'] ?? [];
    $polygons = $type === 'Polygon' ? [$coordinates] : ($type === 'MultiPolygon' ? $coordinates : []);

    foreach ($polygons as $polygon) {
        foreach ($polygon as $ringIndex => $ring) {
            $rasterizeRing($ring, $ringIndex === 0);
        }
    }
}

$bytes = array_fill(0, (int) ceil(count($land) / 8), 0);
foreach ($land as $index => $isLand) {
    if ($isLand) {
        $bytes[intdiv($index, 8)] |= 1 << ($index % 8);
    }
}

$payload = [
    'version' => 1,
    'source' => 'Natural Earth 1:10m land polygons (public domain)',
    'bounds' => [
        'min_longitude' => $minLongitude,
        'max_longitude' => $maxLongitude,
        'min_latitude' => $minLatitude,
        'max_latitude' => $maxLatitude,
    ],
    'step_degrees' => $step,
    'width' => $width,
    'height' => $height,
    'land_bits_base64' => base64_encode(pack('C*', ...$bytes)),
];

if (! is_dir(dirname($output))) {
    mkdir(dirname($output), 0777, true);
}

file_put_contents($output, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
fwrite(STDOUT, sprintf("Created %s (%d x %d grid).\n", $output, $width, $height));
