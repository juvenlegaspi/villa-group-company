<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('haruhay_collections', function (Blueprint $table) {
            $table->foreignId('property_id')->nullable()->constrained('haruhay_properties')->restrictOnDelete();
        });
        DB::table('haruhay_collections')->orderBy('id')->chunkById(100, function ($receipts) {
            foreach ($receipts as $receipt) {
                $propertyId = $receipt->initial_billing_id ? DB::table('haruhay_billings')->join('haruhay_units', 'haruhay_units.id', '=', 'haruhay_billings.unit_id')->where('haruhay_billings.id', $receipt->initial_billing_id)->value('haruhay_units.property_id') : null;
                if (!$propertyId) {
                    $matching = DB::table('haruhay_properties')->where('name', $receipt->property_name)->pluck('id');
                    $propertyId = $matching->count() === 1 ? $matching->first() : DB::table('haruhay_tenants')->join('haruhay_units', 'haruhay_units.id', '=', 'haruhay_tenants.unit_id')->where('haruhay_tenants.id', $receipt->tenant_id)->value('haruhay_units.property_id');
                }
                DB::table('haruhay_collections')->where('id', $receipt->id)->update(['property_id' => $propertyId]);
            }
        });
    }
    public function down(): void
    {
        Schema::table('haruhay_collections', function (Blueprint $table) {
            $table->dropForeign(['property_id']);
            $table->dropColumn('property_id');
        });
    }
};
