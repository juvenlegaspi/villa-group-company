<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yatira_fixed_assets', function (Blueprint $table) {
            $table->dropUnique('yatira_fixed_assets_asset_code_unique');
            $table->unique(['division_id', 'asset_code'], 'company_fixed_asset_code_unique');
        });
        app(App\Services\CompanyFixedAssetPermissions::class)->sync();
    }

    public function down(): void
    {
        if (DB::table('yatira_fixed_assets')->select('asset_code')->groupBy('asset_code')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Company asset codes overlap. Resolve duplicates before restoring a global constraint.');
        }
        Schema::table('yatira_fixed_assets', function (Blueprint $table) {
            $table->dropUnique('company_fixed_asset_code_unique');
            $table->unique('asset_code');
        });
    }
};
