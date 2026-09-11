<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{
  Schema::create('jmv_stock_requests',function(Blueprint $t):void{$t->id();$t->foreignId('division_id')->constrained('divisions')->restrictOnDelete();$t->string('request_no',50)->unique();$t->foreignId('requested_by')->constrained('users')->restrictOnDelete();$t->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();$t->string('status',30)->default('PENDING');$t->string('purpose',1000);$t->text('approval_remarks')->nullable();$t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamp('approved_at')->nullable();$t->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamp('released_at')->nullable();$t->timestamps();$t->index(['division_id','status']);});
  Schema::create('jmv_stock_request_items',function(Blueprint $t):void{$t->id();$t->foreignId('jmv_stock_request_id')->constrained('jmv_stock_requests')->cascadeOnDelete();$t->foreignId('item_inventory_header_id')->constrained('item_inventory_header')->restrictOnDelete();$t->foreignId('location_id')->constrained('jmv_inventory_locations')->restrictOnDelete();$t->unsignedInteger('quantity');$t->unsignedInteger('approved_quantity')->nullable();$t->foreignId('item_inventory_transaction_id')->nullable()->constrained('item_inventory_transactions')->restrictOnDelete();$t->timestamps();});
 }
 public function down():void{Schema::dropIfExists('jmv_stock_request_items');Schema::dropIfExists('jmv_stock_requests');}
};
