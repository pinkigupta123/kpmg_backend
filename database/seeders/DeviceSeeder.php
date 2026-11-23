<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Device;

class DeviceSeeder extends Seeder
{
    public function run(): void
    {
        // Device 1 — Laptop, repair_completed (Branch 1, assigned to EMP-003)
        Device::updateOrCreate(
            ['job_number' => 'JOB-2024-0001'],
            [
                'receipt_number'           => 'REC-2024-0001',
                'customer_id'              => 1,
                'customer_name'            => 'Rahul Sharma',
                'customer_mobile'          => '9876543210',
                'type'                     => 'laptop',
                'brand'                    => 'HP',
                'model'                    => 'Pavilion 15',
                'serial_number'            => 'HP-SN-00123',
                'processor'                => 'Intel Core i5-1135G7',
                'ram'                      => '8GB DDR4',
                'storage'                  => '512GB SSD',
                'accessories'              => json_encode(['charger', 'bag']),
                'reported_issue'           => 'Laptop not turning on, no display.',
                'physical_condition'       => 'Minor scratches on lid.',
                'condition_checklist'      => json_encode(['screen_ok' => false, 'keyboard_ok' => true, 'battery_ok' => false]),
                'current_branch_id'        => 1,
                'current_branch_name'      => 'HQ - TechFix Central',
                'assigned_technician_id'   => 3,
                'assigned_technician_name' => 'Amit Technician',
                'status'                   => 'repair_completed',
                'call_type'                => 'out_warranty',
                'service_type'             => 'carry_in',
                'call_reason'              => 'Hardware failure',
                'response_time'            => '24 hours',
                'received_at'              => now()->subDays(7),
                'assigned_at'              => now()->subDays(6),
                'completed_at'             => now()->subDays(1),
                'created_by'               => 1,
            ]
        );

        // Device 2 — Desktop, repair_in_progress (Branch 1, assigned to EMP-003)
        Device::updateOrCreate(
            ['job_number' => 'JOB-2024-0002'],
            [
                'receipt_number'           => 'REC-2024-0002',
                'customer_id'              => 1,
                'customer_name'            => 'Rahul Sharma',
                'customer_mobile'          => '9876543210',
                'type'                     => 'desktop',
                'brand'                    => 'Dell',
                'model'                    => 'OptiPlex 7090',
                'serial_number'            => 'DL-SN-00456',
                'processor'                => 'Intel Core i7-10700',
                'ram'                      => '16GB DDR4',
                'storage'                  => '1TB HDD + 256GB SSD',
                'accessories'              => json_encode(['keyboard', 'mouse']),
                'reported_issue'           => 'System freezing intermittently.',
                'physical_condition'       => 'Good condition.',
                'condition_checklist'      => json_encode(['screen_ok' => true, 'keyboard_ok' => true, 'battery_ok' => true]),
                'current_branch_id'        => 1,
                'current_branch_name'      => 'HQ - TechFix Central',
                'assigned_technician_id'   => 3,
                'assigned_technician_name' => 'Amit Technician',
                'status'                   => 'repair_in_progress',
                'call_type'                => 'amc',
                'service_type'             => 'onsite',
                'call_reason'              => 'Software / OS issue',
                'response_time'            => '4 hours',
                'received_at'              => now()->subDays(3),
                'assigned_at'              => now()->subDays(2),
                'created_by'               => 2,
            ]
        );

        // Device 3 — Printer, received (Branch 2, unassigned)
        Device::updateOrCreate(
            ['job_number' => 'JOB-2024-0003'],
            [
                'receipt_number'           => 'REC-2024-0003',
                'customer_id'              => 2,
                'customer_name'            => 'Priya Singh',
                'customer_mobile'          => '9123456789',
                'type'                     => 'printer',
                'brand'                    => 'Canon',
                'model'                    => 'PIXMA G3010',
                'serial_number'            => 'CN-SN-00789',
                'processor'                => null,
                'ram'                      => null,
                'storage'                  => null,
                'accessories'              => json_encode(['power cable', 'USB cable']),
                'reported_issue'           => 'Paper jam and print quality issue.',
                'physical_condition'       => 'Ink stains on body.',
                'condition_checklist'      => json_encode(['power_ok' => true, 'paper_feed_ok' => false]),
                'current_branch_id'        => 2,
                'current_branch_name'      => 'North Branch',
                'assigned_technician_id'   => null,
                'assigned_technician_name' => null,
                'status'                   => 'received',
                'call_type'                => 'warranty',
                'service_type'             => 'carry_in',
                'call_reason'              => 'Mechanical issue',
                'response_time'            => '48 hours',
                'received_at'              => now()->subDay(),
                'created_by'               => 5,
            ]
        );

        // Device 4 — Laptop, delivered (Branch 1)
        Device::updateOrCreate(
            ['job_number' => 'JOB-2024-0004'],
            [
                'receipt_number'           => 'REC-2024-0004',
                'customer_id'              => 2,
                'customer_name'            => 'Priya Singh',
                'customer_mobile'          => '9123456789',
                'type'                     => 'laptop',
                'brand'                    => 'Lenovo',
                'model'                    => 'ThinkPad E14',
                'serial_number'            => 'LN-SN-00321',
                'processor'                => 'AMD Ryzen 5 5500U',
                'ram'                      => '8GB DDR4',
                'storage'                  => '256GB SSD',
                'accessories'              => json_encode(['charger']),
                'reported_issue'           => 'Battery draining very fast.',
                'physical_condition'       => 'Excellent.',
                'condition_checklist'      => json_encode(['screen_ok' => true, 'keyboard_ok' => true, 'battery_ok' => false]),
                'current_branch_id'        => 1,
                'current_branch_name'      => 'HQ - TechFix Central',
                'assigned_technician_id'   => 3,
                'assigned_technician_name' => 'Amit Technician',
                'status'                   => 'delivered',
                'call_type'                => 'warranty',
                'service_type'             => 'carry_in',
                'call_reason'              => 'Battery replacement',
                'response_time'            => '24 hours',
                'received_at'              => now()->subDays(10),
                'assigned_at'              => now()->subDays(9),
                'completed_at'             => now()->subDays(4),
                'delivered_at'             => now()->subDays(1),
                'created_by'               => 1,
            ]
        );

        // Device 5 — Networking device, diagnosis_in_progress (Branch 1)
        Device::updateOrCreate(
            ['job_number' => 'JOB-2024-0005'],
            [
                'receipt_number'           => 'REC-2024-0005',
                'customer_id'              => 1,
                'customer_name'            => 'Rahul Sharma',
                'customer_mobile'          => '9876543210',
                'type'                     => 'networking',
                'brand'                    => 'Cisco',
                'model'                    => 'RV340 Router',
                'serial_number'            => 'CS-SN-00654',
                'processor'                => null,
                'ram'                      => null,
                'storage'                  => null,
                'accessories'              => json_encode(['power adapter', 'ethernet cable']),
                'reported_issue'           => 'Router not connecting to internet after firmware update.',
                'physical_condition'       => 'No physical damage.',
                'condition_checklist'      => json_encode(['power_ok' => true, 'ports_ok' => false]),
                'current_branch_id'        => 1,
                'current_branch_name'      => 'HQ - TechFix Central',
                'assigned_technician_id'   => 3,
                'assigned_technician_name' => 'Amit Technician',
                'status'                   => 'diagnosis_in_progress',
                'call_type'                => 'amc',
                'service_type'             => 'carry_in',
                'call_reason'              => 'Firmware / config issue',
                'response_time'            => '8 hours',
                'received_at'              => now()->subDays(2),
                'assigned_at'              => now()->subDays(1),
                'created_by'               => 2,
            ]
        );
    }
}
