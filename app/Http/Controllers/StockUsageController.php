<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\StockUsage;
use App\Models\StockUsageItem;
use App\Services\StockService;
use App\Support\CafeStock;
use App\Support\CafeStockMath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use RuntimeException;

class StockUsageController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Transactions/StockUsages', [
            'usages' => StockUsage::with(['user', 'items.ingredient'])->latest('id')->paginate(20),
            'ingredients' => Ingredient::with('unit:id,name,symbol')->where('is_active', true)->orderBy('name')->get(),
            'usageTypes' => CafeStock::USAGE_TYPES,
            'canCancel' => $request->user()->isOwner(),
        ]);
    }

    public function store(Request $request, StockService $stock)
    {
        $data = $this->validated($request);

        $usage = DB::transaction(function () use ($data, $request) {
            $usage = StockUsage::create([
                'user_id' => $request->user()->id,
                'usage_code' => CafeStock::code('USE', 'stock_usages', 'usage_code', $data['usage_date'] ?? now()),
                'usage_date' => $data['usage_date'] ?? now(),
                'usage_type' => $data['usage_type'],
                'estimated_total_cost' => $this->estimatedTotal($data['items']),
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
            ]);
            $this->replaceItems($usage, $data['items']);

            return $usage;
        });

        if ($data['status'] === 'completed') {
            try {
                $stock->completeUsage($usage);
            } catch (RuntimeException $e) {
                $usage->delete();
                return back()->withErrors(['stock' => $e->getMessage()]);
            }
        }

        return back()->with('success', 'Pemakaian stok tersimpan.');
    }

    public function update(Request $request, StockUsage $stockUsage, StockService $stock)
    {
        abort_unless($stockUsage->status === 'draft', 422, 'Pemakaian stok yang sudah selesai tidak dapat diedit langsung.');

        $data = $this->validated($request);

        DB::transaction(function () use ($data, $stockUsage) {
            $stockUsage = StockUsage::lockForUpdate()->findOrFail($stockUsage->id);
            abort_unless($stockUsage->status === 'draft', 422, 'Pemakaian stok yang sudah selesai tidak dapat diedit langsung.');

            $stockUsage->update([
                'usage_date' => $data['usage_date'] ?? now(),
                'usage_type' => $data['usage_type'],
                'estimated_total_cost' => $this->estimatedTotal($data['items']),
                'notes' => $data['notes'] ?? null,
            ]);
            $this->replaceItems($stockUsage, $data['items']);
        });

        if ($data['status'] === 'completed') {
            try {
                $stock->completeUsage($stockUsage);
            } catch (RuntimeException $e) {
                return back()->withErrors(['stock' => $e->getMessage()]);
            }
        }

        return back()->with('success', 'Pemakaian stok diperbarui.');
    }

    public function destroy(StockUsage $stockUsage)
    {
        abort_unless($stockUsage->status === 'draft', 422, 'Hanya draf pemakaian stok yang dapat dihapus.');
        $stockUsage->delete();

        return back()->with('success', 'Draf pemakaian stok dihapus.');
    }

    public function cancel(StockUsage $stockUsage, StockService $stock)
    {
        try {
            $stock->cancelUsage($stockUsage, auth()->id());
        } catch (RuntimeException $e) {
            return back()->withErrors(['stock' => $e->getMessage()]);
        }

        return back()->with('success', 'Pemakaian stok dibatalkan dan stok dikembalikan.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'usage_date' => ['nullable', 'date'],
            'usage_type' => ['required', Rule::in(CafeStock::USAGE_TYPES)],
            'status' => ['required', Rule::in(['draft', 'completed'])],
            'notes' => ['nullable'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', Rule::exists('ingredients', 'id')->where('is_active', true)],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.notes' => ['nullable'],
        ]);
    }

    private function replaceItems(StockUsage $usage, array $items): void
    {
        $usage->items()->delete();

        foreach ($items as $item) {
            $ingredient = Ingredient::findOrFail($item['ingredient_id']);
            StockUsageItem::create([
                'stock_usage_id' => $usage->id,
                'ingredient_id' => $ingredient->id,
                'quantity' => $item['quantity'],
                'unit_cost_snapshot' => $ingredient->last_unit_cost,
                'estimated_cost' => CafeStockMath::estimatedCost($item['quantity'], $ingredient->last_unit_cost),
                'notes' => $item['notes'] ?? null,
            ]);
        }
    }

    private function estimatedTotal(array $items): float
    {
        return collect($items)->sum(function ($item) {
            $ingredient = Ingredient::findOrFail($item['ingredient_id']);

            return CafeStockMath::estimatedCost($item['quantity'], $ingredient->last_unit_cost);
        });
    }
}
