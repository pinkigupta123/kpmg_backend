<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Company;
use App\Models\DropdownOption;
use App\Models\Employee;
use App\Models\LeadUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read-only post-deploy check: is the base setup (ProductionSeeder) fully in place? */
class VerifySetup extends Command
{
    protected $signature = 'setup:verify';
    protected $description = 'Read-only check that migrations, companies, branches, users, options and settings are all set';

    private int $failed = 0;

    private function check(string $label, bool $ok, string $detail = ''): void
    {
        $ok ? $this->line("  <info>PASS</info>  $label") : $this->line("  <error>FAIL</error>  $label" . ($detail ? " - $detail" : ''));
        if (!$ok) $this->failed++;
    }

    public function handle(): int
    {
        $db = config('database.connections.' . config('database.default'));
        $this->info('Database: ' . config('database.default') . ' / ' . ($db['database'] ?? '?') . ' @ ' . ($db['host'] ?? 'local'));
        $this->line('App env: ' . app()->environment() . ' | debug: ' . (config('app.debug') ? 'ON (turn off in prod)' : 'off'));

        $this->newLine();
        $this->info('Tables');
        foreach (['companies', 'branches', 'employees', 'employee_branch', 'lead_users', 'settings', 'dropdown_options'] as $t) {
            $this->check("table $t exists", Schema::hasTable($t));
        }
        if ($this->failed) {
            $this->error('Missing tables - run: php artisan migrate --force');
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Companies and branches');
        $this->check('company KPNG exists', Company::where('code', 'KPNG')->exists());
        $this->check('company HPS exists', Company::where('code', 'HPS')->exists());
        foreach (['KPNG', 'HPS'] as $c) {
            $n = Branch::whereHas('company', fn ($q) => $q->where('code', $c))->count();
            $this->check("company $c has 2+ branches", $n >= 2, "found $n");
        }
        foreach (['BR-001', 'BR-002', 'BR-003', 'BR-004'] as $code) {
            $this->check("branch $code exists", Branch::where('code', $code)->exists());
        }

        $this->newLine();
        $this->info('Staff users (employees)');
        foreach ([
            'admin@kpng.com' => 'super_admin', 'manager@kpng.com' => 'branch_manager',
            'stock@kpng.com' => 'stock_manager', 'employee@kpng.com' => 'employee',
        ] as $email => $role) {
            $e = Employee::where('email', $email)->first();
            $this->check("$email exists with role $role", $e && $e->role === $role, $e ? "role is {$e->role}" : 'missing');
        }

        $stock = Employee::where('email', 'stock@kpng.com')->first();
        $allBranches = Branch::count();
        $stockIds = $stock ? $stock->allBranchIds() : [];
        $this->check("stock manager can access ALL branches ($allBranches)", $stock && count($stockIds) >= $allBranches, 'has ' . count($stockIds));

        $tech = Employee::where('email', 'employee@kpng.com')->first();
        $techCompanies = $tech ? Branch::whereIn('id', $tech->allBranchIds())->pluck('company_id')->unique()->count() : 0;
        $this->check('technician has a branch in each of the 2 companies', $techCompanies >= 2, "covers $techCompanies company(ies)");

        $noPivot = Employee::whereDoesntHave('branches')->count();
        $this->check('every employee has at least one branch assigned', $noPivot === 0, "$noPivot without");

        $this->newLine();
        $this->info('Lead users (lead_users)');
        foreach (['leadadmin@kpng.com' => 'super_admin', 'sales@kpng.com' => 'sales_executive'] as $email => $role) {
            $u = LeadUser::where('email', $email)->first();
            $this->check("$email exists with role $role", $u && $u->role === $role, $u ? "role is {$u->role}" : 'missing');
        }

        $this->newLine();
        $this->info('Dropdown options and settings');
        foreach (['stock_category', 'stock_condition', 'stock_unit', 'call_type', 'service_type', 'call_reason', 'response_time'] as $g) {
            $this->check("dropdown group $g", DropdownOption::where('group', $g)->count() > 0);
        }
        foreach (['gst_percentage', 'currency_symbol', 'job_number_prefix'] as $k) {
            $this->check("setting $k", DB::table('settings')->where('key', $k)->exists());
        }

        $this->newLine();
        $this->check('storage link (public/storage) exists', file_exists(public_path('storage')), 'run: php artisan storage:link');

        $this->newLine();
        if ($this->failed) {
            $this->error("{$this->failed} check(s) FAILED - see above.");
            return self::FAILURE;
        }
        $this->info('All checks passed - base setup is complete.');
        return self::SUCCESS;
    }
}
