<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\YatiraSalesLead;
use App\Models\YatiraSalesStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class YatiraSalesWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_sales_agent_can_register_and_directly_update_an_assigned_lead(): void
    {
        $agent = $this->salesUser('sales-agent', 'Agent One');

        $this->actingAs($agent)->post(route('yatira.sales.store'), $this->leadPayload())
            ->assertRedirect();

        $lead = YatiraSalesLead::firstOrFail();
        $this->assertMatchesRegularExpression('/^LD-\d{4}-\d{4}$/', $lead->lead_code);
        $this->assertSame($agent->id, $lead->assigned_agent_id);
        $this->assertSame('New Lead Registration', $lead->stage->name);
        $this->assertSame(8.0, (float) $lead->probability_percent);
        $this->assertSame('ACTIVE', $lead->status);

        $proposalSent = YatiraSalesStage::where('name', 'Proposal Sent')->firstOrFail();
        $this->actingAs($agent)->post(route('yatira.sales.stage', $lead), [
            'sales_stage_id' => $proposalSent->id,
            'stage_remarks' => 'Proposal delivered to the client.',
        ])->assertRedirect();

        $lead->refresh();
        $this->assertSame($proposalSent->id, $lead->sales_stage_id);
        $this->assertSame(50.0, (float) $lead->probability_percent);
        $this->assertDatabaseHas('yatira_sales_histories', [
            'yatira_sales_lead_id' => $lead->id,
            'action' => 'STAGE_UPDATED',
            'user_id' => $agent->id,
        ]);
    }

    public function test_awarded_and_cancelled_outcomes_are_automatic_and_audited(): void
    {
        $manager = $this->salesUser('sales-manager', 'Sales Manager');
        $lead = $this->createLead($manager);
        $awarded = YatiraSalesStage::where('name', 'Awarded')->firstOrFail();

        $this->actingAs($manager)->post(route('yatira.sales.stage', $lead), [
            'sales_stage_id' => $awarded->id,
        ])->assertRedirect();

        $lead->refresh();
        $this->assertSame('AWARDED', $lead->status);
        $this->assertSame(100.0, (float) $lead->probability_percent);

        $otherLead = $this->createLead($manager, ['client_name' => 'Cancelled Client']);
        $this->actingAs($manager)->post(route('yatira.sales.cancel', $otherLead), [
            'cancellation_reason' => 'Client deferred the project indefinitely.',
        ])->assertRedirect();

        $otherLead->refresh();
        $this->assertSame('CANCELLED', $otherLead->status);
        $this->assertSame(0.0, (float) $otherLead->probability_percent);
        $this->assertNotNull($otherLead->cancelled_at);
        $this->assertDatabaseHas('yatira_sales_histories', [
            'yatira_sales_lead_id' => $otherLead->id,
            'action' => 'LEAD_CANCELLED',
        ]);
    }

    public function test_agent_is_limited_to_own_leads_while_sales_manager_sees_all(): void
    {
        $firstAgent = $this->salesUser('sales-agent', 'First Agent');
        $secondAgent = $this->salesUser('sales-agent', 'Second Agent');
        $manager = $this->salesUser('sales-manager', 'Sales Manager');
        $lead = $this->createLead($firstAgent);

        $this->actingAs($secondAgent)->get(route('yatira.sales.show', $lead))->assertForbidden();
        $this->actingAs($secondAgent)->get(route('yatira.sales.index'))->assertOk()->assertDontSee($lead->lead_code);
        $this->actingAs($manager)->get(route('yatira.sales.index'))->assertOk()->assertSee($lead->lead_code);
    }

    public function test_user_without_sales_permission_cannot_open_sales_module(): void
    {
        $divisionId = DB::table('divisions')->where('name', 'Yatira')->value('id');
        $departmentId = DB::table('departments')->where(['division_id' => $divisionId, 'name' => 'Engineering'])->value('id');
        $positionId = DB::table('positions')->where(['division_id' => $divisionId, 'department_id' => $departmentId, 'code' => 'civil-engineer'])->value('id');
        $user = $this->makeUser('Engineer', $divisionId, $departmentId, $positionId, 'staff');

        $this->actingAs($user)->get(route('yatira.sales.index'))->assertForbidden();
    }

    private function createLead(User $user, array $overrides = []): YatiraSalesLead
    {
        $this->actingAs($user)->post(route('yatira.sales.store'), array_merge($this->leadPayload(), $overrides))->assertRedirect();

        return YatiraSalesLead::latest('id')->firstOrFail();
    }

    private function leadPayload(): array
    {
        return [
            'date_registered' => now()->toDateString(),
            'client_name' => 'ABC Development Corp.',
            'contact_person' => 'Maria Santos',
            'contact_number' => '09171234567',
            'email' => 'maria@example.test',
            'project_name' => 'Warehouse Expansion',
            'project_type' => 'Commercial Construction',
            'estimated_value' => 2500000,
            'project_location' => 'Cagayan de Oro City',
            'expected_close_date' => now()->addMonth()->toDateString(),
        ];
    }

    private function salesUser(string $positionCode, string $name): User
    {
        $divisionId = DB::table('divisions')->where('name', 'Yatira')->value('id');
        $departmentId = DB::table('departments')->where(['division_id' => $divisionId, 'name' => 'Sales and Business Development'])->value('id');
        $positionId = DB::table('positions')->where(['division_id' => $divisionId, 'department_id' => $departmentId, 'code' => $positionCode])->value('id');

        return $this->makeUser($name, $divisionId, $departmentId, $positionId, $positionCode === 'sales-manager' ? 'manager' : 'staff');
    }

    private function makeUser(string $name, int $divisionId, int $departmentId, int $positionId, string $role): User
    {
        static $sequence = 0;
        $sequence++;

        return User::create([
            'name' => $name,
            'lastname' => 'Tester',
            'username' => 'sales-user-'.$sequence,
            'email' => 'sales-user-'.$sequence.'@example.test',
            'password' => Hash::make('password-for-tests'),
            'role' => $role,
            'department_id' => $departmentId,
            'position_id' => $positionId,
            'division_id' => $divisionId,
            'is_admin' => false,
            'must_change_password' => false,
            'status' => true,
        ]);
    }
}
