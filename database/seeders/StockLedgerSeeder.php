<?php

namespace Database\Seeders;

use App\Models\StockItem;
use App\Models\StockTransaction;
use Illuminate\Database\Seeder;

/** Opening ledger entries so seeded stock has a history. */
class StockLedgerSeeder extends Seeder
{
    public function run(): void
    {
        foreach (StockItem::all() as $item) {
            StockTransaction::record($item, 'addition', (int) $item->quantity, $item->slip_number, 'Opening stock', null);
        }
    }
}
