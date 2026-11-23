<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeadUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Single base / fresh-setup seeder (used by production AND general/dev, via DatabaseSeeder).
 *
 *   php artisan migrate:fresh --force && php artisan db:seed --class=ProductionSeeder --force
 *
 * Creates: 2 companies x 2 branches, dropdown options, one user per role (employees + lead users).
 *   - stock manager  -> all 4 branches (both companies)
 *   - technician     -> one branch in each company
 * Create-only (firstOrCreate): safe to re-run, never resets existing passwords or counters.
 * Every user starts with the same password and must change it on first login.
 * Company / branch details are placeholders - edit them under Companies / Branches after first login.
 */
class ProductionSeeder extends Seeder
{
    private const PASSWORD = 'ChangeMe@123';

    public function run(): void
    {
        // ── Companies ──
        $companies = [];
        foreach ([
            'KPNG' => ['KPNG Technologies', 'Karol Bagh, New Delhi', '110005'],
            'HPS'  => ['HP Showroom Pvt Ltd', 'Nehru Place, New Delhi', '110019'],
        ] as $code => [$name, $address, $pin]) {
            $companies[$code] = Company::firstOrCreate(['code' => $code], [
                'name' => $name, 'address' => $address, 'city' => 'New Delhi', 'state' => 'Delhi',
                'state_code' => '07', 'pincode' => $pin, 'phone' => '0000000000',
                'job_sequence' => 10000, 'is_active' => true,
            ]);
        }

        // ── Branches (created in this order so ids are 1..4 on a fresh DB) ──
        $branches = [];
        foreach ([
            'BR-001' => ['KPNG', 'Karol Bagh'],
            'BR-002' => ['KPNG', 'Nehru Place'],
            'BR-003' => ['HPS',  'HP Showroom - Connaught Place'],
            'BR-004' => ['HPS',  'HP Showroom - Saket'],
        ] as $code => [$co, $name]) {
            $branches[$code] = Branch::firstOrCreate(['code' => $code], [
                'company_id' => $companies[$co]->id, 'name' => $name, 'address' => 'Update me',
                'city' => 'New Delhi', 'state' => 'Delhi', 'pincode' => '110001', 'phone' => '0000000000',
                'is_active' => true,
            ]);
        }

        // Dropdown values used by the job sheet and inventory forms (categories, call types, ...)
        $this->call(OptionSeeder::class);

        // App settings (GST, currency, prefixes, ...) - create-only
        $this->call(SettingsSeeder::class);

        // ── Employees: [code, role, name, email, mobile, primary branch, all branches] ──
        // Order matters: demo data (DeviceSeeder) refers to employee id 3 = Amit Technician.
        $employees = [
            ['EMP-001', 'super_admin',    'Super Admin',        'admin@kpng.com',    '9000000001', 'BR-001', ['BR-001']],
            ['EMP-002', 'branch_manager', 'Branch Manager',     'manager@kpng.com',  '9000000002', 'BR-001', ['BR-001']],
            ['EMP-003', 'employee',       'Amit Technician',    'employee@kpng.com', '9000000004', 'BR-001', ['BR-001', 'BR-003']], // 1 branch per company
            ['EMP-004', 'stock_manager',  'Neha Stock Manager', 'stock@kpng.com',    '9000000003', 'BR-001', ['BR-001', 'BR-002', 'BR-003', 'BR-004']],
            ['EMP-005', 'employee',       'Suresh Technician',  'suresh@kpng.com',   '9000000005', 'BR-002', ['BR-002']],
            ['EMP-006', 'branch_manager', 'HP Branch Manager',  'manager2@kpng.com', '9000000006', 'BR-003', ['BR-003']],
        ];

        foreach ($employees as [$code, $role, $name, $email, $mobile, $primary, $all]) {
            $b = $branches[$primary];
            $e = Employee::firstOrCreate(['code' => $code], [
                'company_id' => $b->company_id, 'name' => $name, 'mobile' => $mobile, 'email' => $email,
                'password' => Hash::make(self::PASSWORD), 'designation' => $name,
                'branch_id' => $b->id, 'branch_name' => $b->name, 'role' => $role,
                'must_change_password' => true, 'is_first_login' => true, 'joined_at' => now(),
            ]);
            // additive only: never detaches existing assignments
            $e->branches()->syncWithoutDetaching(array_map(fn ($c) => $branches[$c]->id, $all));
        }

        foreach ($branches as $b) {
            $b->update(['total_employees' => $b->employees()->count()]);
        }

        // ── Lead module users (lead_users table): [role, employee_id, mobile, email, name, branch] ──
        $leadUsers = [
            ['super_admin',     'LEAD0001', '9000000011', 'leadadmin@kpng.com',        'Lead Super Admin',    'BR-001'],
            ['sales_executive', 'LEAD0002', '9000000012', 'sales@kpng.com',            'Sales Executive',     'BR-001'],
            ['super_admin',     'EMP0001',  '9876543210', 'admin@kpmgleadmanager.com', 'Super Admin',         'BR-001'],
            ['sales_executive', 'EMP0002',  '9876543211', 'exec1@kpmgleadmanager.com', 'Sales Executive One', 'BR-002'],
            ['sales_executive', 'EMP0003',  '9876543212', 'exec2@kpmgleadmanager.com', 'Sales Executive Two', 'BR-003'],
        ];

        foreach ($leadUsers as [$role, $empId, $mobile, $email, $name, $branchCode]) {
            LeadUser::firstOrCreate(['employee_id' => $empId], [
                'name' => $name, 'mobile' => $mobile, 'email' => $email,
                'password' => self::PASSWORD, // hashed automatically by the model
                'role' => $role, 'branch' => $branches[$branchCode]->name, 'designation' => $name,
                'status' => 'active', 'is_temp_password' => true,
            ]);
        }
    }
}
