<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Device;
use Illuminate\Support\Facades\DB;

class JobNumberService
{
    /**
     * Job sheet number: <Company Initials> <System-Generated ID> <Creator Initials>
     * e.g. "HTC 10006 AT". The ID is a per-company sequence incremented under a row lock.
     */
    public static function generate(Company $company, string $creatorName): array
    {
        $seq = DB::transaction(function () use ($company) {
            $locked = Company::whereKey($company->id)->lockForUpdate()->first();
            $locked->job_sequence = $locked->job_sequence + 1;
            $locked->save();
            return $locked->job_sequence;
        });

        return [$seq, sprintf('%s %d %s', $company->code, $seq, self::initials($creatorName))];
    }

    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        $letters = array_map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)), array_slice($words, 0, 2));
        return implode('', $letters) ?: 'XX';
    }

    public static function generateReceipt(): string
    {
        $year = date('Y');
        $prefix = "RCP-{$year}-";

        $lastDevice = Device::where('receipt_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $newNumber = $lastDevice ? intval(last(explode('-', $lastDevice->receipt_number))) + 1 : 1;

        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
}
