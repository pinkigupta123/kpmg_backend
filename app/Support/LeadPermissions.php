<?php

namespace App\Support;

/**
 * Permissions a super admin can grant to a sales executive.
 * NOTE: exporting data is deliberately NOT grantable — it is super-admin only.
 */
class LeadPermissions
{
    public const CATALOG = [
        'bulk_add_leads' => 'Add multiple leads at once',
        'delete_leads'   => 'Delete leads',
    ];

    public static function keys(): array
    {
        return array_keys(self::CATALOG);
    }

    public static function catalog(): array
    {
        $out = [];
        foreach (self::CATALOG as $key => $label) {
            $out[] = ['key' => $key, 'label' => $label];
        }
        return $out;
    }
}
