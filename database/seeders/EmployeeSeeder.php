<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin — matches login demo: kavya@techfix.in / Admin@123
        Employee::create([
            'company_id' => 1,
            'name' => 'Kavya Sharma',
            'code' => 'EMP-001',
            'mobile' => '9900001111',
            'email' => 'kavya@techfix.in',
            'password' => Hash::make('Admin@123'),
            'designation' => 'System Administrator',
            'branch_id' => 1,
            'branch_name' => 'HQ - TechFix Central',
            'role' => 'super_admin',
            'must_change_password' => false,
            'is_first_login' => false,
            'joined_at' => now(),
        ]);

        // Branch Manager — matches login demo: 9900003333 / Manager@123
        Employee::create([
            'company_id' => 1,
            'name' => 'Rajesh Kumar',
            'code' => 'EMP-002',
            'mobile' => '9900003333',
            'email' => 'rajesh@techfix.in',
            'password' => Hash::make('Manager@123'),
            'designation' => 'Branch Manager',
            'branch_id' => 1,
            'branch_name' => 'HQ - TechFix Central',
            'role' => 'branch_manager',
            'must_change_password' => false,
            'is_first_login' => false,
            'joined_at' => now(),
        ]);

        // Technician / Employee — matches login demo: 9900004444 / Tech@123
        Employee::create([
            'company_id' => 1,
            'name' => 'Amit Technician',
            'code' => 'EMP-003',
            'mobile' => '9900004444',
            'email' => 'amit@techfix.in',
            'password' => Hash::make('Tech@123'),
            'designation' => 'Senior Technician',
            'branch_id' => 1,
            'branch_name' => 'HQ - TechFix Central',
            'role' => 'employee',
            'must_change_password' => false,
            'is_first_login' => false,
            'joined_at' => now(),
        ]);

        // Stock Manager — matches login demo: 9900017777 / Stock@123
        Employee::create([
            'company_id' => 1,
            'name' => 'Neha Stock Manager',
            'code' => 'EMP-004',
            'mobile' => '9900017777',
            'email' => 'neha@techfix.in',
            'password' => Hash::make('Stock@123'),
            'designation' => 'Stock Manager',
            'branch_id' => 1,
            'branch_name' => 'HQ - TechFix Central',
            'role' => 'stock_manager',
            'must_change_password' => false,
            'is_first_login' => false,
            'joined_at' => now(),
        ]);

        // Extra employee on second branch
        Employee::create([
            'company_id' => 1,
            'name' => 'Suresh Delhi Tech',
            'code' => 'EMP-005',
            'mobile' => '9900005555',
            'email' => 'suresh@techfix.in',
            'password' => Hash::make('Tech@123'),
            'designation' => 'Technician',
            'branch_id' => 2,
            'branch_name' => 'North Branch',
            'role' => 'employee',
            'must_change_password' => false,
            'is_first_login' => false,
            'joined_at' => now(),
        ]);

        // Demo: a technician who works in two branches (gets a branch choice when creating a job sheet)
        Employee::where('code', 'EMP-003')->first()?->branches()->syncWithoutDetaching([2]);
    }
}
