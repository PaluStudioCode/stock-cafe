<?php

namespace App\Support;

use InvalidArgumentException;

final class CafeStockMath
{
    public static function lineSubtotal(float|int|string $quantity, float|int|string $unitCost): float
    {
        self::ensureNonNegative((float) $quantity, 'quantity');
        self::ensureNonNegative((float) $unitCost, 'unit_cost');

        return round((float) $quantity * (float) $unitCost, 2);
    }

    public static function purchaseSubtotal(array $items): float
    {
        return round(collect($items)->sum(fn (array $item) => self::lineSubtotal($item['quantity'], $item['unit_cost'])), 2);
    }

    public static function purchaseTotal(float|int|string $subtotal, float|int|string $discount): float
    {
        self::ensureNonNegative((float) $subtotal, 'subtotal');
        self::ensureNonNegative((float) $discount, 'discount');

        if ((float) $discount > (float) $subtotal) {
            throw new InvalidArgumentException('discount tidak boleh melebihi subtotal.');
        }

        return round((float) $subtotal - (float) $discount, 2);
    }

    public static function productionQuantityUsed(float|int|string $quantityPerServing, float|int|string $productionQuantity): float
    {
        self::ensureNonNegative((float) $quantityPerServing, 'quantity_per_serving');
        self::ensureNonNegative((float) $productionQuantity, 'production_quantity');

        return round((float) $quantityPerServing * (float) $productionQuantity, 3);
    }

    public static function estimatedCost(float|int|string $quantity, float|int|string $unitCost): float
    {
        return self::lineSubtotal($quantity, $unitCost);
    }

    public static function stockDifference(float|int|string $systemStock, float|int|string $countedStock): float
    {
        self::ensureNonNegative((float) $systemStock, 'system_stock');
        self::ensureNonNegative((float) $countedStock, 'counted_stock');

        return round((float) $countedStock - (float) $systemStock, 3);
    }

    private static function ensureNonNegative(float $value, string $field): void
    {
        if ($value < 0) {
            throw new InvalidArgumentException("{$field} tidak boleh negatif.");
        }
    }
}
