<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::create([
            'id' => 1,
            'name' => 'HQ - TechFix Central',
            'code' => 'HTC',
            'address' => '123 Tech Park',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'state_code' => '27',
            'pincode' => '400001',
            'phone' => '022-12345678',
            'email' => 'hq@techfix.com',
            'gstin' => '27AABCT1234A1Z5',
            'job_sequence' => 10000,
            'is_active' => true,
        ]);
    }
}
