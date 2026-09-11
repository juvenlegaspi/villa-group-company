<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tech_defects', function (Blueprint $table): void {
            $table->foreignId('reported_by_user_id')->nullable()->after('reported_by')
                ->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->index('status');
            $table->index('date_identified');
            $table->index('date_completed');
            $table->index('severity_level');
        });

        Schema::table('third_party_supports', function (Blueprint $table): void {
            $table->foreignId('created_by')->nullable()->after('status')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->after('created_by')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable()->after('completed_by');
            $table->index('status');
        });

        Schema::create('tech_defect_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tech_defect_id')->constrained('tech_defects')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->json('changes')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['tech_defect_id', 'created_at']);
        });

        // Preserve the historical name snapshot and link unambiguous existing
        // records to a user when a unique full-name match is available.
        $usersByName = DB::table('users')->get(['id', 'name', 'lastname'])
            ->groupBy(fn (object $user) => mb_strtoupper(trim($user->name.' '.$user->lastname)));

        DB::table('tech_defects')->whereNotNull('reported_by')->orderBy('id')->each(function (object $report) use ($usersByName): void {
            $normalized = mb_strtoupper(trim((string) $report->reported_by));
            $matches = $usersByName->get($normalized, collect());

            if ($matches->count() === 1) {
                DB::table('tech_defects')->where('id', $report->id)
                    ->update(['reported_by_user_id' => $matches->first()->id]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tech_defect_audits');

        Schema::table('third_party_supports', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropConstrainedForeignId('completed_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('completed_at');
        });

        Schema::table('tech_defects', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropIndex(['date_identified']);
            $table->dropIndex(['date_completed']);
            $table->dropIndex(['severity_level']);
            $table->dropConstrainedForeignId('reported_by_user_id');
            $table->dropSoftDeletes();
        });
    }
};
