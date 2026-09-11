<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tech_defects')->orderBy('id')->each(function (object $report): void {
            if (DB::table('tech_defect_audits')->where('tech_defect_id', $report->id)->exists()) {
                return;
            }

            DB::table('tech_defect_audits')->insert([
                'tech_defect_id' => $report->id,
                'user_id' => null,
                'action' => 'legacy_imported',
                'from_status' => null,
                'to_status' => $report->status,
                'changes' => null,
                'description' => 'Existing report registered as the audit baseline during production hardening.',
                'created_at' => $report->created_at ?? now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('tech_defect_audits')->where('action', 'legacy_imported')->delete();
    }
};
