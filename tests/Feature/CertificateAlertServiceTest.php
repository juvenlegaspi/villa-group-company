<?php

namespace Tests\Feature;

use App\Mail\CertificateExpiryNotification;
use App\Models\AccessRole;
use App\Models\Department;
use App\Models\Division;
use App\Models\Permission;
use App\Models\Position;
use App\Models\User;
use App\Models\Vessel;
use App\Models\VesselCertificate;
use App\Services\CertificateAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CertificateAlertServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_certificate_sends_email_and_sms_once_per_recipient_per_day(): void
    {
        Mail::fake();
        Http::fake(['api.semaphore.co/*' => Http::response([[
            'message_id' => 12345, 'status' => 'Queued', 'recipient' => '639170000001',
        ]])]);
        config()->set('services.semaphore.enabled', true);
        config()->set('services.semaphore.api_key', 'test-api-key');
        config()->set('services.semaphore.sender_name', null);

        [$manager, $liaison, $captain, $vessel] = $this->organization();
        $certificate = VesselCertificate::create([
            'vessel_id' => $vessel->id, 'certificate_name' => 'Safety Certificate',
            'issue_date' => today()->subYear(), 'expiry_date' => today()->addDays(30), 'workflow_status' => 'active',
        ]);

        $service = app(CertificateAlertService::class);
        $summary = $service->sendExpiringCertificateAlerts();
        $this->assertSame(2, $summary['email_sent']);
        $this->assertSame(2, $summary['sms_sent']);
        $this->assertCount(2, $service->getRecipientsForCertificate($certificate->load('vessel.captain')));
        Mail::assertSent(CertificateExpiryNotification::class, 2);
        Mail::assertNotSent(CertificateExpiryNotification::class, fn ($mail) => $mail->hasTo($captain->email));
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.semaphore.co/api/v4/messages'
            && $request['apikey'] === 'test-api-key'
            && str_starts_with($request['number'], '63')
            && ! isset($request['sendername']));

        $secondRun = $service->sendExpiringCertificateAlerts();
        $this->assertSame(0, $secondRun['email_sent']);
        $this->assertSame(0, $secondRun['sms_sent']);
        $this->assertDatabaseCount('certificate_alert_logs', 2);
        $this->assertDatabaseCount('certificate_sms_alert_logs', 2);
    }

    public function test_non_threshold_day_does_not_send_and_disabled_sms_does_not_block_email(): void
    {
        Mail::fake();
        Http::fake();
        [$manager, $liaison, $captain, $vessel] = $this->organization();
        VesselCertificate::create([
            'vessel_id' => $vessel->id, 'certificate_name' => 'Class Certificate',
            'issue_date' => today()->subYear(), 'expiry_date' => today()->addDays(29), 'workflow_status' => 'active',
        ]);
        $summary = app(CertificateAlertService::class)->sendExpiringCertificateAlerts();
        $this->assertSame(0, $summary['email_sent']);
        $this->assertSame(0, $summary['sms_sent']);
        Mail::assertNothingSent();
        Http::assertNothingSent();

        VesselCertificate::query()->update(['expiry_date' => today()->addDays(14)]);
        config()->set('services.semaphore.enabled', false);
        $summary = app(CertificateAlertService::class)->sendExpiringCertificateAlerts();
        $this->assertSame(2, $summary['email_sent']);
        $this->assertSame(0, $summary['sms_sent']);
        $this->assertSame(2, $summary['sms_skipped_unconfigured']);
    }

    private function organization(): array
    {
        $company = Division::create(['name' => 'Villa shipping Lines']);
        $department = Department::create(['division_id' => $company->id, 'name' => 'Marine Operations']);
        $managerPosition = Position::create(['division_id' => $company->id, 'department_id' => $department->id, 'name' => 'Operations Manager', 'code' => 'operations-manager', 'legacy_role' => 'manager', 'is_active' => true]);
        $captainPosition = Position::create(['division_id' => $company->id, 'department_id' => $department->id, 'name' => 'Vessel Captain', 'code' => 'vessel-captain', 'legacy_role' => 'captain', 'is_active' => true]);
        $liaisonPosition = Position::create(['division_id' => $company->id, 'department_id' => $department->id, 'name' => 'Liaison Officer', 'code' => 'liaison-officer', 'legacy_role' => 'staff', 'is_active' => true]);
        $managerPosition->permissions()->sync(Permission::whereIn('slug', ['shipping.certificates.access', 'certificates.manage'])->pluck('id'));
        $liaisonPosition->permissions()->sync(Permission::whereIn('slug', ['shipping.certificates.access', 'certificates.manage'])->pluck('id'));
        $captainPosition->permissions()->sync(Permission::where('slug', 'shipping.certificates.access')->pluck('id'));
        $manager = $this->user($company, $department, $managerPosition, 'Alert Manager', 'manager@example.test', '09170000001', 'company-approver');
        $liaison = $this->user($company, $department, $liaisonPosition, 'Alert Liaison', 'liaison@example.test', '09170000002', 'standard-user');
        $captain = $this->user($company, $department, $captainPosition, 'Alert Captain', 'captain@example.test', '09170000003', 'standard-user');
        $vessel = Vessel::create(['vessel_name' => 'MV Alert Test', 'captain_id' => $captain->id]);

        return [$manager, $liaison, $captain, $vessel];
    }

    private function user(Division $company, Department $department, Position $position, string $name, string $email, string $phone, string $role): User
    {
        return User::create([
            'name' => $name, 'lastname' => 'User', 'username' => str($name)->slug().'-user', 'email' => $email,
            'cell_number' => $phone, 'password' => Hash::make('password-for-tests'), 'role' => $role === 'company-approver' ? 'manager' : 'captain',
            'division_id' => $company->id, 'department_id' => $department->id, 'position_id' => $position->id,
            'access_role_id' => AccessRole::where('slug', $role)->value('id'), 'must_change_password' => false, 'status' => true,
        ]);
    }
}
