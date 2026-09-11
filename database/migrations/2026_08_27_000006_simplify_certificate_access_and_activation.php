<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $shippingId = DB::table('divisions')->whereRaw('LOWER(name) = ?', ['villa shipping lines'])->value('id');
        $marineOperationsId = $shippingId
            ? DB::table('departments')->where('division_id', $shippingId)->whereRaw('LOWER(name) = ?', ['marine operations'])->value('id')
            : null;

        if ($shippingId && $marineOperationsId) {
            $liaisonId = DB::table('positions')->where([
                'division_id' => $shippingId,
                'department_id' => $marineOperationsId,
                'code' => 'liaison-officer',
            ])->value('id');
            if (! $liaisonId) {
                $liaisonId = DB::table('positions')->insertGetId([
                    'division_id' => $shippingId,
                    'department_id' => $marineOperationsId,
                    'name' => 'Liaison Officer',
                    'code' => 'liaison-officer',
                    'legacy_role' => 'staff',
                    'description' => 'Maintains vessel certificates and regulatory liaison records.',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $manageId = DB::table('permissions')->where('slug', 'certificates.manage')->value('id');
            $approveId = DB::table('permissions')->where('slug', 'certificates.approve')->value('id');
            $moduleId = DB::table('permissions')->where('slug', 'shipping.certificates.access')->value('id');
            $shippingPositionIds = DB::table('positions')->where('division_id', $shippingId)->pluck('id');

            if ($manageId) DB::table('permission_position')->whereIn('position_id', $shippingPositionIds)->where('permission_id', $manageId)->delete();
            if ($approveId) DB::table('permission_position')->whereIn('position_id', $shippingPositionIds)->where('permission_id', $approveId)->delete();
            if ($approveId) DB::table('access_role_permission')->where('permission_id', $approveId)->delete();

            $managerIds = DB::table('positions')
                ->where('division_id', $shippingId)
                ->where('department_id', $marineOperationsId)
                ->whereIn('code', ['operations-manager', 'operation-manager', 'marine-operations-manager', 'liaison-officer'])
                ->pluck('id');
            foreach ($managerIds as $positionId) {
                foreach (array_filter([$moduleId, $manageId]) as $permissionId) {
                    DB::table('permission_position')->insertOrIgnore(['position_id' => $positionId, 'permission_id' => $permissionId]);
                }
            }
        }

        $pending = DB::table('vessel_certificates')->whereIn('workflow_status', ['draft', 'pending_approval'])->orderBy('id')->get();
        $renewals = $pending->whereNotNull('previous_certificate_id')->groupBy('previous_certificate_id');
        foreach ($renewals as $previousId => $candidates) {
            $selected = $candidates->sortByDesc('id')->first();
            DB::table('vessel_certificates')->where('id', $previousId)->update(['workflow_status' => 'superseded', 'updated_at' => now()]);
            DB::table('vessel_certificates')->whereIn('id', $candidates->pluck('id'))->update(['workflow_status' => 'superseded', 'updated_at' => now()]);
            DB::table('vessel_certificates')->where('id', $selected->id)->update(['workflow_status' => 'active', 'updated_at' => now()]);
        }
        $regularIds = $pending->whereNull('previous_certificate_id')->pluck('id');
        if ($regularIds->isNotEmpty()) {
            DB::table('vessel_certificates')->whereIn('id', $regularIds)->update(['workflow_status' => 'active', 'updated_at' => now()]);
        }

        foreach ($pending as $certificate) {
            $resultingStatus = DB::table('vessel_certificates')->where('id', $certificate->id)->value('workflow_status');
            DB::table('vessel_certificate_audits')->insert([
                'vessel_certificate_id' => $certificate->id,
                'user_id' => null,
                'action' => 'workflow_simplified',
                'changes' => json_encode(['previous_status' => $certificate->workflow_status, 'resulting_status' => $resultingStatus], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Workflow activation is intentionally not reversed because doing so
        // would guess at business state and could invalidate live records.
    }
};
