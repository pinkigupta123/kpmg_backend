<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CompanySeeder::class,
            BranchSeeder::class,
            EmployeeSeeder::class,
            CustomerSeeder::class,
            DeviceSeeder::class,
            ServiceReportSeeder::class,
            StockItemSeeder::class,
            StockLedgerSeeder::class,
            TransferSeeder::class,
            SettingsSeeder::class,
            OptionSeeder::class,
            // ── Lead Management Module ──
            LeadDatabaseSeeder::class,
        ]);
    }
}
