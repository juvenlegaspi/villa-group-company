<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\HaruhayLeaseContract;
use App\Models\HaruhayBilling;
use App\Models\HaruhayCollection;
use App\Services\HaruhayBillingSchedule;
use App\Models\HaruhayProperty;
use App\Models\HaruhayTenant;
use App\Models\HaruhayUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HaruhayReportTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role = 'admin', string $company = 'Haruhay'): User
    {
        $division = Division::firstOrCreate(['name' => $company]);
        $department = Department::create(['name' => 'Property Management', 'division_id' => $division->id]);
        return User::create(['name' => 'Contract Tester', 'lastname' => 'Test', 'username' => 'test-'.str()->uuid(), 'email' => str()->uuid().'@example.com', 'password' => 'test-password-123', 'department_id' => $department->id, 'division_id' => $division->id, 'role' => $role, 'is_admin' => $role === 'admin', 'status' => true, 'must_change_password' => false]);
    }

    private function tenant(User $user): HaruhayTenant
    {
        $property = HaruhayProperty::create(['name' => 'Haruhay Space', 'owner' => 'Owner', 'type' => 'Dormitory', 'street_barangay' => 'T. Padilla', 'city' => 'Cebu City', 'province' => 'Cebu', 'status' => 'Operational', 'division_id' => $user->division_id]);
        $room = HaruhayUnit::create(['property_id' => $property->id, 'floor' => 3, 'unit_type' => 'Single bed', 'monthly_rent' => 10000, 'status' => 'Occupied', 'room_condition' => 'Ready']);
        return HaruhayTenant::create(['unit_id' => $room->id, 'name' => 'Contract Tenant', 'contact_number' => '09171234567', 'move_in_date' => today()->subDays(10), 'status' => 'Active']);
    }

    private function data(array $overrides = []): array
    {
        return array_replace([
            'starts_on' => today()->subDays(10)->toDateString(), 'ends_on' => today()->addMonthsNoOverflow(6)->toDateString(),
            'monthly_rent' => '10,000', 'billing_starts_on' => today()->subDays(10)->toDateString(),
            'first_due_on' => today()->subDays(10)->toDateString(), 'due_day' => 5,
            'billing_cycle' => 'calendar_month', 'first_month_policy' => 'full_month',
            'deposit_required' => '10,000', 'advance_required' => '10,000', 'advance_application' => 'Apply to first billing month',
        ], $overrides);
    }

    private function add(HaruhayTenant $tenant, array $overrides = []): HaruhayLeaseContract
    {
        $this->post(route('haruhay.contracts.store', $tenant), $this->data($overrides))->assertSessionHasNoErrors()->assertRedirect(route('haruhay.contracts.tenant', $tenant));
        return $tenant->contracts()->latest('id')->firstOrFail();
    }

    private function invoiceData(HaruhayLeaseContract $contract, array $overrides = []): array
    {
        return array_replace(['contract_id' => $contract->id, 'period_start' => $contract->starts_on->toDateString(), 'issued_on' => today()->toDateString(), 'water' => '100', 'electric' => '500', 'utilities_other' => '0', 'parking' => '200', 'addons_other' => '0', 'penalty' => '50', 'discount' => '150'], $overrides);
    }

    private function filters(array $overrides = []): array
    {
        return array_replace(['from' => today()->startOfMonth()->toDateString(), 'to' => today()->toDateString(), 'type' => 'overview', 'group_by' => 'month'], $overrides);
    }
    private function bill(User $user): array
    {
        $tenant = $this->tenant($user); $contract = $this->add($tenant);
        $this->post(route('haruhay.billing.store'), $this->invoiceData($contract, ['utilities_ready' => 1]))->assertSessionHasNoErrors();
        $bill = HaruhayBilling::latest('id')->first();
        $this->post(route('haruhay.billing.issue', $bill))->assertSessionHasNoErrors();
        return [$tenant, $bill->fresh()];
    }
    public function test_report_renders_graphs_filters_tables_and_empty_states(): void
    {
        $this->actingAs($this->actor());
        $this->get(route('haruhay.database', ['section' => 'reports']))->assertRedirect(route('haruhay.reports.index'));
        $this->get(route('haruhay.reports.index'))->assertOk()->assertSee('Report Center')->assertSee('Export Excel')->assertSee('Print')
            ->assertSee('Income Report')->assertSee('Tenant Ledger')->assertSee('Occupancy Report')->assertSee('Lease Expiry Report')
            ->assertSee('id="incomeTrend"', false)->assertSee('id="collectionRate"', false)->assertSee('id="agingChart"', false)->assertSee('id="unitChart"', false)
            ->assertSee('No outstanding accounts.')->assertDontSee('@else', false)
            ->assertViewHas('report', fn ($r) => $r['billingTotal'] === 0 && $r['receivedTotal'] === 0 && $r['rate'] === 0 && $r['totalUnits'] === 0);
    }
    public function test_receipts_count_advances_once_and_drafts_are_excluded(): void
    {
        $user = $this->actor(); $this->actingAs($user); [$tenant, $bill] = $this->bill($user);
        $this->post(route('haruhay.collections.store', $tenant), ['submission_token' => (string) str()->uuid(), 'receipt_type' => 'payment', 'billing_id' => $bill->id, 'amount' => 3000, 'method' => 'Cash', 'paid_on' => today()->toDateString()])->assertSessionHasNoErrors();
        $draftTenant = $this->tenant($user); $draftContract = $this->add($draftTenant);
        $this->post(route('haruhay.billing.store'), $this->invoiceData($draftContract))->assertSessionHasNoErrors();
        $r = $this->get(route('haruhay.reports.index', $this->filters()))->assertOk()->viewData('report');
        $this->assertSame(1070000, $r['billingTotal']);
        $this->assertSame(300000, $r['receivedTotal']);
        $this->assertSame(770000, $r['outstanding']);
        $this->assertEquals(28.04, $r['rate']);
        $this->assertSame(770000, array_sum($r['aging']));
        $this->assertSame(300000, array_values($r['trend'])[0]['collections']);
    }
    public function test_as_of_aging_excludes_later_credit_applications(): void
    {
        $user = $this->actor(); $this->actingAs($user); [$tenant, $bill] = $this->bill($user);
        $bill->update(['issued_on' => today()->subDays(100), 'due_on' => today()->subDays(31)]);
        $bill->payments()->create(['amount' => 700, 'paid_on' => today()->subDays(10), 'reference' => 'BACKDATED', 'created_at' => today()]);
        $this->get(route('haruhay.reports.index', $this->filters(['from' => today()->subDays(120)->toDateString(), 'to' => today()->subDay()->toDateString()])))->assertOk()->assertViewHas('report', fn ($r) => $r['outstanding'] === 1070000 && $r['aging']['1–30 Days'] === 1070000);
        $this->get(route('haruhay.reports.index', $this->filters(['from' => today()->subDays(120)->toDateString()])))->assertOk()->assertViewHas('report', fn ($r) => $r['aging']['31–60 Days'] === 1000000);
    }
    public function test_maintenance_is_not_double_counted_and_filters_are_scoped(): void
    {
        $user = $this->actor(); $this->actingAs($user); [$tenant, $bill] = $this->bill($user);
        $tenant->unit->update(['room_condition' => 'Under Maintenance']);
        $otherTenant = $this->tenant($user); $otherTenant->unit->update(['status' => 'Reserved']);
        $this->get(route('haruhay.reports.index', $this->filters(['property_id' => $bill->unit->property_id, 'tenant_id' => $tenant->id, 'type' => 'occupancy'])))->assertOk()->assertViewHas('report', fn ($r) => $r['totalUnits'] === 1 && $r['unitStatus']['Occupied'] === 1 && array_sum($r['unitStatus']) === 1 && $r['maintenance'] === 1 && $r['occupancyRate'] == 100 && $r['activeTenants'] === 1);
        $this->get(route('haruhay.reports.index', $this->filters(['type' => 'ledger', 'tenant_id' => $otherTenant->id])))->assertOk()->assertViewHas('table', fn ($t) => count($t['rows']) === 0);
    }
    public function test_export_is_formula_safe_scoped_and_viewer_can_read(): void
    {
        $user = $this->actor(); $this->actingAs($user); [$tenant, $bill] = $this->bill($user); $bill->update(['tenant_name' => '=BADFORMULA']);
        $response = $this->get(route('haruhay.reports.export', $this->filters(['type' => 'income', 'format' => 'csv'])))->assertOk()->assertDownload();
        $this->assertStringContainsString("'=BADFORMULA", $response->streamedContent());
        $foreign = $this->tenant($this->actor('admin', 'Foreign'));
        $this->get(route('haruhay.reports.index', $this->filters(['property_id' => $foreign->unit->property_id])))->assertSessionHasErrors('property_id');
        $this->get(route('haruhay.reports.export', $this->filters(['tenant_id' => $foreign->id])))->assertSessionHasErrors('tenant_id');
        $this->actingAs($this->actor('owner'));
        $this->get(route('haruhay.reports.index', $this->filters()))->assertOk();
        $this->get(route('haruhay.reports.export', $this->filters(['type' => 'income'])))->assertOk();
    }
    public function test_excel_export_contains_complete_styled_report_sheets_and_literal_names(): void
    {
        $user = $this->actor(); $this->actingAs($user); [$tenant, $bill] = $this->bill($user);
        $bill->update(['tenant_name' => '=BADFORMULA']);
        for ($i = 1; $i <= 11; $i++) {
            $extra = $bill->replicate();
            $extra->fill(['code' => 'EXPORT-'.$i, 'period_start' => $bill->period_start->copy()->addMonths($i), 'tenant_name' => 'Export row '.$i])->save();
        }
        $foreignUser = $this->actor('admin', 'Foreign');
        $foreignTenant = $this->tenant($foreignUser);
        $foreignContract = $bill->contract->replicate();
        $foreignContract->fill(['code' => 'FOREIGN-CTR', 'tenant_id' => $foreignTenant->id, 'unit_id' => $foreignTenant->unit_id])->save();
        $foreignBill = $bill->replicate();
        $foreignBill->fill(['code' => 'FOREIGN-INV', 'contract_id' => $foreignContract->id, 'tenant_id' => $foreignTenant->id, 'unit_id' => $foreignTenant->unit_id, 'tenant_name' => 'FOREIGN-SECRET'])->save();
        $this->actingAs($user);
        $response = $this->get(route('haruhay.reports.export', $this->filters(['page' => 2])))
            ->assertOk()->assertDownload()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new \PharData($path);
        try {
            $this->assertTrue($zip->isFileFormat(\Phar::ZIP));
            $this->assertStringContainsString('name="Summary"', $zip['xl/workbook.xml']->getContent());
            $this->assertStringContainsString('name="Security Deposits"', $zip['xl/workbook.xml']->getContent());
            $billing = $zip['xl/worksheets/sheet3.xml']->getContent();
            $this->assertStringContainsString('=BADFORMULA', $billing);
            $this->assertStringContainsString('t="inlineStr"', $billing);
            $this->assertStringContainsString('state="frozen"', $billing);
            $this->assertStringContainsString('<autoFilter ', $billing);
            $this->assertStringContainsString('TOTAL', $billing);
            $this->assertStringContainsString('Export row 11', $billing);
            $this->assertStringContainsString('Records: 12', $billing);
            $this->assertStringNotContainsString('<f>', $billing);
            $this->assertStringContainsString('<v>'.number_format((float) $bill->total, 2, '.', '').'</v>', $billing);
            foreach (new \RecursiveIteratorIterator($zip) as $entry) {
                $xml = $entry->getContent();
                $this->assertNotFalse(simplexml_load_string($xml), $entry->getFilename());
                $this->assertStringNotContainsString('FOREIGN-SECRET', $xml);
            }
        } finally {
            unset($zip);
            if (is_file($path)) unlink($path);
        }
    }

    public function test_future_lease_expiry_report_and_preview_use_real_contracts(): void
    {
        $user = $this->actor(); $this->actingAs($user); [$tenant, $bill] = $this->bill($user);
        $bill->contract->update(['ends_on' => today()->addDays(10)]);
        $this->get(route('haruhay.reports.index', $this->filters()))->assertOk()->assertViewHas('report', fn ($r) => $r['upcoming']->count() === 1);
        $this->get(route('haruhay.reports.index', $this->filters(['type' => 'leases', 'from' => today()->toDateString(), 'to' => today()->addDays(30)->toDateString()])))->assertOk()->assertViewHas('table', fn ($t) => count($t['rows']) === 1)->assertViewHas('report', fn ($r) => $r['asOf'] === today()->toDateString());
        $this->get(route('haruhay.reports.index', $this->filters(['to' => today()->addDay()->toDateString()])))->assertSessionHasErrors('to');
    }

    public function test_invalid_ranges_and_groups_and_paginated_details(): void
    {
        $this->actingAs($this->actor());
        $this->get(route('haruhay.reports.index', $this->filters(['from' => today()->toDateString(), 'to' => today()->subDay()->toDateString()])))->assertSessionHasErrors('to');
        $this->get(route('haruhay.reports.index', $this->filters(['group_by' => 'invalid'])))->assertSessionHasErrors('group_by');
        $this->get(route('haruhay.reports.index', $this->filters(['from' => today()->subYears(3)->toDateString()])))->assertSessionHasErrors('to');
        $this->get(route('haruhay.reports.index', $this->filters(['group_by' => 'day', 'from' => today()->subDays(14)->toDateString(), 'page' => 2])))->assertOk()->assertViewHas('details', fn ($rows) => $rows->total() === 15 && $rows->perPage() === 10 && $rows->count() === 5);
    }
}
