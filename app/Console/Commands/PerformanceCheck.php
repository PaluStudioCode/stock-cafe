<?php

namespace App\Console\Commands;

use App\Http\Controllers\ReportController;
use App\Models\Ingredient;
use App\Models\StockUsage;
use App\Models\StockUsageItem;
use App\Models\User;
use App\Services\StockService;
use App\Support\CafeStock;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Command\Command as CommandStatus;

class PerformanceCheck extends Command
{
    protected $signature = 'cafestock:performance-check {--json : Output machine-readable check results}';

    protected $description = 'Run MVP performance smoke checks for search, stock transaction submit, report filter, and report export.';

    public function handle(): int
    {
        $owner = User::where('role', User::ROLE_OWNER)->first();

        if (! $owner) {
            $this->error('Seed data belum tersedia. Jalankan php artisan migrate:fresh --seed terlebih dahulu.');

            return CommandStatus::FAILURE;
        }

        $checks = [
            $this->timeCheck('Search bahan', 1000, fn () => Ingredient::with(['category', 'unit'])
                ->where('name', 'like', '%Milk%')
                ->orWhere('code', 'like', '%Milk%')
                ->paginate(20)),
            $this->timeCheck('Submit transaksi stok', 2000, fn () => $this->simulateStockUsageSubmit($owner)),
            $this->timeCheck('Filter laporan', 3000, fn () => $this->renderReport($owner)),
            $this->timeCheck('Export laporan', 5000, fn () => $this->streamReportExport($owner)),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($checks, JSON_PRETTY_PRINT));
        } else {
            $this->table(['Check', 'Durasi ms', 'Target ms', 'Status'], array_map(fn (array $check) => [
                $check['name'],
                $check['duration_ms'],
                $check['target_ms'],
                $check['passed'] ? 'PASS' : 'FAIL',
            ], $checks));
        }

        return collect($checks)->every(fn (array $check) => $check['passed'])
            ? CommandStatus::SUCCESS
            : CommandStatus::FAILURE;
    }

    private function timeCheck(string $name, int $targetMs, Closure $callback): array
    {
        $started = hrtime(true);
        $callback();
        $durationMs = round((hrtime(true) - $started) / 1_000_000, 2);

        return [
            'name' => $name,
            'duration_ms' => $durationMs,
            'target_ms' => $targetMs,
            'passed' => $durationMs <= $targetMs,
        ];
    }

    private function simulateStockUsageSubmit(User $owner): void
    {
        DB::beginTransaction();

        try {
            $usage = StockUsage::create([
                'user_id' => $owner->id,
                'usage_code' => CafeStock::code('USE', 'stock_usages', 'usage_code', now()->toDateString()),
                'usage_date' => now(),
                'usage_type' => 'sample',
                'estimated_total_cost' => 0,
                'status' => 'draft',
                'notes' => 'Performance smoke check',
            ]);
            $ingredient = Ingredient::where('name', 'Gula Pasir')->firstOrFail();

            StockUsageItem::create([
                'stock_usage_id' => $usage->id,
                'ingredient_id' => $ingredient->id,
                'quantity' => 1,
                'unit_cost_snapshot' => $ingredient->last_unit_cost,
                'estimated_cost' => $ingredient->last_unit_cost,
            ]);

            app(StockService::class)->completeUsage($usage);
        } finally {
            DB::rollBack();
        }
    }

    private function renderReport(User $owner): void
    {
        $request = Request::create('/reports/stock-movement', 'GET', [
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-06',
            'type' => 'production_usage',
        ]);
        $request->setUserResolver(fn () => $owner);

        app(ReportController::class)->index($request, 'stock-movement');
    }

    private function streamReportExport(User $owner): void
    {
        DB::beginTransaction();

        try {
            $request = Request::create('/reports/stock-movement/export/xlsx', 'GET', [
                'date_from' => '2026-05-01',
                'date_to' => '2026-05-06',
                'type' => 'production_usage',
            ]);
            $request->setUserResolver(fn () => $owner);
            $response = app(ReportController::class)->export($request, 'stock-movement', 'xlsx');

            ob_start();
            $response->sendContent();
            ob_end_clean();
        } finally {
            DB::rollBack();
        }
    }
}
