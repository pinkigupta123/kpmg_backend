<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Base setup (companies, branches, options, all users) comes from ProductionSeeder, so general/dev and
     * production share one source of truth. Everything after it is demo data for general/dev only.
     */
    public function run(): void
    {
        $this->call([
            ProductionSeeder::class,
            // ── Demo data (general/dev only) ──
            CustomerSeeder::class,
            DeviceSeeder::class,
            ServiceReportSeeder::class,
            StockItemSeeder::class,
            StockLedgerSeeder::class,
            TransferSeeder::class,
        ]);
    }
}
