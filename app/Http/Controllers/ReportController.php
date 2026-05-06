<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\MenuItem;
use App\Models\ProductionLog;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\StockMovement;
use App\Models\StockUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ReportController extends Controller
{
    private const TRANSACTION_REPORTS = ['purchases', 'production', 'waste', 'stock-movement'];

    private const EXPORT_FORMATS = ['pdf', 'xlsx'];

    private const MOVEMENT_TYPES = [
        'opening_stock', 'purchase_receipt', 'production_usage', 'manual_usage', 'waste',
        'adjustment_in', 'adjustment_out', 'cancel_production', 'cancel_usage', 'cancel_adjustment',
    ];

    private const USAGE_TYPES = ['waste', 'expired', 'damaged', 'internal_use', 'sample', 'other'];

    private const LABELS = [
        'draft' => 'Draf',
        'received' => 'Diterima',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
        'waste' => 'Terbuang',
        'expired' => 'Kedaluwarsa',
        'damaged' => 'Rusak',
        'internal_use' => 'Pemakaian internal',
        'sample' => 'Sampel',
        'other' => 'Lainnya',
        'opening_stock' => 'Stok awal',
        'purchase_receipt' => 'Penerimaan pembelian',
        'production_usage' => 'Pemakaian produksi',
        'manual_usage' => 'Pemakaian manual',
        'adjustment_in' => 'Penyesuaian masuk',
        'adjustment_out' => 'Penyesuaian keluar',
        'cancel_production' => 'Pembatalan produksi',
        'cancel_usage' => 'Pembatalan pemakaian',
        'cancel_adjustment' => 'Pembatalan penyesuaian',
        'purchase_orders' => 'Pesanan pembelian',
        'production_logs' => 'Catatan produksi',
        'stock_usages' => 'Pemakaian stok',
        'stock_adjustments' => 'Penyesuaian stok',
    ];

    public function index(Request $request, ?string $report = null)
    {
        $report = $report ?: $request->string('report')->toString() ?: 'stock';
        $this->ensureReportExists($report);
        $filters = $this->filters($report, $request);
        $paginationQuery = $this->activeFilters($filters);

        if (! $request->route('report')) {
            $paginationQuery['report'] = $report;
        }

        return Inertia::render('Reports/Index', [
            'report' => $report,
            'title' => 'Laporan',
            'activeReportTitle' => $this->title($report),
            'reports' => $this->reportTabs(),
            'rows' => $this->query($report, $filters)->paginate(20)->appends($paginationQuery),
            'filters' => $filters,
            'lookups' => $this->lookups(),
            'columns' => $this->columns($report),
            'requiresDateFilter' => in_array($report, self::TRANSACTION_REPORTS, true),
        ]);
    }

    public function export(Request $request, string $report, string $format): StreamedResponse
    {
        $this->ensureReportExists($report);
        abort_unless(in_array($format, self::EXPORT_FORMATS, true), 404);

        $filters = $this->filters($report, $request);
        $rows = $this->query($report, $filters)->limit(5000)->get();
        $headers = $this->exportHeaders($report);
        $exportRows = $this->exportRows($report, $rows);
        $filename = $this->filename($report, $format, $filters);
        $content = $format === 'pdf'
            ? $this->buildPdf($this->title($report), $headers, $exportRows, $filters)
            : $this->buildXlsx($this->title($report), $headers, $exportRows);
        $contentType = $format === 'pdf'
            ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'export_report',
            'module' => 'reports',
            'description' => "Ekspor {$this->title($report)} dengan filter ".json_encode($this->activeFilters($filters)),
            'reference_type' => $report,
            'ip_address' => $request->ip(),
        ]);

        return response()->streamDownload(fn () => print($content), $filename, [
            'Cache-Control' => 'no-store',
            'Content-Type' => $contentType,
        ]);
    }

    private function query(string $report, array $filters): Builder
    {
        return match ($report) {
            'stock' => $this->ingredientReportQuery($filters)->latest('id'),
            'low-stock' => $this->ingredientReportQuery($filters)
                ->whereColumn('current_stock', '<=', 'minimum_stock')
                ->latest('id'),
            'inventory-value' => $this->ingredientReportQuery($filters)
                ->select('ingredients.*')
                ->selectRaw('(current_stock * last_unit_cost) as inventory_value')
                ->latest('id'),
            'purchases' => PurchaseOrder::with(['supplier', 'user'])
                ->whereDate('purchase_date', '>=', $filters['date_from'])
                ->whereDate('purchase_date', '<=', $filters['date_to'])
                ->when($filters['supplier_id'] !== '', fn (Builder $query) => $query->where('supplier_id', $filters['supplier_id']))
                ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
                ->latest('purchase_date')
                ->latest('id'),
            'production' => ProductionLog::with(['menuItem', 'user'])
                ->whereDate('production_date', '>=', $filters['date_from'])
                ->whereDate('production_date', '<=', $filters['date_to'])
                ->when($filters['menu_item_id'] !== '', fn (Builder $query) => $query->where('menu_item_id', $filters['menu_item_id']))
                ->when($filters['user_id'] !== '', fn (Builder $query) => $query->where('user_id', $filters['user_id']))
                ->when(
                    $filters['status'] !== '',
                    fn (Builder $query) => $query->where('status', $filters['status']),
                    fn (Builder $query) => $query->where('status', '!=', 'cancelled')
                )
                ->latest('production_date')
                ->latest('id'),
            'waste' => StockUsage::with('user')
                ->whereDate('usage_date', '>=', $filters['date_from'])
                ->whereDate('usage_date', '<=', $filters['date_to'])
                ->when($filters['usage_type'] !== '', fn (Builder $query) => $query->where('usage_type', $filters['usage_type']))
                ->when(
                    $filters['status'] !== '',
                    fn (Builder $query) => $query->where('status', $filters['status']),
                    fn (Builder $query) => $query->where('status', '!=', 'cancelled')
                )
                ->latest('usage_date')
                ->latest('id'),
            'stock-movement' => StockMovement::with(['ingredient.category', 'ingredient.unit', 'user'])
                ->whereDate('created_at', '>=', $filters['date_from'])
                ->whereDate('created_at', '<=', $filters['date_to'])
                ->when($filters['ingredient_id'] !== '', fn (Builder $query) => $query->where('ingredient_id', $filters['ingredient_id']))
                ->when($filters['category_id'] !== '', fn (Builder $query) => $query->whereHas('ingredient', fn (Builder $ingredient) => $ingredient->where('ingredient_category_id', $filters['category_id'])))
                ->when($filters['type'] !== '', fn (Builder $query) => $query->where('type', $filters['type']))
                ->latest('id'),
            default => abort(404),
        };
    }

    private function ingredientReportQuery(array $filters): Builder
    {
        return Ingredient::with(['category', 'unit', 'supplier'])
            ->when($filters['category_id'] !== '', fn (Builder $query) => $query->where('ingredient_category_id', $filters['category_id']))
            ->when($filters['supplier_id'] !== '', fn (Builder $query) => $query->where('primary_supplier_id', $filters['supplier_id']));
    }

    private function filters(string $report, Request $request): array
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'max:80'],
            'usage_type' => ['nullable', 'string', 'max:80'],
            'supplier_id' => ['nullable', 'integer'],
            'menu_item_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'ingredient_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
        ]);

        $filters = array_fill_keys([
            'date_from', 'date_to', 'status', 'type', 'usage_type',
            'supplier_id', 'menu_item_id', 'user_id', 'ingredient_id', 'category_id',
        ], '');

        foreach ($filters as $key => $value) {
            if (array_key_exists($key, $validated) && $validated[$key] !== null) {
                $filters[$key] = (string) $validated[$key];
            }
        }

        if ($report === 'waste' && $filters['usage_type'] === '' && $filters['type'] !== '') {
            $filters['usage_type'] = $filters['type'];
            $filters['type'] = '';
        }

        if (in_array($report, self::TRANSACTION_REPORTS, true)) {
            $filters['date_from'] = $filters['date_from'] !== ''
                ? Carbon::parse($filters['date_from'])->toDateString()
                : now()->startOfMonth()->toDateString();
            $filters['date_to'] = $filters['date_to'] !== ''
                ? Carbon::parse($filters['date_to'])->toDateString()
                : now()->toDateString();
        } else {
            $filters['date_from'] = $filters['date_from'] !== '' ? Carbon::parse($filters['date_from'])->toDateString() : '';
            $filters['date_to'] = $filters['date_to'] !== '' ? Carbon::parse($filters['date_to'])->toDateString() : '';
        }

        return $filters;
    }

    private function lookups(): array
    {
        return [
            'categories' => IngredientCategory::orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'menuItems' => MenuItem::orderBy('name')->get(['id', 'name', 'code']),
            'users' => User::orderBy('name')->get(['id', 'name', 'role']),
            'ingredients' => Ingredient::with('unit')->orderBy('name')->get(['id', 'name', 'code', 'unit_id', 'ingredient_category_id']),
            'movementTypes' => collect(self::MOVEMENT_TYPES)->map(fn (string $type) => ['value' => $type, 'label' => $this->label($type)])->values(),
            'usageTypes' => collect(self::USAGE_TYPES)->map(fn (string $type) => ['value' => $type, 'label' => $this->label($type)])->values(),
            'statuses' => [
                'purchases' => [
                    ['value' => 'draft', 'label' => 'Draf'],
                    ['value' => 'received', 'label' => 'Diterima'],
                ],
                'production' => [
                    ['value' => 'completed', 'label' => 'Selesai'],
                    ['value' => 'cancelled', 'label' => 'Dibatalkan'],
                ],
                'waste' => [
                    ['value' => 'draft', 'label' => 'Draf'],
                    ['value' => 'completed', 'label' => 'Selesai'],
                    ['value' => 'cancelled', 'label' => 'Dibatalkan'],
                ],
            ],
        ];
    }

    private function columns(string $report): array
    {
        return [
            'stock' => [
                ['key' => 'code', 'label' => 'Kode'],
                ['key' => 'name', 'label' => 'Nama'],
                ['key' => 'category.name', 'label' => 'Kategori'],
                ['key' => 'unit.symbol', 'label' => 'Satuan'],
                ['key' => 'supplier.name', 'label' => 'Supplier'],
                ['key' => 'current_stock', 'label' => 'Stok'],
                ['key' => 'minimum_stock', 'label' => 'Minimum'],
                ['key' => 'reorder_level', 'label' => 'Reorder'],
                ['key' => 'last_unit_cost', 'label' => 'Harga Modal'],
            ],
            'low-stock' => [
                ['key' => 'code', 'label' => 'Kode'],
                ['key' => 'name', 'label' => 'Nama'],
                ['key' => 'category.name', 'label' => 'Kategori'],
                ['key' => 'unit.symbol', 'label' => 'Satuan'],
                ['key' => 'current_stock', 'label' => 'Stok'],
                ['key' => 'minimum_stock', 'label' => 'Minimum'],
                ['key' => 'reorder_level', 'label' => 'Reorder'],
            ],
            'purchases' => [
                ['key' => 'purchase_code', 'label' => 'Kode PO'],
                ['key' => 'purchase_date', 'label' => 'Tanggal'],
                ['key' => 'supplier.name', 'label' => 'Supplier'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'subtotal', 'label' => 'Subtotal'],
                ['key' => 'discount', 'label' => 'Diskon'],
                ['key' => 'total_amount', 'label' => 'Total'],
            ],
            'production' => [
                ['key' => 'production_code', 'label' => 'Kode Produksi'],
                ['key' => 'production_date', 'label' => 'Tanggal'],
                ['key' => 'menu_item.name', 'label' => 'Menu'],
                ['key' => 'user.name', 'label' => 'Pengguna'],
                ['key' => 'quantity', 'label' => 'Jumlah'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'estimated_total_cost', 'label' => 'Estimasi Biaya'],
            ],
            'waste' => [
                ['key' => 'usage_code', 'label' => 'Kode Usage'],
                ['key' => 'usage_date', 'label' => 'Tanggal'],
                ['key' => 'usage_type', 'label' => 'Jenis Penggunaan'],
                ['key' => 'user.name', 'label' => 'Pengguna'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'estimated_total_cost', 'label' => 'Estimasi Biaya'],
            ],
            'stock-movement' => [
                ['key' => 'created_at', 'label' => 'Tanggal'],
                ['key' => 'ingredient.name', 'label' => 'Bahan'],
                ['key' => 'ingredient.category.name', 'label' => 'Kategori'],
                ['key' => 'type', 'label' => 'Tipe'],
                ['key' => 'reference_type', 'label' => 'Referensi'],
                ['key' => 'quantity_in', 'label' => 'Masuk'],
                ['key' => 'quantity_out', 'label' => 'Keluar'],
                ['key' => 'stock_before', 'label' => 'Stok Sebelum'],
                ['key' => 'stock_after', 'label' => 'Stok Sesudah'],
                ['key' => 'user.name', 'label' => 'Pengguna'],
            ],
            'inventory-value' => [
                ['key' => 'code', 'label' => 'Kode'],
                ['key' => 'name', 'label' => 'Nama'],
                ['key' => 'category.name', 'label' => 'Kategori'],
                ['key' => 'unit.symbol', 'label' => 'Satuan'],
                ['key' => 'current_stock', 'label' => 'Stok'],
                ['key' => 'last_unit_cost', 'label' => 'Harga Modal'],
                ['key' => 'inventory_value', 'label' => 'Nilai Persediaan'],
            ],
        ][$report];
    }

    private function exportHeaders(string $report): array
    {
        return collect($this->columns($report))->pluck('label')->all();
    }

    private function exportRows(string $report, Collection $rows): array
    {
        return match ($report) {
            'stock' => $rows->map(fn (Ingredient $row) => [
                $row->code,
                $row->name,
                $row->category?->name,
                $row->unit?->symbol,
                $row->supplier?->name,
                $this->decimal($row->current_stock, 3),
                $this->decimal($row->minimum_stock, 3),
                $this->decimal($row->reorder_level, 3),
                $this->decimal($row->last_unit_cost, 2),
            ])->all(),
            'low-stock' => $rows->map(fn (Ingredient $row) => [
                $row->code,
                $row->name,
                $row->category?->name,
                $row->unit?->symbol,
                $this->decimal($row->current_stock, 3),
                $this->decimal($row->minimum_stock, 3),
                $this->decimal($row->reorder_level, 3),
            ])->all(),
            'inventory-value' => $rows->map(fn (Ingredient $row) => [
                $row->code,
                $row->name,
                $row->category?->name,
                $row->unit?->symbol,
                $this->decimal($row->current_stock, 3),
                $this->decimal($row->last_unit_cost, 2),
                $this->decimal($row->inventory_value, 2),
            ])->all(),
            'purchases' => $rows->map(fn (PurchaseOrder $row) => [
                $row->purchase_code,
                $this->formatDate($row->purchase_date),
                $row->supplier?->name,
                $this->label($row->status),
                $this->decimal($row->subtotal, 2),
                $this->decimal($row->discount, 2),
                $this->decimal($row->total_amount, 2),
            ])->all(),
            'production' => $rows->map(fn (ProductionLog $row) => [
                $row->production_code,
                $this->formatDate($row->production_date, 'Y-m-d H:i'),
                $row->menuItem?->name,
                $row->user?->name,
                $this->decimal($row->quantity, 3),
                $this->label($row->status),
                $this->decimal($row->estimated_total_cost, 2),
            ])->all(),
            'waste' => $rows->map(fn (StockUsage $row) => [
                $row->usage_code,
                $this->formatDate($row->usage_date, 'Y-m-d H:i'),
                $this->label($row->usage_type),
                $row->user?->name,
                $this->label($row->status),
                $this->decimal($row->estimated_total_cost, 2),
            ])->all(),
            'stock-movement' => $rows->map(fn (StockMovement $row) => [
                $this->formatDate($row->created_at, 'Y-m-d H:i'),
                $row->ingredient?->name,
                $row->ingredient?->category?->name,
                $this->label($row->type),
                $this->label($row->reference_type),
                $this->decimal($row->quantity_in, 3),
                $this->decimal($row->quantity_out, 3),
                $this->decimal($row->stock_before, 3),
                $this->decimal($row->stock_after, 3),
                $row->user?->name,
            ])->all(),
            default => abort(404),
        };
    }

    private function filename(string $report, string $format, array $filters): string
    {
        $date = in_array($report, self::TRANSACTION_REPORTS, true)
            ? "{$filters['date_from']}_{$filters['date_to']}"
            : now()->format('Y-m-d');

        return "laporan-{$report}-{$date}.{$format}";
    }

    private function activeFilters(array $filters): array
    {
        return collect($filters)->filter(fn ($value) => $value !== '' && $value !== null)->all();
    }

    private function buildXlsx(string $title, array $headers, array $rows): string
    {
        if (! class_exists(ZipArchive::class)) {
            return $this->buildCsv($headers, $rows);
        }

        $path = tempnam(sys_get_temp_dir(), 'cafestock-report-');
        $zip = new ZipArchive();

        if ($path === false || $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return $this->buildCsv($headers, $rows);
        }

        $sheetRows = [[$title], [], $headers, ...$rows];
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($sheetRows as $rowIndex => $row) {
            $sheetXml .= '<row r="'.($rowIndex + 1).'">';
            foreach (array_values($row) as $columnIndex => $value) {
                $sheetXml .= '<c r="'.$this->cellReference($columnIndex + 1, $rowIndex + 1).'" t="inlineStr"><is><t>'
                    .htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8')
                    .'</t></is></c>';
            }
            $sheetXml .= '</row>';
        }

        $sheetXml .= '</sheetData></worksheet>';

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Laporan" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        $content = file_get_contents($path);
        @unlink($path);

        return $content !== false ? $content : $this->buildCsv($headers, $rows);
    }

    private function buildCsv(array $headers, array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return (string) $content;
    }

    private function buildPdf(string $title, array $headers, array $rows, array $filters): string
    {
        $lines = [$title];

        if ($activeFilters = $this->activeFilters($filters)) {
            $lines[] = 'Filter: '.json_encode($activeFilters);
        }

        $lines[] = implode(' | ', $headers);
        foreach ($rows as $row) {
            $lines[] = implode(' | ', array_map(fn ($value) => (string) $value, $row));
        }

        return $this->plainTextPdf($lines);
    }

    private function plainTextPdf(array $lines): string
    {
        $pages = array_chunk($lines, 38);
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pageIds = [];
        $nextId = 4;

        foreach ($pages as $pageLines) {
            $contentId = $nextId++;
            $pageId = $nextId++;
            $stream = "BT\n/F1 8 Tf\n36 555 Td\n13 TL\n";

            foreach ($pageLines as $line) {
                $stream .= '('.$this->escapePdfText(substr($line, 0, 170)).") Tj\nT*\n";
            }

            $stream .= "ET";
            $objects[$contentId] = "<< /Length ".strlen($stream)." >>\nstream\n{$stream}\nendstream";
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentId} 0 R >>";
            $pageIds[] = $pageId;
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', array_map(fn ($id) => "{$id} 0 R", $pageIds)).'] /Count '.count($pageIds).' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $maxId = max(array_keys($objects));
        $pdf .= "xref\n0 ".($maxId + 1)."\n0000000000 65535 f \n";

        for ($id = 1; $id <= $maxId; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }

        return $pdf."trailer\n<< /Size ".($maxId + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }

    private function cellReference(int $column, int $row): string
    {
        $letters = '';

        while ($column > 0) {
            $mod = ($column - 1) % 26;
            $letters = chr(65 + $mod).$letters;
            $column = intdiv($column - $mod - 1, 26);
        }

        return $letters.$row;
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(["\\", '(', ')', "\r", "\n"], ["\\\\", "\\(", "\\)", ' ', ' '], $text);
    }

    private function formatDate($value, string $format = 'Y-m-d'): string
    {
        if (! $value) {
            return '';
        }

        return $value instanceof \DateTimeInterface
            ? $value->format($format)
            : Carbon::parse((string) $value)->format($format);
    }

    private function decimal($value, int $precision): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return number_format((float) $value, $precision, '.', '');
    }

    private function label(string $value): string
    {
        return self::LABELS[$value] ?? str($value)->replace('_', ' ')->title()->toString();
    }

    private function ensureReportExists(string $report): void
    {
        abort_unless(array_key_exists($report, $this->titles()), 404);
    }

    private function title(string $report): string
    {
        return $this->titles()[$report] ?? 'Laporan';
    }

    private function titles(): array
    {
        return [
            'stock' => 'Laporan Stok',
            'low-stock' => 'Laporan Stok Menipis',
            'purchases' => 'Laporan Pembelian',
            'production' => 'Laporan Produksi',
            'waste' => 'Laporan Waste dan Pemakaian',
            'stock-movement' => 'Laporan Pergerakan Stok',
            'inventory-value' => 'Laporan Nilai Persediaan',
        ];
    }

    private function reportTabs(): array
    {
        return collect($this->titles())->map(fn (string $label, string $key) => [
            'key' => $key,
            'label' => $label,
        ])->values()->all();
    }
}
