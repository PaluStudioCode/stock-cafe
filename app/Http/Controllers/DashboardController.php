<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\ProductionLog;
use App\Models\PurchaseOrder;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\StockUsage;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Dashboard', $this->summary($request)->getData(true));
    }

    public function summary(Request $request)
    {
        $role = $request->user()->role;

        $data = match ($role) {
            'inventory_staff' => $this->inventorySummary(),
            'barista' => $this->baristaSummary($request),
            default => $this->ownerSummary(),
        };

        return response()->json($data + [
            'role' => $role,
            'refreshedAt' => now()->toIso8601String(),
        ]);
    }

    private function ownerSummary(): array
    {
        $today = now()->toDateString();

        return [
            'metrics' => [
                ['label' => 'Total Bahan', 'value' => Ingredient::count(), 'tone' => 'orange'],
                ['label' => 'Stok Menipis', 'value' => Ingredient::whereColumn('current_stock', '<=', 'minimum_stock')->count(), 'tone' => 'amber'],
                ['label' => 'Stok Habis', 'value' => Ingredient::where('current_stock', 0)->count(), 'tone' => 'rose'],
                ['label' => 'Pembelian Bulan Ini', 'value' => (float) PurchaseOrder::where('status', 'received')->whereMonth('purchase_date', now()->month)->sum('total_amount'), 'format' => 'currency', 'tone' => 'emerald'],
                ['label' => 'Produksi Hari Ini', 'value' => (float) StockMovement::where('type', 'production_usage')->whereDate('created_at', $today)->sum('quantity_out'), 'format' => 'decimal', 'tone' => 'sky'],
                ['label' => 'Bahan Terbuang Bulan Ini', 'value' => (float) StockMovement::where('type', 'waste')->whereMonth('created_at', now()->month)->sum('quantity_out'), 'format' => 'decimal', 'tone' => 'red'],
                ['label' => 'Nilai Persediaan', 'value' => (float) Ingredient::selectRaw('sum(current_stock * last_unit_cost) as total')->value('total'), 'format' => 'currency', 'tone' => 'slate'],
            ],
            'lowStock' => Ingredient::with(['category', 'unit'])->whereColumn('current_stock', '<=', 'minimum_stock')->orderBy('current_stock')->limit(8)->get(),
            'recentActivities' => ActivityLog::with('user')->latest()->limit(8)->get(),
            'recentPurchaseOrders' => PurchaseOrder::with('supplier')->latest()->limit(5)->get(),
            'recentStockUsages' => StockUsage::with('user')->latest()->limit(5)->get(),
            'draftAdjustments' => StockAdjustment::where('status', 'draft')->latest()->limit(5)->get(),
            'activeMenus' => MenuItem::where('is_active', true)->count(),
            'productionLogs' => [],
        ];
    }

    private function inventorySummary(): array
    {
        return [
            'metrics' => [
                ['label' => 'Total Bahan', 'value' => Ingredient::count(), 'tone' => 'orange'],
                ['label' => 'Stok Menipis', 'value' => Ingredient::whereColumn('current_stock', '<=', 'minimum_stock')->count(), 'tone' => 'amber'],
                ['label' => 'Stok Habis', 'value' => Ingredient::where('current_stock', 0)->count(), 'tone' => 'rose'],
                ['label' => 'Draf Penyesuaian', 'value' => StockAdjustment::where('status', 'draft')->count(), 'tone' => 'sky'],
            ],
            'lowStock' => Ingredient::with(['category', 'unit'])->whereColumn('current_stock', '<=', 'minimum_stock')->orderBy('current_stock')->limit(8)->get(),
            'recentActivities' => [],
            'recentPurchaseOrders' => PurchaseOrder::with('supplier')->latest('id')->limit(6)->get(),
            'recentStockUsages' => StockUsage::with('user')->latest('id')->limit(6)->get(),
            'draftAdjustments' => StockAdjustment::with('user')->where('status', 'draft')->latest('id')->limit(6)->get(),
            'activeMenus' => MenuItem::where('is_active', true)->count(),
            'productionLogs' => [],
        ];
    }

    private function baristaSummary(Request $request): array
    {
        return [
            'metrics' => [
                ['label' => 'Bahan Perlu Diperhatikan', 'value' => Ingredient::whereColumn('current_stock', '<=', 'minimum_stock')->count(), 'tone' => 'amber'],
                ['label' => 'Bahan Habis', 'value' => Ingredient::where('current_stock', 0)->count(), 'tone' => 'rose'],
                ['label' => 'Menu Aktif', 'value' => MenuItem::where('is_active', true)->count(), 'tone' => 'orange'],
                ['label' => 'Catatan Produksi Saya', 'value' => ProductionLog::where('user_id', $request->user()->id)->count(), 'tone' => 'sky'],
            ],
            'lowStock' => Ingredient::with(['category', 'unit'])->whereColumn('current_stock', '<=', 'minimum_stock')->orderBy('current_stock')->limit(8)->get(),
            'recentActivities' => [],
            'recentPurchaseOrders' => [],
            'recentStockUsages' => [],
            'draftAdjustments' => [],
            'activeMenus' => MenuItem::where('is_active', true)->count(),
            'productionLogs' => ProductionLog::with('menuItem')
                ->where('user_id', $request->user()->id)
                ->latest('production_date')
                ->limit(6)
                ->get(),
        ];
    }
}
