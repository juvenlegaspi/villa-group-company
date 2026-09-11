<?php

namespace Database\Seeders;

// use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (['Yatira', 'Villa shipping Lines', 'JMV', 'Villa Group', 'HYVE'] as $division) {
            DB::table('divisions')->updateOrInsert(
                ['name' => $division],
                ['updated_at' => now(), 'created_at' => now()]
            );
        }

        foreach (['IT', 'R & D', 'Operation', 'HR', 'Accounting', 'Shipping', 'Purchasing'] as $department) {
            DB::table('departments')->updateOrInsert(
                ['name' => $department],
                ['updated_at' => now(), 'created_at' => now()]
            );
        }

        $this->call(OrganizationAccessSeeder::class);
    }
}
