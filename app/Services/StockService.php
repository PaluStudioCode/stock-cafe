<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Ingredient;
use App\Models\ProductionLog;
use App\Models\ProductionLogItem;
use App\Models\PurchaseOrder;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\StockUsage;
use App\Support\CafeStockMath;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockService
{
    public function move(
        Ingredient $ingredient,
        ?int $userId,
        string $type,
        string $referenceType,
        ?int $referenceId,
        float $quantityIn,
        float $quantityOut,
        float $unitCost,
        ?string $notes = null
    ): StockMovement {
        if ($quantityIn < 0 || $quantityOut < 0) {
            throw new RuntimeException('Quantity movement tidak boleh negatif.');
        }

        return DB::transaction(function () use ($ingredient, $userId, $type, $referenceType, $referenceId, $quantityIn, $quantityOut, $unitCost, $notes) {
            $locked = Ingredient::query()->lockForUpdate()->findOrFail($ingredient->id);
            $before = (float) $locked->current_stock;
            $after = round($before + $quantityIn - $quantityOut, 3);

            if ($after < 0) {
                throw new RuntimeException("Stok {$locked->name} tidak cukup.");
            }

            $locked->forceFill(['current_stock' => $after])->save();

            return StockMovement::create([
                'ingredient_id' => $locked->id,
                'user_id' => $userId,
                'type' => $type,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'quantity_in' => $quantityIn,
                'quantity_out' => $quantityOut,
                'stock_before' => $before,
                'stock_after' => $after,
                'unit_cost_snapshot' => $unitCost,
                'notes' => $notes,
            ]);
        });
    }

    public function receivePurchaseOrder(PurchaseOrder $order, int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $userId) {
            $order = PurchaseOrder::with('items.ingredient')->lockForUpdate()->findOrFail($order->id);
            if ($order->status !== 'draft') {
                throw new RuntimeException('Purchase order received tidak dapat diproses ulang.');
            }
            if ($order->items->isEmpty()) {
                throw new RuntimeException('Purchase order minimal memiliki satu item.');
            }

            foreach ($order->items as $item) {
                if ((float) $item->quantity <= 0 || (float) $item->unit_cost < 0) {
                    throw new RuntimeException('Item purchase order tidak valid.');
                }
                $this->move($item->ingredient, $userId, 'purchase_receipt', 'purchase_orders', $order->id, (float) $item->quantity, 0, (float) $item->unit_cost, $order->purchase_code);
                $item->ingredient->forceFill(['last_unit_cost' => $item->unit_cost])->save();
            }

            $order->forceFill(['status' => 'received', 'received_at' => now()])->save();
            $this->log($userId, 'receive_purchase', 'purchase_orders', "Menerima {$order->purchase_code}", 'purchase_orders', $order->id);

            return $order;
        });
    }

    public function completeProduction(ProductionLog $log): ProductionLog
    {
        return DB::transaction(function () use ($log) {
            $log = ProductionLog::with('menuItem.recipeItems.ingredient')->lockForUpdate()->findOrFail($log->id);
            if (! $log->menuItem->is_active || $log->menuItem->recipeItems->isEmpty()) {
                throw new RuntimeException('Menu tidak aktif atau belum memiliki resep.');
            }

            $total = 0;
            foreach ($log->menuItem->recipeItems as $recipe) {
                $ingredient = Ingredient::query()->lockForUpdate()->findOrFail($recipe->ingredient_id);
                if (! $ingredient->is_active) {
                    throw new RuntimeException("Bahan {$ingredient->name} tidak aktif.");
                }
                $used = CafeStockMath::productionQuantityUsed($recipe->quantity_per_serving, $log->quantity);
                if ((float) $ingredient->current_stock < $used) {
                    throw new RuntimeException("Stok {$ingredient->name} tidak cukup.");
                }
                $cost = CafeStockMath::estimatedCost($used, $ingredient->last_unit_cost);
                ProductionLogItem::create([
                    'production_log_id' => $log->id,
                    'ingredient_id' => $ingredient->id,
                    'quantity_per_serving_snapshot' => $recipe->quantity_per_serving,
                    'quantity_used' => $used,
                    'unit_cost_snapshot' => $ingredient->last_unit_cost,
                    'estimated_cost' => $cost,
                ]);
                $this->move($ingredient, $log->user_id, 'production_usage', 'production_logs', $log->id, 0, $used, (float) $ingredient->last_unit_cost, $log->production_code);
                $total += $cost;
            }

            $log->forceFill(['estimated_total_cost' => $total, 'status' => 'completed'])->save();
            $this->log($log->user_id, 'complete_production', 'production_logs', "Menyelesaikan {$log->production_code}", 'production_logs', $log->id);

            return $log;
        });
    }

    public function cancelProduction(ProductionLog $log, int $userId): ProductionLog
    {
        return DB::transaction(function () use ($log, $userId) {
            $log = ProductionLog::with('items.ingredient')->lockForUpdate()->findOrFail($log->id);
            if ($log->status !== 'completed') {
                throw new RuntimeException('Production log hanya bisa dibatalkan dari status completed.');
            }
            if ($log->items->isEmpty()) {
                throw new RuntimeException('Production log tidak memiliki snapshot bahan.');
            }

            foreach ($log->items as $item) {
                $this->move(
                    $item->ingredient,
                    $userId,
                    'cancel_production',
                    'production_logs',
                    $log->id,
                    (float) $item->quantity_used,
                    0,
                    (float) $item->unit_cost_snapshot,
                    "Cancel {$log->production_code}"
                );
            }

            $log->forceFill(['status' => 'cancelled'])->save();
            $this->log($userId, 'cancel_production', 'production_logs', "Membatalkan {$log->production_code}", 'production_logs', $log->id);

            return $log;
        });
    }

    public function completeUsage(StockUsage $usage): StockUsage
    {
        return DB::transaction(function () use ($usage) {
            $usage = StockUsage::with('items.ingredient')->lockForUpdate()->findOrFail($usage->id);
            if ($usage->status !== 'draft') {
                throw new RuntimeException('Stock usage hanya dapat diselesaikan dari status draft.');
            }
            if ($usage->items->isEmpty()) {
                throw new RuntimeException('Stock usage minimal memiliki satu item.');
            }

            $total = 0;
            foreach ($usage->items as $item) {
                $ingredient = Ingredient::query()->lockForUpdate()->findOrFail($item->ingredient_id);
                $type = $usage->usage_type === 'waste' ? 'waste' : 'manual_usage';
                $cost = CafeStockMath::estimatedCost($item->quantity, $ingredient->last_unit_cost);
                $item->forceFill(['unit_cost_snapshot' => $ingredient->last_unit_cost, 'estimated_cost' => $cost])->save();
                $this->move($ingredient, $usage->user_id, $type, 'stock_usages', $usage->id, 0, (float) $item->quantity, (float) $ingredient->last_unit_cost, $usage->usage_code);
                $total += $cost;
            }

            $usage->forceFill(['estimated_total_cost' => $total, 'status' => 'completed'])->save();
            $this->log($usage->user_id, 'complete_usage', 'stock_usages', "Mencatat {$usage->usage_code}", 'stock_usages', $usage->id);

            return $usage;
        });
    }

    public function cancelUsage(StockUsage $usage, int $userId): StockUsage
    {
        return DB::transaction(function () use ($usage, $userId) {
            $usage = StockUsage::with('items.ingredient')->lockForUpdate()->findOrFail($usage->id);
            if ($usage->status !== 'completed') {
                throw new RuntimeException('Stock usage hanya bisa dibatalkan dari status completed.');
            }
            if ($usage->items->isEmpty()) {
                throw new RuntimeException('Stock usage tidak memiliki item.');
            }

            foreach ($usage->items as $item) {
                $this->move(
                    $item->ingredient,
                    $userId,
                    'cancel_usage',
                    'stock_usages',
                    $usage->id,
                    (float) $item->quantity,
                    0,
                    (float) $item->unit_cost_snapshot,
                    "Cancel {$usage->usage_code}"
                );
            }

            $usage->forceFill(['status' => 'cancelled'])->save();
            $this->log($userId, 'cancel_usage', 'stock_usages', "Membatalkan {$usage->usage_code}", 'stock_usages', $usage->id);

            return $usage;
        });
    }

    public function approveAdjustment(StockAdjustment $adjustment, int $approverId): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment, $approverId) {
            $adjustment = StockAdjustment::with('items.ingredient')->lockForUpdate()->findOrFail($adjustment->id);
            if ($adjustment->status !== 'draft') {
                throw new RuntimeException('Stock adjustment hanya dapat disetujui dari status draft.');
            }
            if ($adjustment->items->isEmpty()) {
                throw new RuntimeException('Stock adjustment minimal memiliki satu item.');
            }

            foreach ($adjustment->items as $item) {
                $ingredient = Ingredient::query()->lockForUpdate()->findOrFail($item->ingredient_id);
                $system = (float) $ingredient->current_stock;
                $difference = CafeStockMath::stockDifference($system, $item->counted_stock);
                $item->forceFill(['system_stock' => $system, 'difference' => $difference])->save();

                if ($difference > 0) {
                    $this->move($ingredient, $approverId, 'adjustment_in', 'stock_adjustments', $adjustment->id, $difference, 0, (float) $ingredient->last_unit_cost, $adjustment->adjustment_code);
                } elseif ($difference < 0) {
                    $this->move($ingredient, $approverId, 'adjustment_out', 'stock_adjustments', $adjustment->id, 0, abs($difference), (float) $ingredient->last_unit_cost, $adjustment->adjustment_code);
                }
            }

            $adjustment->forceFill(['status' => 'approved', 'approved_by' => $approverId, 'approved_at' => now()])->save();
            $this->log($approverId, 'approve_adjustment', 'stock_adjustments', "Menyetujui {$adjustment->adjustment_code}", 'stock_adjustments', $adjustment->id);

            return $adjustment;
        });
    }

    public function cancelAdjustment(StockAdjustment $adjustment, int $userId): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment, $userId) {
            $adjustment = StockAdjustment::with('items.ingredient')->lockForUpdate()->findOrFail($adjustment->id);
            if ($adjustment->status === 'cancelled') {
                throw new RuntimeException('Stock adjustment sudah dibatalkan.');
            }

            if ($adjustment->status === 'approved') {
                foreach ($adjustment->items as $item) {
                    $difference = (float) $item->difference;
                    if (abs($difference) < 0.0005) {
                        continue;
                    }

                    $unitCost = StockMovement::where('reference_type', 'stock_adjustments')
                        ->where('reference_id', $adjustment->id)
                        ->where('ingredient_id', $item->ingredient_id)
                        ->whereIn('type', ['adjustment_in', 'adjustment_out'])
                        ->latest('id')
                        ->value('unit_cost_snapshot') ?? $item->ingredient->last_unit_cost;

                    $this->move(
                        $item->ingredient,
                        $userId,
                        'cancel_adjustment',
                        'stock_adjustments',
                        $adjustment->id,
                        $difference < 0 ? abs($difference) : 0,
                        $difference > 0 ? $difference : 0,
                        (float) $unitCost,
                        "Cancel {$adjustment->adjustment_code}"
                    );
                }
            }

            $adjustment->forceFill(['status' => 'cancelled'])->save();
            $this->log($userId, 'cancel_adjustment', 'stock_adjustments', "Membatalkan {$adjustment->adjustment_code}", 'stock_adjustments', $adjustment->id);

            return $adjustment;
        });
    }

    private function log(?int $userId, string $action, string $module, string $description, ?string $referenceType, ?int $referenceId): void
    {
        ActivityLog::create([
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'ip_address' => request()?->ip(),
        ]);
    }
}
