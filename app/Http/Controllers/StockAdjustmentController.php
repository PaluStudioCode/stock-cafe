<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Services\StockService;
use App\Support\CafeStock;
use App\Support\CafeStockMath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use RuntimeException;

class StockAdjustmentController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Transactions/StockAdjustments', [
            'adjustments' => StockAdjustment::with(['user', 'approver', 'items.ingredient'])->latest('id')->paginate(20),
            'ingredients' => Ingredient::with('unit:id,name,symbol')->where('is_active', true)->orderBy('name')->get(),
            'canApprove' => $request->user()->isOwner(),
            'canCancel' => $request->user()->isOwner(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $request) {
            $adjustment = StockAdjustment::create([
                'user_id' => $request->user()->id,
                'adjustment_code' => CafeStock::code('ADJ', 'stock_adjustments', 'adjustment_code', $data['adjustment_date'] ?? now()),
                'adjustment_date' => $data['adjustment_date'] ?? now(),
                'status' => 'draft',
                'reason' => $data['reason'],
            ]);
            foreach ($data['items'] as $item) {
                $ingredient = Ingredient::findOrFail($item['ingredient_id']);
                $this->createItem($adjustment, $ingredient, $item);
            }
        });

        return back()->with('success', 'Draf penyesuaian stok dibuat.');
    }

    public function update(Request $request, StockAdjustment $stockAdjustment)
    {
        abort_unless($stockAdjustment->status === 'draft', 422, 'Penyesuaian stok yang sudah disetujui tidak dapat diedit langsung.');

        $data = $this->validated($request);

        DB::transaction(function () use ($data, $stockAdjustment) {
            $stockAdjustment = StockAdjustment::lockForUpdate()->findOrFail($stockAdjustment->id);
            abort_unless($stockAdjustment->status === 'draft', 422, 'Penyesuaian stok yang sudah disetujui tidak dapat diedit langsung.');

            $stockAdjustment->update([
                'adjustment_date' => $data['adjustment_date'] ?? now(),
                'reason' => $data['reason'],
            ]);
            $stockAdjustment->items()->delete();
            foreach ($data['items'] as $item) {
                $ingredient = Ingredient::findOrFail($item['ingredient_id']);
                $this->createItem($stockAdjustment, $ingredient, $item);
            }
        });

        return back()->with('success', 'Draf penyesuaian stok diperbarui.');
    }

    public function approve(StockAdjustment $stockAdjustment, StockService $stock)
    {
        try {
            $stock->approveAdjustment($stockAdjustment, auth()->id());
        } catch (RuntimeException $e) {
            return back()->withErrors(['stock' => $e->getMessage()]);
        }

        return back()->with('success', 'Penyesuaian stok disetujui dan pergerakan stok dibuat.');
    }

    public function cancel(StockAdjustment $stockAdjustment, StockService $stock)
    {
        try {
            $stock->cancelAdjustment($stockAdjustment, auth()->id());
        } catch (RuntimeException $e) {
            return back()->withErrors(['stock' => $e->getMessage()]);
        }

        return back()->with('success', 'Penyesuaian stok dibatalkan.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'adjustment_date' => ['nullable', 'date'],
            'reason' => ['required'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', Rule::exists('ingredients', 'id')->where('is_active', true)],
            'items.*.counted_stock' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable'],
        ]);
    }

    private function createItem(StockAdjustment $adjustment, Ingredient $ingredient, array $item): StockAdjustmentItem
    {
        $systemStock = (float) $ingredient->current_stock;

        return StockAdjustmentItem::create([
            'stock_adjustment_id' => $adjustment->id,
            'ingredient_id' => $ingredient->id,
            'system_stock' => $systemStock,
            'counted_stock' => $item['counted_stock'],
            'difference' => CafeStockMath::stockDifference($systemStock, $item['counted_stock']),
            'notes' => $item['notes'] ?? null,
        ]);
    }
}
