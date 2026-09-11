<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('vessel_certificates')->orderBy('id')->each(function (object $certificate): void {
            if (DB::table('vessel_certificate_audits')->where('vessel_certificate_id', $certificate->id)->exists()) return;
            DB::table('vessel_certificate_audits')->insert([
                'vessel_certificate_id' => $certificate->id,
                'user_id' => $certificate->created_by ?? null,
                'action' => 'legacy_record_registered',
                'changes' => json_encode(['source' => 'pre-workflow certificate record'], JSON_THROW_ON_ERROR),
                'created_at' => $certificate->created_at ?? now(),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('vessel_certificate_audits')->where('action', 'legacy_record_registered')->delete();
    }
};
