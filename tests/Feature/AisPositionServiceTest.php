<?php

namespace Tests\Feature;

use App\Models\Vessel;
use App\Models\VesselPositionLog;
use App\Models\VoyageLogHeader;
use App\Services\AisPositionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AisPositionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_class_b_ais_position_for_an_ongoing_voyage(): void
    {
        Carbon::setTestNow('2026-10-13 09:00:00');
        [$vessel, $voyage] = $this->ongoingVoyage();

        $result = app(AisPositionService::class)->ingest($this->payload());

        $this->assertSame('recorded', $result['status']);
        $this->assertDatabaseHas('vessel_position_logs', [
            'vessel_id' => $vessel->id,
            'voyage_id' => $voyage->voyage_id,
            'source' => 'aisstream',
            'ais_message_type' => 'StandardClassBPositionReport',
            'speed_over_ground' => 8.40,
            'course_over_ground' => 115.20,
            'true_heading' => 116,
        ]);
        $this->assertEqualsWithDelta(14.5951000, (float) $voyage->fresh()->current_latitude, 0.0000001);
        $this->assertEqualsWithDelta(120.9782000, (float) $voyage->fresh()->current_longitude, 0.0000001);
        $this->assertNotNull($vessel->fresh()->ais_last_seen_at);
    }

    public function test_it_refreshes_marker_without_creating_dense_duplicate_history(): void
    {
        Carbon::setTestNow('2026-10-13 09:00:00');
        [, $voyage] = $this->ongoingVoyage();
        $service = app(AisPositionService::class);
        $service->ingest($this->payload());

        Carbon::setTestNow('2026-10-13 09:00:30');
        $secondPayload = $this->payload();
        data_set($secondPayload, 'MetaData.Latitude', 14.59511);
        data_set($secondPayload, 'MetaData.Longitude', 120.97821);
        $result = $service->ingest($secondPayload);

        $this->assertSame('refreshed', $result['status']);
        $this->assertSame(1, VesselPositionLog::where('voyage_id', $voyage->voyage_id)->count());
        $this->assertEqualsWithDelta(14.59511, (float) $voyage->fresh()->current_latitude, 0.0000001);
    }

    public function test_it_does_not_attach_an_ais_position_to_a_completed_voyage(): void
    {
        Carbon::setTestNow('2026-10-13 09:00:00');
        [$vessel, $voyage] = $this->ongoingVoyage();
        $voyage->update(['status' => 'COMPLETED', 'date_completed' => today()]);

        $result = app(AisPositionService::class)->ingest($this->payload());

        $this->assertSame('no_ongoing_voyage', $result['reason']);
        $this->assertDatabaseMissing('vessel_position_logs', [
            'vessel_id' => $vessel->id,
            'source' => 'aisstream',
        ]);
        $this->assertNotNull($vessel->fresh()->ais_last_seen_at);
    }

    private function ongoingVoyage(): array
    {
        $vessel = Vessel::create([
            'vessel_name' => 'MV JAKE VINCENT OCHO',
            'imo_number' => '9084023',
            'mmsi' => '548235468',
            'ais_enabled' => true,
        ]);

        $voyage = VoyageLogHeader::create([
            'date_created' => today(),
            'voyage_no' => 'AIS-TEST-001',
            'status' => 'ONGOING',
            'vessel_id' => $vessel->id,
        ]);

        return [$vessel, $voyage];
    }

    private function payload(): array
    {
        return [
            'MessageType' => 'StandardClassBPositionReport',
            'MetaData' => [
                'MMSI' => 548235468,
                'ShipName' => 'MV JAKE VINCENT OCHO',
                'Latitude' => 14.5951,
                'Longitude' => 120.9782,
            ],
            'Message' => [
                'StandardClassBPositionReport' => [
                    'UserID' => 548235468,
                    'Valid' => true,
                    'Sog' => 8.4,
                    'Cog' => 115.2,
                    'TrueHeading' => 116,
                ],
            ],
        ];
    }
}
