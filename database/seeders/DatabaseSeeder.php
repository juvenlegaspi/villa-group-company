<?php

namespace Database\Seeders;

//use App\Models\User;
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
        // Divisions
    DB::table('divisions')->insert([
        ['name' => 'Yatira'],
        ['name' => 'Shipping Lines'],
        ['name' => 'JMV'],
    ]);

    // Departments (NEW)
    DB::table('new_departments')->insert([
        ['name' => 'IT'],
        ['name' => 'R&D'],
        ['name' => 'Operation'],
        ['name' => 'HR'],
        ['name' => 'Accounting'],
        ['name' => 'Shipping'],
    ]);
    }
}
