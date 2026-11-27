<?php

namespace Tests\Feature;

use App\Models\AccessRole;
use App\Models\Department;
use App\Models\Division;
use App\Models\Permission;
use App\Models\Position;
use App\Models\User;
use App\Models\UserVesselAssignment;
use App\Models\Vessel;
use App\Models\VesselCertificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VesselCertificateWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Division $company;
    private Department $department;
    private Vessel $vessel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Division::create(['name' => 'Villa shipping Lines']);
        $this->department = Department::create(['division_id' => $this->company->id, 'name' => 'Marine Operations']);
        $this->vessel = Vessel::create(['vessel_name' => 'MV Certificate Test']);
    }

    public function test_assigned_viewer_cannot_create_or_edit_certificates(): void
    {
        $captain = $this->user('Vessel Captain', 'vessel-captain', ['shipping.certificates.access']);
        $this->assign($captain);

        $certificate = VesselCertificate::create([
            'vessel_id' => $this->vessel->id, 'certificate_name' => 'Safety Certificate',
            'issue_date' => today(), 'expiry_date' => today()->addYear(), 'workflow_status' => 'active',
        ]);

        $this->actingAs($captain)->get(route('vessel.certificates.show', $this->vessel))
            ->assertOk()->assertDontSee('Add certificate')->assertDontSee(route('vessel-certificates.renew', $certificate));
        $this->actingAs($captain)->get(route('vessel-certificates.add', $this->vessel))->assertForbidden();
        $this->actingAs($captain)->get(route('vessel-certificates.edit', $certificate))->assertForbidden();
    }

    public function test_manager_can_create_and_renew_without_destroying_previous_record_or_document(): void
    {
        Storage::fake('local');
        $manager = $this->user('Operations Manager', 'operations-manager', ['shipping.certificates.access', 'certificates.manage']);

        $this->actingAs($manager)->post(route('vessel-certificates.store'), [
            'vessel_id' => $this->vessel->id, 'certificate_name' => 'Class Certificate',
            'issue_date' => today()->toDateString(), 'expiry_date' => today()->addYear()->toDateString(),
            'remarks' => 'Initial issue', 'document' => UploadedFile::fake()->create('class-certificate.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('vessel.certificates.show', $this->vessel));

        $original = VesselCertificate::firstOrFail();
        $originalPath = $original->document;
        Storage::disk('local')->assertExists($originalPath);
        $this->assertDatabaseHas('vessel_certificate_audits', ['vessel_certificate_id' => $original->id, 'action' => 'created_and_activated']);
        $this->assertDatabaseHas('vessel_certificate_documents', ['vessel_certificate_id' => $original->id, 'original_name' => 'class-certificate.pdf', 'is_current' => true]);
        $this->assertSame('active', $original->workflow_status);

        $this->actingAs($manager)->post(route('vessel-certificates.renew.store', $original), [
            'vessel_id' => $this->vessel->id, 'certificate_name' => 'Class Certificate',
            'issue_date' => today()->addYear()->toDateString(), 'expiry_date' => today()->addYears(2)->toDateString(),
            'remarks' => 'Renewed issue', 'document' => UploadedFile::fake()->create('class-certificate-renewed.pdf', 120, 'application/pdf'),
        ])->assertRedirect(route('vessel.certificates.show', $this->vessel));

        $original->refresh();
        $renewal = VesselCertificate::where('previous_certificate_id', $original->id)->firstOrFail();
        $this->assertSame('superseded', $original->workflow_status);
        $this->assertSame('active', $renewal->workflow_status);
        $this->assertNotSame($originalPath, $renewal->document);
        Storage::disk('local')->assertExists($originalPath);
        Storage::disk('local')->assertExists($renewal->document);
    }

    public function test_expiry_date_boundaries_and_summary_counts_use_all_rows(): void
    {
        $manager = $this->user('Operations Manager', 'operations-manager', ['shipping.certificates.access', 'certificates.manage']);
        for ($index = 1; $index <= 11; $index++) {
            VesselCertificate::create([
                'vessel_id' => $this->vessel->id, 'certificate_name' => 'Certificate '.$index,
                'issue_date' => today()->subMonth(), 'expiry_date' => $index === 1 ? today() : today()->addYear(),
                'workflow_status' => 'active',
            ]);
        }

        $this->assertSame(0, VesselCertificate::expired()->count());
        $this->assertSame(1, VesselCertificate::expiringWithinDays()->count());
        $this->actingAs($manager)->get(route('vessel.certificates.show', $this->vessel))
            ->assertOk()->assertSeeInOrder(['Total', '11', 'Valid', '10', 'Expiring', '1', 'Expired', '0']);
    }

    public function test_liaison_can_add_but_technical_manager_cannot_manage_certificates(): void
    {
        Storage::fake('local');
        $liaison = $this->user('Liaison Officer', 'liaison-officer', ['shipping.certificates.access', 'certificates.manage']);
        $technical = $this->user('Technical Manager', 'technical-manager', ['shipping.certificates.access', 'certificates.manage']);

        $this->actingAs($technical)->get(route('vessel-certificates.add', $this->vessel))->assertForbidden();
        $this->actingAs($liaison)->post(route('vessel-certificates.store'), [
            'vessel_id' => $this->vessel->id, 'certificate_name' => 'Load Line Certificate',
            'issue_date' => today()->toDateString(), 'expiry_date' => today()->addYear()->toDateString(),
            'document' => UploadedFile::fake()->create('load-line.pdf', 80, 'application/pdf'),
        ])->assertRedirect(route('vessel.certificates.show', $this->vessel));

        $certificate = VesselCertificate::firstOrFail();
        $this->assertSame('active', $certificate->workflow_status);
        $this->assertFalse(app('router')->has('vessel-certificates.submit'));
        $this->assertFalse(app('router')->has('vessel-certificates.approve'));
    }

    public function test_renewal_form_shows_current_expiry_and_editable_suggested_dates(): void
    {
        $manager = $this->user('Operations Manager', 'operations-manager', ['shipping.certificates.access', 'certificates.manage']);
        $certificate = VesselCertificate::create([
            'vessel_id' => $this->vessel->id, 'certificate_name' => 'Safety Management Certificate',
            'issue_date' => '2026-09-01', 'expiry_date' => '2027-08-31', 'workflow_status' => 'active',
        ]);

        $this->actingAs($manager)->get(route('vessel-certificates.renew', $certificate))
            ->assertOk()
            ->assertSee('Current certificate expiry')
            ->assertSee('August 31, 2027')
            ->assertSee('value="2027-09-01"', false)
            ->assertSee('value="2028-08-30"', false);
    }

    public function test_calendar_respects_vessel_access_month_boundaries_and_current_records(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2028-02-15 12:00:00', 'Asia/Manila'));
        $captain = $this->user('Vessel Captain', 'vessel-captain', ['shipping.certificates.access']);
        $this->assign($captain);
        $other = Vessel::create(['vessel_name' => 'Restricted vessel']);
        foreach ([['Yesterday', '2028-02-14', 'active', $this->vessel->id], ['Today', '2028-02-15', 'active', $this->vessel->id], ['Leap day', '2028-02-29', 'active', $this->vessel->id], ['Next month', '2028-03-01', 'active', $this->vessel->id], ['History', '2028-02-20', 'superseded', $this->vessel->id], ['Draft', '2028-02-20', 'draft', $this->vessel->id], ['Secret', '2028-02-20', 'active', $other->id]] as [$name, $expiry, $status, $vesselId]) {
            VesselCertificate::create(['vessel_id' => $vesselId, 'certificate_name' => $name, 'issue_date' => '2027-01-01', 'expiry_date' => $expiry, 'workflow_status' => $status]);
        }
        $this->actingAs($captain)->getJson(route('vessel-certificates.calendar', ['month' => '2028-02']))
            ->assertOk()->assertJsonPath('total', 3)->assertJsonPath('events.0.status', 'Expired')
            ->assertJsonPath('events.1.status', 'Expires today')->assertJsonPath('events.2.date', '2028-02-29')
            ->assertDontSee('Secret')->assertDontSee('History')->assertDontSee('Draft')->assertDontSee('Next month');
        $this->getJson(route('vessel-certificates.calendar', ['month' => '2028-03']))->assertOk()->assertJsonPath('total', 1);
        $this->getJson(route('vessel-certificates.calendar', ['month' => '2028-02', 'vessel_id' => $other->id]))->assertForbidden();
        $this->getJson(route('vessel-certificates.calendar', ['month' => '2028-13']))->assertUnprocessable();
        $this->getJson(route('vessel-certificates.calendar'))->assertUnprocessable();
        $this->get(route('vessel-certificates.index'))->assertOk()->assertSee('data-certificate-calendar', false);
        $this->get(route('vessel.certificates.show', $this->vessel))->assertOk()->assertSee('data-vessel="'.$this->vessel->id.'"', false);
        $this->travelBack();
    }

    public function test_calendar_company_wide_user_can_filter_one_vessel(): void
    {
        $manager = $this->user('Operations Manager', 'operations-manager', ['shipping.certificates.access']);
        $other = Vessel::create(['vessel_name' => 'Other vessel']);
        foreach ([$this->vessel, $other] as $vessel) {
            VesselCertificate::create(['vessel_id' => $vessel->id, 'certificate_name' => 'Safety', 'issue_date' => '2026-01-01', 'expiry_date' => '2026-12-31', 'workflow_status' => 'active']);
        }
        $this->actingAs($manager)->getJson(route('vessel-certificates.calendar', ['month' => '2026-12']))->assertOk()->assertJsonPath('total', 2);
        $this->getJson(route('vessel-certificates.calendar', ['month' => '2026-12', 'vessel_id' => $this->vessel->id]))->assertOk()->assertJsonPath('total', 1)->assertJsonPath('events.0.vessel', $this->vessel->vessel_name);
    }

    public function test_manager_adds_optional_sub_certificate_without_changing_main_expiry(): void
    {
        Storage::fake('local');
        $manager = $this->user('Operations Manager', 'operations-manager', ['shipping.certificates.access', 'certificates.manage']);
        $parent = VesselCertificate::create(['vessel_id' => $this->vessel->id, 'certificate_name' => 'Five Year Safety', 'issue_date' => today()->subYear(), 'expiry_date' => today()->addYears(4), 'workflow_status' => 'active']);
        $expiry = $parent->expiry_date->toDateString();
        $this->actingAs($manager)->get(route('vessel.certificates.show', $this->vessel->id))
            ->assertOk()->assertSee('+ Add Sub Cert')->assertDontSee('aria-controls="sub-list-'.$parent->id.'"', false);
        $this->actingAs($manager)->get(route('vessel-certificates.sub.create', $parent))->assertOk()->assertSee('Five Year Safety');
        $payload = ['vessel_id' => $this->vessel->id, 'certificate_name' => 'Annual Audit 2026', 'issue_date' => today()->subYear()->toDateString(), 'expiry_date' => today()->subDay()->toDateString(), 'document' => UploadedFile::fake()->create('audit.pdf', 50, 'application/pdf')];
        $this->post(route('vessel-certificates.sub.store', $parent), $payload, ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('message', 'Sub-certificate saved.');
        $child = $parent->subCertificates()->firstOrFail();
        $this->assertSame($expiry, $parent->fresh()->expiry_date->toDateString());
        Storage::disk('local')->assertExists($child->document);
        $this->assertDatabaseHas('vessel_certificate_audits', ['vessel_certificate_id' => $child->id, 'action' => 'sub_certificate_added']);
        $this->get(route('vessel.certificates.show', ['id' => $this->vessel->id, 'expand' => $parent->id]))->assertOk()->assertSee('Annual Audit 2026')->assertSee('days overdue')->assertSee('data-sub-list-toggle')->assertSee('Sub certificate')->assertSee('id="sub-certificate-modal"', false)->assertSee('data-sub-certificate-open', false);
        $this->getJson(route('vessel-certificates.calendar', ['month' => today()->subDay()->format('Y-m')]))->assertOk()->assertJsonPath('events.0.name', 'Annual Audit 2026 (under Five Year Safety)');
        $this->get(route('vessel-certificates.document', $child))->assertOk();
        $this->post(route('vessel-certificates.sub.store', $parent), $payload)->assertSessionHasErrors('certificate_name');
        $this->assertSame(1, $parent->subCertificates()->count());
        $this->get(route('vessel-certificates.sub.create', $child))->assertUnprocessable();
    }

    public function test_sub_certificate_permissions_validation_and_parent_history_are_enforced(): void
    {
        Storage::fake('local');
        $manager = $this->user('Operations Manager', 'operations-manager', ['shipping.certificates.access', 'certificates.manage']);
        $captain = $this->user('Vessel Captain', 'vessel-captain', ['shipping.certificates.access']);
        $this->assign($captain);
        $parent = VesselCertificate::create(['vessel_id' => $this->vessel->id, 'certificate_name' => 'Safety', 'issue_date' => today(), 'expiry_date' => today()->addYears(5), 'workflow_status' => 'active']);
        $this->actingAs($captain)->get(route('vessel-certificates.sub.create', $parent))->assertForbidden();
        $payload = ['vessel_id' => $this->vessel->id, 'certificate_name' => 'Audit', 'issue_date' => today()->toDateString(), 'expiry_date' => today()->addYear()->toDateString(), 'document' => UploadedFile::fake()->create('audit.pdf', 50, 'application/pdf')];
        $this->post(route('vessel-certificates.sub.store', $parent), $payload)->assertForbidden();
        $this->actingAs($manager)->post(route('vessel-certificates.sub.store', $parent), [...$payload, 'expiry_date' => today()->subDay()->toDateString()])->assertSessionHasErrors('expiry_date');
        $this->post(route('vessel-certificates.sub.store', $parent), [...$payload, 'document' => null])->assertSessionHasErrors('document');
        $child = VesselCertificate::create([...collect($payload)->except('document')->all(), 'parent_certificate_id' => $parent->id, 'workflow_status' => 'active']);
        $parent->update(['workflow_status' => 'superseded']);
        $this->post(route('vessel-certificates.sub.store', $parent), $payload)->assertUnprocessable();
        $this->get(route('vessel-certificates.edit', $child))->assertUnprocessable();
        $this->post(route('vessel-certificates.update', $child), $payload)->assertUnprocessable();
        $this->post(route('vessel-certificates.renew.store', $child), $payload)->assertUnprocessable();
        $this->assertFalse(VesselCertificate::effective()->whereKey($child->id)->exists());
    }

    public function test_sub_certificate_edit_with_empty_upload_updates_details_and_keeps_document(): void
    {
        Storage::fake('local');
        $manager = $this->user('Operations Manager', 'operations-manager', ['shipping.certificates.access', 'certificates.manage']);
        $parent = VesselCertificate::create(['vessel_id' => $this->vessel->id, 'certificate_name' => 'Safety', 'issue_date' => today(), 'expiry_date' => today()->addYears(5), 'workflow_status' => 'active']);
        $child = VesselCertificate::create(['vessel_id' => $this->vessel->id, 'parent_certificate_id' => $parent->id, 'certificate_name' => 'Annual Audit', 'issue_date' => today(), 'expiry_date' => today()->addYear(), 'workflow_status' => 'active', 'document' => 'certificates/original.pdf']);

        $this->actingAs($manager)->postJson(route('vessel-certificates.update', $child), [
            'vessel_id' => $this->vessel->id,
            'certificate_name' => 'Updated Annual Audit',
            'issue_date' => today()->toDateString(),
            'expiry_date' => today()->addMonths(10)->toDateString(),
            'remarks' => 'Edited without replacing the attachment',
            'document' => null,
        ])->assertOk();

        $this->assertSame('Updated Annual Audit', $child->fresh()->certificate_name);
        $this->assertSame('certificates/original.pdf', $child->fresh()->document);
        $this->assertSame($parent->id, $child->fresh()->parent_certificate_id);
        $this->assertSame(today()->addYears(5)->toDateString(), $parent->fresh()->expiry_date->toDateString());
    }

    public function test_sub_certificate_renewal_preserves_parent_and_history(): void
    {
        Storage::fake('local');
        $manager = $this->user('Operations Manager', 'operations-manager', ['shipping.certificates.access', 'certificates.manage']);
        $parent = VesselCertificate::create(['vessel_id' => $this->vessel->id, 'certificate_name' => 'Safety', 'issue_date' => today(), 'expiry_date' => today()->addYears(5), 'workflow_status' => 'active']);
        $child = VesselCertificate::create(['vessel_id' => $this->vessel->id, 'parent_certificate_id' => $parent->id, 'certificate_name' => 'Annual Audit', 'issue_date' => today(), 'expiry_date' => today()->addYear(), 'workflow_status' => 'active']);
        $this->actingAs($manager)->getJson(route('vessel-certificates.edit', $child))->assertOk()->assertJsonPath('fields.certificate_name', 'Annual Audit')->assertJsonPath('fields.issue_date', today()->toDateString())->assertJsonMissingPath('document');
        $this->post(route('vessel-certificates.update', $child), ['vessel_id' => $this->vessel->id, 'certificate_name' => 'Annual Audit', 'issue_date' => today()->toDateString(), 'expiry_date' => today()->addYear()->toDateString(), 'remarks' => 'Updated from popup'], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('message', 'Sub-certificate updated.');
        $this->assertSame('Updated from popup', $child->fresh()->remarks);
        $this->getJson(route('vessel-certificates.renew', $child))->assertOk()->assertJsonPath('suggested_issue_date', today()->addYear()->addDay()->toDateString());
        $this->post(route('vessel-certificates.renew.store', $child), ['vessel_id' => $this->vessel->id, 'certificate_name' => 'Annual Audit', 'issue_date' => today()->addYear()->toDateString(), 'expiry_date' => today()->addYears(2)->toDateString(), 'document' => UploadedFile::fake()->create('renewal.pdf', 50, 'application/pdf')], ['Accept' => 'application/json'])->assertCreated();
        $renewal = VesselCertificate::where('previous_certificate_id', $child->id)->firstOrFail();
        $this->assertSame($parent->id, $renewal->parent_certificate_id);
        $this->assertSame('superseded', $child->fresh()->workflow_status);
        $this->assertSame(2, $parent->subCertificates()->count());
        $this->assertSame('active', $parent->fresh()->workflow_status);
    }

    private function user(string $name, string $code, array $permissionSlugs): User
    {
        $position = Position::create(['division_id' => $this->company->id, 'department_id' => $this->department->id, 'name' => $name, 'code' => $code, 'legacy_role' => str_contains($code, 'manager') ? 'manager' : 'staff', 'is_active' => true]);
        $position->permissions()->sync(Permission::whereIn('slug', $permissionSlugs)->pluck('id'));
        static $number = 0;
        $number++;
        return User::create([
            'name' => $name, 'lastname' => 'User', 'username' => 'certificate-user-'.$number,
            'email' => 'certificate-user-'.$number.'@example.test', 'cell_number' => '09170000000',
            'password' => Hash::make('password-for-tests'), 'role' => str_contains($code, 'manager') ? 'manager' : 'staff',
            'division_id' => $this->company->id, 'department_id' => $this->department->id, 'position_id' => $position->id,
            'access_role_id' => AccessRole::where('slug', str_contains($code, 'manager') ? 'company-approver' : 'standard-user')->value('id'),
            'must_change_password' => false, 'status' => true,
        ]);
    }

    private function assign(User $user): void
    {
        UserVesselAssignment::create(['user_id' => $user->id, 'vessel_id' => $this->vessel->id, 'assigned_by' => $user->id, 'is_active' => true, 'effective_from' => today()]);
    }
}
