<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ports')) {
            Schema::create('ports', function (Blueprint $table): void {
                $table->id();
                $table->string('port_name');
                $table->string('province')->nullable();
                $table->string('status')->default('ACTIVE');
                $table->timestamps();
            });
        }

        $this->addColumnIfMissing('users', 'department_id', fn (Blueprint $table) => $table->unsignedBigInteger('department_id')->nullable());
        $this->addColumnIfMissing('vessels', 'captain_id', fn (Blueprint $table) => $table->unsignedBigInteger('captain_id')->nullable());

        $headerColumns = [
            'port_id' => fn (Blueprint $table) => $table->unsignedBigInteger('port_id')->nullable(),
            'port_destination' => fn (Blueprint $table) => $table->string('port_destination')->nullable(),
            'port_destination_id' => fn (Blueprint $table) => $table->unsignedBigInteger('port_destination_id')->nullable(),
            'current_location' => fn (Blueprint $table) => $table->string('current_location')->nullable(),
            'current_location_id' => fn (Blueprint $table) => $table->unsignedBigInteger('current_location_id')->nullable(),
            'date_completed' => fn (Blueprint $table) => $table->date('date_completed')->nullable(),
            'total_hours_voyage' => fn (Blueprint $table) => $table->decimal('total_hours_voyage', 10, 2)->nullable(),
        ];
        foreach ($headerColumns as $column => $definition) {
            $this->addColumnIfMissing('voyage_logs_header', $column, $definition);
        }

        $this->addColumnIfMissing('voyage_logs_details', 'vessel_id', fn (Blueprint $table) => $table->unsignedBigInteger('vessel_id')->nullable());
        $this->addColumnIfMissing('voyage_logs_details', 'main_status', fn (Blueprint $table) => $table->string('main_status')->default('ONGOING'));

        if (Schema::hasTable('voyage_activities') && Schema::hasColumn('voyage_activities', 'id') && ! Schema::hasColumn('voyage_activities', 'activity_id')) {
            Schema::table('voyage_activities', fn (Blueprint $table) => $table->renameColumn('id', 'activity_id'));
        }

        $activityColumns = [
            'voyage_id' => fn (Blueprint $table) => $table->unsignedBigInteger('voyage_id')->nullable(),
            'vessel_id' => fn (Blueprint $table) => $table->unsignedBigInteger('vessel_id')->nullable(),
            'status_id' => fn (Blueprint $table) => $table->unsignedBigInteger('status_id')->nullable(),
            'status_activity_id' => fn (Blueprint $table) => $table->unsignedBigInteger('status_activity_id')->nullable(),
            'remarks' => fn (Blueprint $table) => $table->text('remarks')->nullable(),
            'total_hours' => fn (Blueprint $table) => $table->decimal('total_hours', 10, 2)->nullable(),
            'cargo_load' => fn (Blueprint $table) => $table->decimal('cargo_load', 12, 2)->nullable(),
            'total_load' => fn (Blueprint $table) => $table->decimal('total_load', 12, 2)->nullable(),
            'cargo_unload' => fn (Blueprint $table) => $table->decimal('cargo_unload', 12, 2)->nullable(),
            'total_unload' => fn (Blueprint $table) => $table->decimal('total_unload', 12, 2)->nullable(),
            'load_unit' => fn (Blueprint $table) => $table->string('load_unit', 20)->nullable(),
            'main_status' => fn (Blueprint $table) => $table->string('main_status')->default('ONGOING'),
            'edited_end_date_time' => fn (Blueprint $table) => $table->dateTime('edited_end_date_time')->nullable(),
            'edit_reason' => fn (Blueprint $table) => $table->text('edit_reason')->nullable(),
            'edit_attachment' => fn (Blueprint $table) => $table->string('edit_attachment')->nullable(),
            'edited_at' => fn (Blueprint $table) => $table->dateTime('edited_at')->nullable(),
        ];
        foreach ($activityColumns as $column => $definition) {
            $this->addColumnIfMissing('voyage_activities', $column, $definition);
        }

        $this->addColumnIfMissing('fuel_rob_monitorings', 'boiler', fn (Blueprint $table) => $table->decimal('boiler', 10, 2)->default(0));
        $this->addColumnIfMissing('suppliers', 'added_by', fn (Blueprint $table) => $table->unsignedBigInteger('added_by')->nullable());
        $this->addColumnIfMissing('suppliers', 'status', fn (Blueprint $table) => $table->boolean('status')->default(true));
    }

    public function down(): void
    {
        // Compatibility columns are intentionally retained to avoid destructive rollbacks.
    }

    private function addColumnIfMissing(string $tableName, string $column, callable $definition): void
    {
        if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, $column)) {
            Schema::table($tableName, function (Blueprint $table) use ($definition): void {
                $definition($table);
            });
        }
    }
};
