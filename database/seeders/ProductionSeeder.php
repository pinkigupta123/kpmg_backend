<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeadUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Production seeder: base company + branch, dropdown options, and one user per role. Safe to re-run.
 *
 *   php artisan db:seed --class=ProductionSeeder --force
 *
 * Every user starts with the same password and must change it on first login.
 * After the first login, open Companies / Branches and replace the placeholder details (name, initials, address,
 * GSTIN) - they print on job sheets and invoices, and the initials form the job sheet number (e.g. "KPNG 10001 SA").
 */
class ProductionSeeder extends Seeder
{
    private const PASSWORD = 'ChangeMe@123';

    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['code' => 'KPNG'],
            [
                'name' => 'KPNG Technologies', 'address' => 'Update me', 'city' => 'Update me',
                'state' => 'Update me', 'pincode' => '000000', 'phone' => '0000000000',
                'job_sequence' => 10000, 'is_active' => true,
            ]
        );

        $branch = Branch::firstOrCreate(
            ['code' => 'BR-001'],
            [
                'company_id' => $company->id,
                'name' => 'Head Office', 'address' => 'Update me', 'city' => 'Update me',
                'state' => 'Update me', 'pincode' => '000000', 'phone' => '0000000000',
                'is_active' => true,
            ]
        );

        // Dropdown values used by the job sheet and inventory forms (categories, call types, ...)
        $this->call(OptionSeeder::class);

        // employees table: [role, code, mobile, email, name]
        $employees = [
            ['super_admin',    'EMP-001', '9000000001', 'admin@kpng.com',   'Super Admin'],
            ['branch_manager', 'EMP-002', '9000000002', 'manager@kpng.com', 'Branch Manager'],
            ['stock_manager',  'EMP-003', '9000000003', 'stock@kpng.com',   'Stock Manager'],
            ['employee',       'EMP-004', '9000000004', 'employee@kpng.com', 'Employee'],
        ];

        foreach ($employees as [$role, $code, $mobile, $email, $name]) {
            Employee::firstOrCreate(['code' => $code], [
                'company_id' => $company->id,
                'name' => $name, 'mobile' => $mobile, 'email' => $email,
                'password' => Hash::make(self::PASSWORD),
                'designation' => $name, 'branch_id' => $branch->id, 'branch_name' => $branch->name,
                'role' => $role, 'must_change_password' => true, 'is_first_login' => true,
                'joined_at' => now(),
            ]);
        }
        $branch->update(['total_employees' => count($employees)]);

        // lead_users table: [role, employee_id, mobile, email, name]
        $leadUsers = [
            ['super_admin',     'LEAD0001', '9000000011', 'leadadmin@kpng.com', 'Lead Super Admin'],
            ['sales_executive', 'LEAD0002', '9000000012', 'sales@kpng.com',     'Sales Executive'],
        ];

        foreach ($leadUsers as [$role, $empId, $mobile, $email, $name]) {
            LeadUser::firstOrCreate(['employee_id' => $empId], [
                'name' => $name, 'mobile' => $mobile, 'email' => $email,
                'password' => self::PASSWORD, // hashed automatically by the model
                'role' => $role, 'branch' => $branch->name, 'designation' => $name,
                'status' => 'active', 'is_temp_password' => true,
            ]);
        }
    }
}
