<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\MenuItem;
use App\Models\RecipeItem;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Support\CafeStock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DataController extends Controller
{
    private const REFERENCE_TYPES = [
        'opening_stock',
        'purchase_orders',
        'production_logs',
        'stock_usages',
        'stock_adjustments',
    ];

    private const MENU_CATEGORIES = [
        'Coffee',
        'Non Coffee',
        'Manual Brew',
        'Pastry',
    ];

    private const ALLOWED_SETTING_KEYS = [
        'cafe_name',
        'cafe_tagline',
        'cafe_address',
        'cafe_phone',
        'timezone',
        'default_minimum_stock',
        'table_per_page',
        'report_export_formats',
        'upload_max_file_size_mb',
    ];

    private const SETTING_DEFINITIONS = [
        'cafe_name' => ['label' => 'Nama cafe', 'type' => 'text', 'description' => 'Nama cafe'],
        'cafe_tagline' => ['label' => 'Tagline cafe', 'type' => 'text', 'description' => 'Tagline cafe'],
        'cafe_address' => ['label' => 'Alamat cafe', 'type' => 'textarea', 'description' => 'Alamat cafe'],
        'cafe_phone' => ['label' => 'Telepon cafe', 'type' => 'text', 'description' => 'Nomor kontak cafe'],
        'timezone' => ['label' => 'Zona waktu', 'type' => 'select', 'description' => 'Timezone aplikasi', 'options' => ['Asia/Makassar', 'Asia/Jakarta', 'Asia/Jayapura']],
        'default_minimum_stock' => ['label' => 'Stok minimum default', 'type' => 'number', 'description' => 'Default minimum stock bahan baru', 'min' => 0, 'step' => '0.001'],
        'table_per_page' => ['label' => 'Data per halaman', 'type' => 'number', 'description' => 'Jumlah data default per halaman', 'min' => 5, 'step' => 1],
        'report_export_formats' => ['label' => 'Format export laporan', 'type' => 'formats', 'description' => 'Format export laporan yang didukung', 'options' => ['pdf', 'xlsx']],
        'upload_max_file_size_mb' => ['label' => 'Maksimal upload file (MB)', 'type' => 'number', 'description' => 'Batas maksimal upload file', 'min' => 1, 'step' => 1],
    ];

    private array $resources = [
        'ingredient-categories' => [IngredientCategory::class, 'Kategori Bahan', ['name', 'description']],
        'units' => [Unit::class, 'Satuan', ['name', 'symbol']],
        'suppliers' => [Supplier::class, 'Supplier', ['name', 'phone', 'address', 'notes', 'is_active']],
        'ingredients' => [Ingredient::class, 'Bahan Baku', ['code', 'name', 'ingredient_category_id', 'unit_id', 'primary_supplier_id', 'last_unit_cost', 'current_stock', 'minimum_stock', 'reorder_level', 'is_active']],
        'menu-items' => [MenuItem::class, 'Menu', ['code', 'name', 'category', 'selling_price', 'is_active']],
        'recipe-items' => [RecipeItem::class, 'Resep Menu', ['menu_item_id', 'ingredient_id', 'quantity_per_serving', 'notes']],
        'stock-movements' => [StockMovement::class, 'Riwayat Pergerakan Stok', []],
        'activity-logs' => [ActivityLog::class, 'Log Aktivitas', []],
        'users' => [User::class, 'Pengguna', ['name', 'email', 'role', 'is_active', 'password']],
        'settings' => [Setting::class, 'Pengaturan', ['key', 'value', 'description']],
    ];

    public function index(Request $request, string $resource)
    {
        if ($request->routeIs('monitoring.index') && in_array($resource, ['low-stock', 'out-of-stock'], true)) {
            $query = array_merge($request->query(), [
                'resource' => 'ingredients',
                'stock_status' => $resource === 'low-stock' ? 'low' : 'out',
            ]);

            return redirect()->route('monitoring.index', $query);
        }

        [$model, $title] = $this->resource($resource);

        if ($resource === 'settings') {
            return $this->settingsIndex($title);
        }

        $query = $model::query();
        $title = $request->routeIs('monitoring.index') && $resource === 'ingredients' ? 'Monitoring Stok' : $title;

        if (method_exists($model, 'category')) {
            $query->with(['category', 'unit', 'supplier']);
        }
        if ($model === MenuItem::class) {
            $query->withCount('recipeItems');
        }
        if ($model === RecipeItem::class) {
            $query->with(['menuItem', 'ingredient.unit']);
        }
        if ($model === StockMovement::class) {
            $query->with(['ingredient.category', 'user']);
            $this->applyStockMovementFilters($query, $request);
        }
        if ($model === ActivityLog::class) {
            $query->with('user');
        }
        if ($model === Ingredient::class) {
            $this->applyIngredientFilters($query, $request);
        }

        $search = $request->string('search')->toString();
        if ($search !== '') {
            $query->where(function ($q) use ($model, $search) {
                foreach (['name', 'code', 'purchase_code', 'production_code', 'usage_code', 'adjustment_code', 'key', 'description', 'reference_type', 'notes'] as $column) {
                    if ($this->hasColumn($model, $column)) {
                        $q->orWhere($column, 'like', "%{$search}%");
                    }
                }
            });
        }

        return Inertia::render('Generic/Index', [
            'resource' => $resource,
            'title' => $title,
            'rows' => $query->latest('id')->paginate((int) Setting::where('key', 'table_per_page')->value('value') ?: 20)->withQueryString(),
            'filters' => $request->only('search', 'type', 'ingredient_id', 'category_id', 'supplier_id', 'is_active', 'stock_status', 'reference_type', 'date_from', 'date_to'),
            'lookups' => $this->lookups(),
            'readonly' => $request->routeIs('monitoring.*') || in_array($resource, ['stock-movements', 'activity-logs'], true),
        ]);
    }

    public function store(Request $request, string $resource)
    {
        [$model, , $fields] = $this->resource($resource);
        $data = $this->validated($request, $resource);

        if ($resource === 'ingredients' && empty($data['code'])) {
            $data['code'] = CafeStock::code('ING', 'ingredients', 'code');
        }
        if ($resource === 'menu-items' && empty($data['code'])) {
            $data['code'] = CafeStock::code('MENU', 'menu_items', 'code');
        }
        if ($resource === 'settings') {
            $this->rejectSensitiveSettings($data);
        }
        if ($resource === 'users') {
            $this->assertUserChangeAllowed(null, $data);
        }
        if ($resource === 'users' && ! empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        if ($resource === 'ingredients') {
            $row = DB::transaction(function () use ($data, $fields, $model, $request) {
                $opening = (float) ($data['current_stock'] ?? 0);
                $data['current_stock'] = 0;
                $ingredient = $model::create(array_intersect_key($data, array_flip($fields)));
                if ($opening > 0) {
                    app(\App\Services\StockService::class)->move($ingredient, $request->user()->id, 'opening_stock', 'opening_stock', null, $opening, 0, (float) $ingredient->last_unit_cost, 'Stok awal');
                }

                return $ingredient;
            });
        } else {
            $row = $model::create(array_intersect_key($data, array_flip($fields)));
        }

        $this->logResourceChange($request, 'create', $resource, $row);

        return back()->with('success', 'Data tersimpan.');
    }

    public function update(Request $request, string $resource, int $id)
    {
        [$model, , $fields] = $this->resource($resource);
        $data = $this->validated($request, $resource, $id);
        $row = $model::findOrFail($id);

        if ($resource === 'users' && empty($data['password'])) {
            unset($data['password']);
        }
        $beforeUnitCost = $resource === 'ingredients' ? (float) $row->last_unit_cost : null;
        if ($resource === 'ingredients') {
            $this->rejectDirectStockUpdate($row, $data);
            unset($data['current_stock']);
        }
        if ($resource === 'menu-items') {
            abort_if($this->hasReferences($resource, $id), 422, 'Menu sudah memiliki catatan produksi dan tidak dapat diubah.');
        }
        if ($resource === 'users') {
            $this->assertUserChangeAllowed($row, $data);
            if (! empty($data['password'])) {
                $data['password'] = bcrypt($data['password']);
            }
        }
        if ($resource === 'settings') {
            $this->rejectSensitiveSettings($data);
        }
        $row->update(array_intersect_key($data, array_flip($fields)));
        $row->refresh();

        $this->logResourceChange($request, 'update', $resource, $row);
        if ($resource === 'ingredients' && abs($beforeUnitCost - (float) $row->last_unit_cost) > 0.005) {
            $this->logUnitCostChange($request, $row, $beforeUnitCost, (float) $row->last_unit_cost);
        }

        return back()->with('success', 'Data diperbarui.');
    }

    public function updateSettings(Request $request)
    {
        $settings = $this->validatedSettings($request);

        DB::transaction(function () use ($request, $settings) {
            foreach (self::ALLOWED_SETTING_KEYS as $key) {
                $definition = self::SETTING_DEFINITIONS[$key];
                $row = Setting::updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => $settings[$key],
                        'description' => $definition['description'],
                    ]
                );

                $this->logResourceChange($request, 'update', 'settings', $row);
            }
        });

        return back()->with('success', 'Pengaturan diperbarui.');
    }

    public function destroy(string $resource, int $id)
    {
        [$model] = $this->resource($resource);
        $row = $model::findOrFail($id);

        if (in_array($resource, ['ingredient-categories', 'units', 'suppliers', 'ingredients', 'menu-items', 'users'], true)) {
            abort_if($this->hasReferences($resource, $id), 422, 'Data sudah terkait transaksi dan tidak dapat dihapus.');
        }
        if ($resource === 'users') {
            $this->assertUserCanBeDeleted($row);
        }

        $row->delete();
        $this->logResourceChange(request(), 'delete', $resource, $row);

        return back()->with('success', 'Data dihapus.');
    }

    private function resource(string $resource): array
    {
        abort_unless(isset($this->resources[$resource]), 404);

        return $this->resources[$resource];
    }

    private function settingsIndex(string $title)
    {
        return Inertia::render('Settings/Index', [
            'title' => $title,
            'settings' => $this->settingValues(),
            'definitions' => collect(self::SETTING_DEFINITIONS)
                ->map(fn (array $definition, string $key) => ['key' => $key] + $definition)
                ->values()
                ->all(),
        ]);
    }

    private function settingValues(): array
    {
        $stored = Setting::whereIn('key', self::ALLOWED_SETTING_KEYS)->pluck('value', 'key');
        $defaults = [
            'cafe_name' => '',
            'cafe_tagline' => '',
            'cafe_address' => '',
            'cafe_phone' => '',
            'timezone' => config('app.timezone', 'Asia/Makassar'),
            'default_minimum_stock' => '10',
            'table_per_page' => '20',
            'report_export_formats' => 'pdf,xlsx',
            'upload_max_file_size_mb' => '2',
        ];

        return collect(self::ALLOWED_SETTING_KEYS)
            ->mapWithKeys(fn (string $key) => [$key => (string) ($stored[$key] ?? $defaults[$key] ?? '')])
            ->all();
    }

    private function validated(Request $request, string $resource, ?int $id = null): array
    {
        return match ($resource) {
            'ingredient-categories' => $request->validate(['name' => ['required', 'max:100', Rule::unique('ingredient_categories')->ignore($id)], 'description' => ['nullable']]),
            'units' => $request->validate(['name' => ['required', 'max:100', Rule::unique('units')->ignore($id)], 'symbol' => ['required', 'max:20']]),
            'suppliers' => $request->validate(['name' => ['required', 'max:150'], 'phone' => ['nullable', 'max:30'], 'address' => ['nullable'], 'notes' => ['nullable'], 'is_active' => ['boolean']]),
            'ingredients' => $request->validate([
                'code' => ['nullable', 'max:30', Rule::unique('ingredients')->ignore($id)], 'name' => ['required', 'max:150'],
                'ingredient_category_id' => ['required', 'exists:ingredient_categories,id'], 'unit_id' => ['required', 'exists:units,id'], 'primary_supplier_id' => ['nullable', 'exists:suppliers,id'],
                'last_unit_cost' => ['required', 'numeric', 'min:0'], 'current_stock' => ['required', 'numeric', 'min:0'], 'minimum_stock' => ['required', 'numeric', 'min:0'], 'reorder_level' => ['required', 'numeric', 'min:0'], 'is_active' => ['boolean'],
            ]),
            'menu-items' => $request->validate(['code' => ['nullable', 'max:30', Rule::unique('menu_items')->ignore($id)], 'name' => ['required', 'max:150'], 'category' => ['required', Rule::in(self::MENU_CATEGORIES)], 'selling_price' => ['required', 'numeric', 'min:0'], 'is_active' => ['boolean']]),
            'recipe-items' => $request->validate([
                'menu_item_id' => ['required', 'exists:menu_items,id'],
                'ingredient_id' => [
                    'required',
                    Rule::exists('ingredients', 'id')->where('is_active', true),
                    Rule::unique('recipe_items')->where('menu_item_id', $request->menu_item_id)->ignore($id),
                ],
                'quantity_per_serving' => ['required', 'numeric', 'gt:0'],
                'notes' => ['nullable'],
            ]),
            'users' => $request->validate(['name' => ['required', 'max:100'], 'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($id)], 'role' => ['required', Rule::in(CafeStock::ROLES)], 'is_active' => ['boolean'], 'password' => [$id ? 'nullable' : 'required', 'min:8']]),
            'settings' => $request->validate([
                'key' => ['required', 'max:100', Rule::in(self::ALLOWED_SETTING_KEYS), Rule::unique('settings')->ignore($id)],
                'value' => ['nullable'],
                'description' => ['nullable'],
            ]),
            default => abort(422),
        };
    }

    private function validatedSettings(Request $request): array
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.cafe_name' => ['nullable', 'string', 'max:150'],
            'settings.cafe_tagline' => ['nullable', 'string', 'max:255'],
            'settings.cafe_address' => ['nullable', 'string', 'max:1000'],
            'settings.cafe_phone' => ['nullable', 'string', 'max:30'],
            'settings.timezone' => ['required', Rule::in(self::SETTING_DEFINITIONS['timezone']['options'])],
            'settings.default_minimum_stock' => ['required', 'numeric', 'min:0'],
            'settings.table_per_page' => ['required', 'integer', 'min:5', 'max:100'],
            'settings.report_export_formats' => ['required', 'string', 'regex:/^(pdf|xlsx)(,(pdf|xlsx))*$/'],
            'settings.upload_max_file_size_mb' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $settings = $validated['settings'];
        $unknownKeys = array_diff(array_keys($request->input('settings', [])), self::ALLOWED_SETTING_KEYS);

        throw_if($unknownKeys !== [], ValidationException::withMessages([
            'settings' => 'Pengaturan tidak dikenali.',
        ]));

        foreach (self::ALLOWED_SETTING_KEYS as $key) {
            $settings[$key] = (string) ($settings[$key] ?? '');
            $this->rejectSensitiveSettings([
                'key' => $key,
                'value' => $settings[$key],
                'description' => self::SETTING_DEFINITIONS[$key]['description'],
            ]);
        }

        return $settings;
    }

    private function lookups(): array
    {
        return [
            'categories' => IngredientCategory::orderBy('name')->get(['id', 'name']),
            'units' => Unit::orderBy('name')->get(['id', 'name', 'symbol']),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'ingredients' => Ingredient::with('unit:id,name,symbol')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'current_stock', 'unit_id']),
            'menus' => MenuItem::orderBy('name')->get(['id', 'name', 'code', 'is_active']),
            'menuCategories' => self::MENU_CATEGORIES,
            'roles' => CafeStock::ROLES,
            'movementTypes' => CafeStock::MOVEMENT_TYPES,
            'referenceTypes' => self::REFERENCE_TYPES,
            'settingKeys' => self::ALLOWED_SETTING_KEYS,
        ];
    }

    private function hasColumn(string $model, string $column): bool
    {
        return Schema::hasColumn((new $model)->getTable(), $column);
    }

    private function hasReferences(string $resource, int $id): bool
    {
        return match ($resource) {
            'ingredients' => collect(['recipe_items', 'purchase_order_items', 'production_log_items', 'stock_usage_items', 'stock_adjustment_items', 'stock_movements'])
                ->contains(fn ($table) => DB::table($table)->where('ingredient_id', $id)->exists()),
            'menu-items' => DB::table('production_logs')->where('menu_item_id', $id)->exists(),
            'users' => collect(['purchase_orders', 'production_logs', 'stock_usages', 'stock_adjustments', 'stock_movements', 'activity_logs'])
                ->contains(fn ($table) => DB::table($table)->where('user_id', $id)->exists())
                || DB::table('stock_adjustments')->where('approved_by', $id)->exists(),
            'ingredient-categories' => DB::table('ingredients')->where('ingredient_category_id', $id)->exists(),
            'units' => DB::table('ingredients')->where('unit_id', $id)->exists(),
            'suppliers' => DB::table('ingredients')->where('primary_supplier_id', $id)->exists()
                || DB::table('purchase_orders')->where('supplier_id', $id)->exists(),
            default => false,
        };
    }

    private function applyIngredientFilters($query, Request $request): void
    {
        $query->when($request->filled('category_id'), fn ($q) => $q->where('ingredient_category_id', $request->integer('category_id')));
        $query->when($request->filled('supplier_id'), fn ($q) => $q->where('primary_supplier_id', $request->integer('supplier_id')));
        $query->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')));

        match ($request->string('stock_status')->toString()) {
            'low' => $query->where('current_stock', '>', 0)->whereColumn('current_stock', '<=', 'minimum_stock'),
            'out' => $query->where('current_stock', 0),
            'safe' => $query->where('current_stock', '>', 0)->whereColumn('current_stock', '>', 'minimum_stock'),
            default => null,
        };
    }

    private function applyStockMovementFilters($query, Request $request): void
    {
        $query->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()));
        $query->when($request->filled('ingredient_id'), fn ($q) => $q->where('ingredient_id', $request->integer('ingredient_id')));
        $query->when($request->filled('category_id'), fn ($q) => $q->whereHas('ingredient', fn ($ingredient) => $ingredient->where('ingredient_category_id', $request->integer('category_id'))));
        $query->when($request->filled('reference_type'), fn ($q) => $q->where('reference_type', $request->string('reference_type')->toString()));
        $query->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('date_to')));
    }

    private function rejectDirectStockUpdate(Ingredient $ingredient, array $data): void
    {
        if (! array_key_exists('current_stock', $data)) {
            return;
        }

        throw_if(abs((float) $data['current_stock'] - (float) $ingredient->current_stock) > 0.0005, ValidationException::withMessages([
            'current_stock' => 'Stok berjalan hanya boleh berubah melalui transaksi stok.',
        ]));
    }

    private function assertUserChangeAllowed(?User $target, array $data): void
    {
        if (! $target || ! $target->is_active || $target->role !== User::ROLE_OWNER || $this->activeOwnerCount() > 1) {
            return;
        }

        $willNoLongerBeActiveOwner = false;
        if (array_key_exists('role', $data) && $data['role'] !== User::ROLE_OWNER) {
            $willNoLongerBeActiveOwner = true;
        }
        if (array_key_exists('is_active', $data) && ! $this->booleanValue($data['is_active'])) {
            $willNoLongerBeActiveOwner = true;
        }

        throw_if($willNoLongerBeActiveOwner, ValidationException::withMessages([
            'is_active' => 'Owner aktif terakhir tidak boleh dinonaktifkan atau diganti rolenya.',
        ]));
    }

    private function assertUserCanBeDeleted(User $target): void
    {
        throw_if(
            $target->is_active && $target->role === User::ROLE_OWNER && $this->activeOwnerCount() <= 1,
            ValidationException::withMessages(['user' => 'Owner aktif terakhir tidak boleh dihapus.'])
        );
    }

    private function activeOwnerCount(): int
    {
        return User::where('role', User::ROLE_OWNER)->where('is_active', true)->count();
    }

    private function booleanValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }

    private function rejectSensitiveSettings(array $data): void
    {
        $content = implode(' ', array_filter([
            $data['key'] ?? '',
            $data['value'] ?? '',
            $data['description'] ?? '',
        ], fn ($value) => $value !== null && $value !== ''));

        throw_if(preg_match('/password|token|api[\s_-]*key|secret|credential|private[\s_-]*key|access[\s_-]*key|bearer/i', $content), ValidationException::withMessages([
            'value' => 'Pengaturan tidak boleh menyimpan kredensial sensitif.',
        ]));
    }

    private function logResourceChange(Request $request, string $action, string $resource, Model $row): void
    {
        if ($resource === 'activity-logs') {
            return;
        }

        $module = $this->referenceType($resource);
        $label = $this->logLabel($resource, $row);
        $logAction = $resource === 'settings' ? "{$action}_settings" : "{$action}_master_data";

        ActivityLog::create([
            'user_id' => $request->user()?->id,
            'action' => $logAction,
            'module' => $module,
            'description' => ucfirst($action).' '.$label,
            'reference_type' => $module,
            'reference_id' => $row->getKey(),
            'ip_address' => $request->ip(),
        ]);
    }

    private function logUnitCostChange(Request $request, Ingredient $ingredient, float $before, float $after): void
    {
        ActivityLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'update_unit_cost',
            'module' => 'ingredients',
            'description' => sprintf('Harga modal %s berubah dari %.2f ke %.2f', $ingredient->name, $before, $after),
            'reference_type' => 'ingredients',
            'reference_id' => $ingredient->id,
            'ip_address' => $request->ip(),
        ]);
    }

    private function referenceType(string $resource): string
    {
        return str_replace('-', '_', $resource);
    }

    private function logLabel(string $resource, Model $row): string
    {
        return match ($resource) {
            'settings' => 'settings '.$row->getAttribute('key'),
            'users' => 'user #'.$row->getKey(),
            default => ($row->getAttribute('code') ?: $row->getAttribute('name') ?: $row->getAttribute('key') ?: $resource.' #'.$row->getKey()),
        };
    }
}
