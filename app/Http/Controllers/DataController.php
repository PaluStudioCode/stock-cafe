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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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

    private array $resources = [
        'ingredient-categories' => [IngredientCategory::class, 'Kategori Bahan', ['name', 'description']],
        'units' => [Unit::class, 'Satuan', ['name', 'symbol']],
        'suppliers' => [Supplier::class, 'Supplier', ['name', 'phone', 'address', 'notes', 'is_active']],
        'ingredients' => [Ingredient::class, 'Bahan Baku', ['code', 'name', 'ingredient_category_id', 'unit_id', 'primary_supplier_id', 'image_path', 'last_unit_cost', 'current_stock', 'minimum_stock', 'reorder_level', 'is_active']],
        'menu-items' => [MenuItem::class, 'Menu Item', ['code', 'name', 'category', 'selling_price', 'is_active']],
        'recipe-items' => [RecipeItem::class, 'Resep Menu', ['menu_item_id', 'ingredient_id', 'quantity_per_serving', 'notes']],
        'stock-movements' => [StockMovement::class, 'Stock Movement', []],
        'activity-logs' => [ActivityLog::class, 'Activity Log', []],
        'users' => [User::class, 'Pengguna', ['name', 'email', 'role', 'is_active', 'password']],
        'settings' => [Setting::class, 'Settings', ['key', 'value', 'description']],
    ];

    public function index(Request $request, string $resource)
    {
        [$model, $title] = $this->resource($resource);
        $query = $model::query();

        if ($resource === 'low-stock') {
            $query = Ingredient::query()->whereColumn('current_stock', '<=', 'minimum_stock');
            $title = 'Stok Menipis';
        }
        if ($resource === 'out-of-stock') {
            $query = Ingredient::query()->where('current_stock', 0);
            $title = 'Stok Habis';
        }

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
            'readonly' => $request->routeIs('monitoring.*') || in_array($resource, ['stock-movements', 'activity-logs', 'low-stock', 'out-of-stock'], true),
        ]);
    }

    public function store(Request $request, string $resource)
    {
        [$model, , $fields] = $this->resource($resource);
        $data = $this->validated($request, $resource);

        if ($resource === 'ingredients' && empty($data['code'])) {
            $data['code'] = CafeStock::code('ING', 'ingredients', 'code');
        }
        if ($resource === 'ingredients') {
            $this->applyIngredientImage($request, $data);
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
            DB::transaction(function () use ($data, $fields, $model, $request) {
                $opening = (float) ($data['current_stock'] ?? 0);
                $data['current_stock'] = 0;
                $ingredient = $model::create(array_intersect_key($data, array_flip($fields)));
                if ($opening > 0) {
                    app(\App\Services\StockService::class)->move($ingredient, $request->user()->id, 'opening_stock', 'opening_stock', null, $opening, 0, (float) $ingredient->last_unit_cost, 'Opening stock');
                }
            });
        } else {
            $model::create(array_intersect_key($data, array_flip($fields)));
        }

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
        if ($resource === 'ingredients') {
            $this->rejectDirectStockUpdate($row, $data);
            $this->applyIngredientImage($request, $data, $row);
            unset($data['current_stock']);
        }
        if ($resource === 'menu-items') {
            abort_if($this->hasReferences($resource, $id), 422, 'Menu sudah memiliki production log dan tidak dapat diubah.');
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

        return back()->with('success', 'Data diperbarui.');
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

        return back()->with('success', 'Data dihapus.');
    }

    private function resource(string $resource): array
    {
        if ($resource === 'low-stock' || $resource === 'out-of-stock') {
            return [Ingredient::class, 'Monitoring Stok', []];
        }
        abort_unless(isset($this->resources[$resource]), 404);

        return $this->resources[$resource];
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
                'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
                'last_unit_cost' => ['required', 'numeric', 'min:0'], 'current_stock' => ['required', 'numeric', 'min:0'], 'minimum_stock' => ['required', 'numeric', 'min:0'], 'reorder_level' => ['required', 'numeric', 'min:0'], 'is_active' => ['boolean'],
            ]),
            'menu-items' => $request->validate(['code' => ['nullable', 'max:30', Rule::unique('menu_items')->ignore($id)], 'name' => ['required', 'max:150'], 'category' => ['nullable', 'max:100'], 'selling_price' => ['required', 'numeric', 'min:0'], 'is_active' => ['boolean']]),
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

    private function lookups(): array
    {
        return [
            'categories' => IngredientCategory::orderBy('name')->get(['id', 'name']),
            'units' => Unit::orderBy('name')->get(['id', 'name', 'symbol']),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'ingredients' => Ingredient::with('unit:id,name,symbol')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'current_stock', 'unit_id']),
            'menus' => MenuItem::orderBy('name')->get(['id', 'name', 'code', 'is_active']),
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
            'low' => $query->whereColumn('current_stock', '<=', 'minimum_stock'),
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

    private function applyIngredientImage(Request $request, array &$data, ?Ingredient $ingredient = null): void
    {
        unset($data['image']);

        if (! $request->hasFile('image')) {
            return;
        }

        if ($ingredient?->image_path) {
            Storage::disk('public')->delete($ingredient->image_path);
        }

        $data['image_path'] = $request->file('image')->store('ingredient-images', 'public');
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
            'value' => 'Settings tidak boleh menyimpan credential sensitif.',
        ]));
    }
}
