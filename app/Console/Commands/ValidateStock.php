<?php

namespace App\Console\Commands;

use App\Models\Ingredient;
use Illuminate\Console\Command;

class ValidateStock extends Command
{
    protected $signature = 'cafestock:validate-stock';
    protected $description = 'Validate ingredients.current_stock against latest stock movement stock_after.';

    public function handle(): int
    {
        $failed = 0;
        Ingredient::orderBy('id')->get()->each(function (Ingredient $ingredient) use (&$failed) {
            $last = $ingredient->movements()->latest('id')->first();
            if (! $last || bccomp((string) $ingredient->current_stock, (string) $last->stock_after, 3) !== 0) {
                $failed++;
                $this->error("{$ingredient->name}: current={$ingredient->current_stock}, movement=".($last?->stock_after ?? 'none'));
                return;
            }
            $this->line("OK {$ingredient->name}: {$ingredient->current_stock}");
        });

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
