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
