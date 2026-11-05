<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransaction extends Model
{
    protected $fillable = [
        'stock_item_id', 'item_name', 'item_code', 'branch_id', 'type', 'quantity_change',
        'quantity_after', 'reference', 'note', 'performed_by', 'performed_by_name',
    ];

    /** Append one ledger row for a stock movement. */
    public static function record(StockItem $item, string $type, int $change, ?string $reference, ?string $note, $user): self
    {
        return self::create([
            'stock_item_id' => $item->id,
            'item_name' => $item->name,
            'item_code' => $item->item_code,
            'branch_id' => $item->branch_id,
            'type' => $type,
            'quantity_change' => $change,
            'quantity_after' => $item->quantity,
            'reference' => $reference,
            'note' => $note,
            'performed_by' => $user?->id,
            'performed_by_name' => $user?->name,
        ]);
    }
}
