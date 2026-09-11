<?php

namespace Tests\Unit;

use App\Models\ActivityVoyage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ActivityVoyageTest extends TestCase
{
    #[DataProvider('activityTypes')]
    public function test_only_actual_cargo_activities_are_classified_as_cargo_movements(string $name, ?string $expected): void
    {
        $activity = new ActivityVoyage(['name' => $name]);

        $this->assertSame($expected, $activity->cargoMovementType());
    }

    public static function activityTypes(): array
    {
        return [
            'loading' => ['Loading', 'loading'],
            'completed loading' => ['Completed Loading', 'loading'],
            'unloading' => ['Unloading', 'unloading'],
            'completed unloading' => ['Completed Unloading', 'unloading'],
            'transit to load port' => ['In Transit to Loadport', null],
            'transit to discharge port' => ['In Transit to Disport', null],
            'weather observation' => ['Observing Weather / Big Swell', null],
        ];
    }
}
