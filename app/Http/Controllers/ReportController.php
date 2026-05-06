<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Ingredient;
use App\Models\ProductionLog;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\StockUsage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, string $report)
    {
        return Inertia::render('Reports/Index', [
            'report' => $report,
            'title' => $this->title($report),
            'rows' => $this->query($report, $request)->paginate(20)->withQueryString(),
            'filters' => $request->only('date_from', 'date_to', 'status', 'type'),
        ]);
    }

    public function export(Request $request, string $report, string $format): StreamedResponse
    {
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);

        $rows = $this->query($report, $request)->limit(5000)->get();
        $filename = sprintf('laporan-%s-%s.%s', $report, now()->format('Y-m-d'), $format);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'export_report',
            'module' => 'reports',
            'description' => "Export {$this->title($report)}",
            'reference_type' => $report,
            'ip_address' => $request->ip(),
        ]);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'code/name', 'status/type', 'date', 'quantity/amount']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->id,
                    $row->code ?? $row->purchase_code ?? $row->production_code ?? $row->usage_code ?? $row->type ?? $row->name ?? '',
                    $row->status ?? $row->type ?? '',
                    $row->purchase_date ?? $row->production_date ?? $row->usage_date ?? $row->created_at ?? '',
                    $row->total_amount ?? $row->estimated_total_cost ?? $row->current_stock ?? $row->stock_after ?? '',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function query(string $report, Request $request)
    {
        $from = $request->date('date_from')?->startOfDay();
        $to = $request->date('date_to')?->endOfDay();

        return match ($report) {
            'stock' => Ingredient::with(['category', 'unit'])->latest('id'),
            'low-stock' => Ingredient::with(['category', 'unit'])->whereColumn('current_stock', '<=', 'minimum_stock')->latest('id'),
            'inventory-value' => Ingredient::with(['category', 'unit'])->select('*')->selectRaw('(current_stock * last_unit_cost) as inventory_value')->latest('id'),
            'purchases' => PurchaseOrder::with('supplier')->when($from, fn ($q) => $q->where('purchase_date', '>=', $from))->when($to, fn ($q) => $q->where('purchase_date', '<=', $to))->when($request->status, fn ($q, $s) => $q->where('status', $s))->latest('id'),
            'production' => ProductionLog::with(['menuItem', 'user'])->when($from, fn ($q) => $q->where('production_date', '>=', $from))->when($to, fn ($q) => $q->where('production_date', '<=', $to))->when($request->status, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->where('status', '!=', 'cancelled'))->latest('id'),
            'waste' => StockUsage::with('user')->when($from, fn ($q) => $q->where('usage_date', '>=', $from))->when($to, fn ($q) => $q->where('usage_date', '<=', $to))->when($request->type, fn ($q, $t) => $q->where('usage_type', $t))->when($request->status, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->where('status', '!=', 'cancelled'))->latest('id'),
            'stock-movement' => StockMovement::with(['ingredient', 'user'])->when($from, fn ($q) => $q->where('created_at', '>=', $from))->when($to, fn ($q) => $q->where('created_at', '<=', $to))->when($request->type, fn ($q, $t) => $q->where('type', $t))->latest('id'),
            default => abort(404),
        };
    }

    private function title(string $report): string
    {
        return [
            'stock' => 'Laporan Stok',
            'low-stock' => 'Laporan Stok Menipis',
            'purchases' => 'Laporan Pembelian',
            'production' => 'Laporan Produksi',
            'waste' => 'Laporan Waste dan Usage',
            'stock-movement' => 'Laporan Stock Movement',
            'inventory-value' => 'Laporan Nilai Persediaan',
        ][$report] ?? 'Laporan';
    }
}
