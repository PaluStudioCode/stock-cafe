<?php

namespace Tests\Unit;

use App\Support\CafeStock;
use App\Support\CafeStockMath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CafeStockBusinessRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_order_formula_calculates_subtotal_discount_and_total(): void
    {
        $items = [
            ['quantity' => 10, 'unit_cost' => 160],
            ['quantity' => 5, 'unit_cost' => 95],
        ];

        $subtotal = CafeStockMath::purchaseSubtotal($items);
        $total = CafeStockMath::purchaseTotal($subtotal, 1000);

        $this->assertSame(2075.0, $subtotal);
        $this->assertSame(1075.0, $total);
    }

    public function test_production_usage_and_adjustment_formulas_match_prd_rules(): void
    {
        $quantityUsed = CafeStockMath::productionQuantityUsed(18, 20);
        $productionCost = CafeStockMath::estimatedCost($quantityUsed, 160);
        $usageCost = CafeStockMath::estimatedCost(300, 22);
        $adjustmentDifference = CafeStockMath::stockDifference(5220, 5200);

        $this->assertSame(360.0, $quantityUsed);
        $this->assertSame(57600.0, $productionCost);
        $this->assertSame(6600.0, $usageCost);
        $this->assertSame(-20.0, $adjustmentDifference);
    }

    public function test_auto_code_generation_for_all_operational_prefixes(): void
    {
        $this->seed();

        $this->assertSame('ING-20260506-0001', CafeStock::code('ING', 'ingredients', 'code', '2026-05-06'));
        $this->assertSame('MENU-20260506-0001', CafeStock::code('MENU', 'menu_items', 'code', '2026-05-06'));
        $this->assertSame('PO-20260506-0001', CafeStock::code('PO', 'purchase_orders', 'purchase_code', '2026-05-06'));
        $this->assertSame('PROD-20260506-0001', CafeStock::code('PROD', 'production_logs', 'production_code', '2026-05-06'));
        $this->assertSame('USE-20260506-0001', CafeStock::code('USE', 'stock_usages', 'usage_code', '2026-05-06'));
        $this->assertSame('ADJ-20260506-0001', CafeStock::code('ADJ', 'stock_adjustments', 'adjustment_code', '2026-05-06'));
    }

    public function test_numeric_business_helpers_reject_negative_values(): void
    {
        foreach ([
            fn () => CafeStockMath::lineSubtotal(-1, 100),
            fn () => CafeStockMath::lineSubtotal(1, -100),
            fn () => CafeStockMath::purchaseTotal(100, -1),
            fn () => CafeStockMath::purchaseTotal(100, 101),
            fn () => CafeStockMath::productionQuantityUsed(-1, 1),
            fn () => CafeStockMath::stockDifference(10, -1),
        ] as $callback) {
            try {
                $callback();
                $this->fail('Nilai negatif atau total invalid lolos dari helper bisnis.');
            } catch (InvalidArgumentException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }
}
