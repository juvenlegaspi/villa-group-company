<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(App\Services\CompanyFixedAssetPermissions::class)->sync();
    }

    public function down(): void
    {
        // Preserve permissions that may also have been assigned manually.
    }
};
