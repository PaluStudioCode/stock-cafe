<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\ProductionLog;
use App\Services\StockService;
use App\Support\CafeStock;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

class ProductionLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ProductionLog::with(['menuItem', 'user', 'items.ingredient']);
        if ($request->user()->role === 'barista') {
            $query->where('user_id', $request->user()->id);
        }

        return Inertia::render('Transactions/ProductionLogs', [
            'logs' => $query->latest('id')->paginate(20),
            'menus' => MenuItem::with('recipeItems.ingredient.unit')
                ->where('is_active', true)
                ->whereHas('recipeItems')
                ->orderBy('name')
                ->get(),
            'canCreate' => $request->user()->hasRole('owner', 'barista'),
            'canCancel' => $request->user()->isOwner(),
        ]);
    }

    public function store(Request $request, StockService $stock)
    {
        $data = $request->validate([
            'menu_item_id' => ['required', 'exists:menu_items,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'production_date' => ['nullable', 'date'],
            'notes' => ['nullable'],
        ]);

        $log = ProductionLog::create([
            'menu_item_id' => $data['menu_item_id'],
            'user_id' => $request->user()->id,
            'production_code' => CafeStock::code('PROD', 'production_logs', 'production_code', $data['production_date'] ?? now()),
            'production_date' => $data['production_date'] ?? now(),
            'quantity' => $data['quantity'],
            'status' => 'completed',
            'notes' => $data['notes'] ?? null,
        ]);

        try {
            $stock->completeProduction($log);
        } catch (RuntimeException $e) {
            $log->delete();
            return back()->withErrors(['stock' => $e->getMessage()]);
        }

        return back()->with('success', 'Catatan produksi selesai dan stok berkurang.');
    }

    public function cancel(ProductionLog $productionLog, StockService $stock)
    {
        try {
            $stock->cancelProduction($productionLog, auth()->id());
        } catch (RuntimeException $e) {
            return back()->withErrors(['stock' => $e->getMessage()]);
        }

        return back()->with('success', 'Catatan produksi dibatalkan dan stok dikembalikan.');
    }
}
