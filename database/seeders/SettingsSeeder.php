<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // Company info
            ['key' => 'company_name',        'value' => 'KPNG Technologies',     'type' => 'string'],
            ['key' => 'company_email',        'value' => 'info@kpng.com',            'type' => 'string'],
            ['key' => 'company_phone',        'value' => '0000000000',               'type' => 'string'],
            ['key' => 'company_address',      'value' => 'Update me', 'type' => 'string'],
            ['key' => 'company_gst',          'value' => '',            'type' => 'string'],
            ['key' => 'company_logo_path',    'value' => null,                         'type' => 'string'],

            // Job number settings
            ['key' => 'job_number_prefix',    'value' => 'JOB',                        'type' => 'string'],
            ['key' => 'job_number_counter',   'value' => '0',                          'type' => 'string'],
            ['key' => 'receipt_prefix',       'value' => 'REC',                        'type' => 'string'],
            ['key' => 'transfer_prefix',      'value' => 'TRF',                        'type' => 'string'],

            // Operational settings
            ['key' => 'default_response_time', 'value' => '24 hours',                  'type' => 'string'],
            ['key' => 'enable_sms',            'value' => 'false',                     'type' => 'boolean'],
            ['key' => 'enable_email_notify',   'value' => 'false',                     'type' => 'boolean'],
            ['key' => 'allow_technician_close', 'value' => 'false',                    'type' => 'boolean'],

            // Warranty / AMC
            ['key' => 'default_warranty_months', 'value' => '6',                       'type' => 'string'],

            // Currency
            ['key' => 'currency_symbol',      'value' => '₹',                         'type' => 'string'],
            ['key' => 'currency_code',        'value' => 'INR',                        'type' => 'string'],

            // Tax
            ['key' => 'gst_percentage',       'value' => '18',                         'type' => 'string'],
            ['key' => 'include_gst_in_estimates', 'value' => 'true',                   'type' => 'boolean'],

            // App settings
            ['key' => 'app_version',          'value' => '1.0.0',                      'type' => 'string'],
            ['key' => 'maintenance_mode',     'value' => 'false',                      'type' => 'boolean'],
        ];

        // create-only: never overwrite values already changed in the app (safe on prod)
        foreach ($settings as $setting) {
            if (DB::table('settings')->where('key', $setting['key'])->exists()) {
                continue;
            }
            DB::table('settings')->insert([
                'key'        => $setting['key'],
                'value'      => $setting['value'],
                'type'       => $setting['type'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
