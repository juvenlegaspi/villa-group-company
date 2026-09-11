<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vessel_certificates', function (Blueprint $table): void {
            $table->string('workflow_status', 30)->default('active')->after('document');
            $table->foreignId('previous_certificate_id')->nullable()->after('workflow_status')
                ->constrained('vessel_certificates')->nullOnDelete();
            $table->string('document_original_name')->nullable()->after('previous_certificate_id');
            $table->string('document_mime_type', 120)->nullable()->after('document_original_name');
            $table->unsignedBigInteger('document_size')->nullable()->after('document_mime_type');
            $table->foreignId('created_by')->nullable()->after('document_size')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->index(['vessel_id', 'workflow_status', 'expiry_date'], 'certificate_vessel_status_expiry_index');
            $table->index(['vessel_id', 'certificate_name'], 'certificate_vessel_name_index');
        });

        Schema::create('vessel_certificate_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vessel_certificate_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->boolean('is_current')->default(true);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['vessel_certificate_id', 'is_current'], 'certificate_document_current_index');
        });

        Schema::create('vessel_certificate_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vessel_certificate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['vessel_certificate_id', 'created_at'], 'certificate_audit_timeline_index');
        });

        DB::table('vessel_certificates')
            ->whereNotNull('document')
            ->orderBy('id')
            ->each(function (object $certificate): void {
                DB::table('vessel_certificate_documents')->insert([
                    'vessel_certificate_id' => $certificate->id,
                    'path' => $certificate->document,
                    'original_name' => basename((string) $certificate->document),
                    'is_current' => true,
                    'created_at' => $certificate->updated_at ?? now(),
                    'updated_at' => $certificate->updated_at ?? now(),
                ]);
            });

        $permissions = [
            'certificates.manage' => 'Create, update, and renew vessel certificates',
            'certificates.approve' => 'Approve certificate compliance records and receive escalations',
        ];
        foreach ($permissions as $slug => $name) {
            DB::table('permissions')->updateOrInsert(['slug' => $slug], [
                'name' => $name,
                'module' => 'certificates',
                'description' => $name.'.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $permissionIds = DB::table('permissions')->whereIn('slug', array_keys($permissions))->pluck('id', 'slug');
        foreach (['system-administrator', 'company-administrator'] as $roleSlug) {
            $roleId = DB::table('access_roles')->where('slug', $roleSlug)->value('id');
            if (! $roleId) continue;
            foreach ($permissionIds as $permissionId) {
                DB::table('access_role_permission')->insertOrIgnore(['access_role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }

        $positionGrants = [
            'operations-manager' => ['certificates.manage', 'certificates.approve'],
            'operation-manager' => ['certificates.manage', 'certificates.approve'],
            'marine-operations-manager' => ['certificates.manage', 'certificates.approve'],
            'vessel-manager' => ['certificates.manage', 'certificates.approve'],
            'technical-manager' => ['certificates.manage'],
            'safety-manager' => ['certificates.manage', 'certificates.approve'],
            'safety-officer' => ['certificates.manage'],
        ];
        foreach ($positionGrants as $code => $slugs) {
            foreach (DB::table('positions')->where('code', $code)->pluck('id') as $positionId) {
                foreach ($slugs as $slug) {
                    if (isset($permissionIds[$slug])) {
                        DB::table('permission_position')->insertOrIgnore([
                            'position_id' => $positionId,
                            'permission_id' => $permissionIds[$slug],
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('slug', ['certificates.manage', 'certificates.approve'])->pluck('id');
        DB::table('access_role_permission')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permission_position')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::dropIfExists('vessel_certificate_audits');
        Schema::dropIfExists('vessel_certificate_documents');
        Schema::table('vessel_certificates', function (Blueprint $table): void {
            $table->dropIndex('certificate_vessel_status_expiry_index');
            $table->dropIndex('certificate_vessel_name_index');
            $table->dropConstrainedForeignId('previous_certificate_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['workflow_status', 'document_original_name', 'document_mime_type', 'document_size']);
        });
    }
};
