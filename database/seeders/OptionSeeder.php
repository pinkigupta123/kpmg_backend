<?php

namespace Database\Seeders;

use App\Models\DropdownOption;
use Illuminate\Database\Seeder;

class OptionSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            'stock_category' => [
                'Display', 'Battery', 'Keyboard', 'Motherboard', 'RAM', 'SSD', 'Charger', 'Storage',
                'Charger / Adapter', 'Consumables', 'Networking', 'Printer Ink / Toner', 'Other',
            ],
            'stock_condition' => ['New', 'Refurbished', 'Used'],
            'stock_unit' => ['pcs', 'set', 'box', 'meter'],
            'call_type' => ['WAR' => 'Warranty', 'OW' => 'Out of Warranty', 'CP_AMC' => 'Contract / AMC', 'Other' => 'Other'],
            'service_type' => ['Onsite' => 'Onsite', 'Carry-in' => 'Carry-in', '1 Hour' => '1 Hour Response', 'Other' => 'Other'],
            'call_reason' => ['PM' => 'Preventive Maint. (PM)', 'BD' => 'Breakdown (BD)', 'INS' => 'Installation (INS)', 'Other' => 'Other'],
            'response_time' => ['1-8 Hours' => '1-8 Hours', '8-24 Hours' => '8-24 Hours', '>24 Hours' => '>24 Hours'],
        ];

        foreach ($groups as $group => $options) {
            $i = 0;
            foreach ($options as $value => $label) {
                if (is_int($value)) {
                    $value = $label;
                }
                DropdownOption::updateOrCreate(
                    ['group' => $group, 'value' => $value],
                    ['label' => $label, 'sort_order' => ++$i, 'is_active' => true]
                );
            }
        }
    }
}
