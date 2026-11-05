<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Branch;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        Branch::create([
            'id' => 1,
            'company_id' => 1,
            'name' => 'HQ - TechFix Central',
            'code' => 'BR-001',
            'address' => '123 Tech Park',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
            'phone' => '022-12345678',
            'email' => 'hq@techfix.com',
            'is_active' => true,
            'total_employees' => 4,
        ]);

        Branch::create([
            'id' => 2,
            'company_id' => 1,
            'name' => 'North Branch',
            'code' => 'BR-002',
            'address' => '45 North Ave',
            'city' => 'Delhi',
            'state' => 'Delhi',
            'pincode' => '110001',
            'phone' => '011-12345678',
            'email' => 'north@techfix.com',
            'is_active' => true,
            'total_employees' => 1,
        ]);
    }
}
