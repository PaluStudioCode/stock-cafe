<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\MenuItem;
use App\Models\ProductionLog;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\RecipeItem;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockMovement;
use App\Models\StockUsage;
use App\Models\StockUsageItem;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CafeStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_stock_matches_latest_stock_movement(): void
    {
        $this->seed();

        Ingredient::all()->each(function (Ingredient $ingredient) {
            $this->assertSame(
                number_format((float) $ingredient->current_stock, 3, '.', ''),
                number_format((float) $ingredient->movements()->latest('id')->first()->stock_after, 3, '.', ''),
                $ingredient->name
            );
        });
    }

    public function test_inactive_account_cannot_login(): void
    {
        $this->seed();

        $this->post('/login', [
            'email' => 'inactive@cafestock.test',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_barista_receives_403_for_owner_admin_pages(): void
    {
        $this->seed();
        $barista = User::where('email', 'dimas@cafestock.test')->firstOrFail();

        $this->actingAs($barista)->get('/admin/users')->assertForbidden();
        $this->actingAs($barista)->get('/admin/settings')->assertForbidden();
    }

    public function test_unauthenticated_users_are_redirected_from_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_owner_can_access_sensitive_operational_pages(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();

        $this->actingAs($owner)->get('/admin/users')->assertOk();
        $this->actingAs($owner)->get('/master/ingredients')->assertOk();
        $this->actingAs($owner)->get('/purchase-orders')->assertOk();
        $this->actingAs($owner)->get('/stock-usages')->assertOk();
        $this->actingAs($owner)->get('/stock-adjustments')->assertOk();
        $this->actingAs($owner)->get('/production-logs')->assertOk();
        $this->actingAs($owner)->get('/reports/stock')->assertOk();
    }

    public function test_inventory_staff_permissions_follow_phase_three_rules(): void
    {
        $this->seed();
        $inventory = User::where('email', 'inventory@cafestock.test')->firstOrFail();

        $this->actingAs($inventory)->get('/master/ingredients')->assertOk();
        $this->actingAs($inventory)->get('/master/suppliers')->assertOk();
        $this->actingAs($inventory)->get('/purchase-orders')->assertOk();
        $this->actingAs($inventory)->get('/stock-usages')->assertOk();
        $this->actingAs($inventory)->get('/stock-adjustments')->assertOk();
        $this->actingAs($inventory)->get('/production-logs')->assertOk();
        $this->actingAs($inventory)->get('/admin/users')->assertForbidden();
        $this->actingAs($inventory)->get('/admin/settings')->assertForbidden();
        $this->actingAs($inventory)->post('/production-logs', [
            'menu_item_id' => 2,
            'quantity' => 1,
            'production_date' => '2026-05-06 10:00:00',
        ])->assertForbidden();
    }

    public function test_owner_can_manage_users_without_operational_data(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();

        $this->actingAs($owner)->post('/admin/users', [
            'name' => 'Temporary Staff',
            'email' => 'temp@cafestock.test',
            'role' => User::ROLE_BARISTA,
            'is_active' => true,
            'password' => 'password',
        ])->assertRedirect();

        $staff = User::where('email', 'temp@cafestock.test')->firstOrFail();
        $this->assertTrue(Hash::check('password', $staff->password));

        $this->actingAs($owner)->put("/admin/users/{$staff->id}", [
            'name' => 'Temporary Staff Updated',
            'email' => 'temp@cafestock.test',
            'role' => User::ROLE_BARISTA,
            'is_active' => false,
            'password' => '',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'name' => 'Temporary Staff Updated',
            'is_active' => false,
        ]);

        $this->actingAs($owner)->delete("/admin/users/{$staff->id}")->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $staff->id]);
    }

    public function test_owner_cannot_deactivate_the_only_active_owner(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();

        $this->actingAs($owner)->put("/admin/users/{$owner->id}", [
            'name' => $owner->name,
            'email' => $owner->email,
            'role' => User::ROLE_OWNER,
            'is_active' => false,
            'password' => '',
        ])->assertSessionHasErrors('is_active');

        $this->assertTrue($owner->fresh()->is_active);
    }

    public function test_owner_cannot_delete_user_with_operational_data(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();
        $inventory = User::where('email', 'inventory@cafestock.test')->firstOrFail();

        $this->actingAs($owner)->delete("/admin/users/{$inventory->id}")->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $inventory->id]);
    }

    public function test_settings_are_limited_to_allowed_non_sensitive_keys(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();

        $this->actingAs($owner)->post('/admin/settings', [
            'key' => 'api_key',
            'value' => 'abc123',
            'description' => 'External credential',
        ])->assertSessionHasErrors('key');

        $this->actingAs($owner)->put('/admin/settings/1', [
            'key' => 'cafe_name',
            'value' => 'secret token value',
            'description' => 'Nama cafe',
        ])->assertSessionHasErrors('value');

        $this->actingAs($owner)->put('/admin/settings/1', [
            'key' => 'cafe_name',
            'value' => 'CafeStock Lab',
            'description' => 'Nama cafe',
        ])->assertRedirect();

        $this->assertDatabaseHas('settings', [
            'key' => 'cafe_name',
            'value' => 'CafeStock Lab',
        ]);
    }

    public function test_owner_can_create_ingredient_with_auto_code_opening_stock_and_image(): void
    {
        $this->seed();
        Storage::fake('public');
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();

        $this->actingAs($owner)->post('/master/ingredients', [
            'code' => '',
            'name' => 'Single Origin Test',
            'ingredient_category_id' => 1,
            'unit_id' => 1,
            'primary_supplier_id' => 1,
            'image' => UploadedFile::fake()->image('beans.jpg'),
            'last_unit_cost' => 75000,
            'current_stock' => 12.5,
            'minimum_stock' => 3,
            'reorder_level' => 8,
            'is_active' => true,
        ])->assertRedirect();

        $ingredient = Ingredient::where('name', 'Single Origin Test')->firstOrFail();
        $this->assertMatchesRegularExpression('/^ING-\d{8}-\d{4}$/', $ingredient->code);
        $this->assertSame('12.500', number_format((float) $ingredient->current_stock, 3, '.', ''));
        Storage::disk('public')->assertExists($ingredient->image_path);

        $this->assertDatabaseHas('stock_movements', [
            'ingredient_id' => $ingredient->id,
            'type' => 'opening_stock',
            'reference_type' => 'opening_stock',
            'stock_after' => 12.5,
        ]);
    }

    public function test_ingredient_current_stock_cannot_be_updated_directly(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();
        $ingredient = Ingredient::where('name', 'Arabica Beans')->firstOrFail();

        $this->actingAs($owner)->put("/master/ingredients/{$ingredient->id}", [
            'code' => $ingredient->code,
            'name' => $ingredient->name,
            'ingredient_category_id' => $ingredient->ingredient_category_id,
            'unit_id' => $ingredient->unit_id,
            'primary_supplier_id' => $ingredient->primary_supplier_id,
            'last_unit_cost' => $ingredient->last_unit_cost,
            'current_stock' => 9999,
            'minimum_stock' => $ingredient->minimum_stock,
            'reorder_level' => $ingredient->reorder_level,
            'is_active' => $ingredient->is_active,
        ])->assertSessionHasErrors('current_stock');

        $this->assertSame('5200.000', number_format((float) $ingredient->fresh()->current_stock, 3, '.', ''));
    }

    public function test_master_data_with_related_ingredients_or_transactions_cannot_be_deleted(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();

        $this->actingAs($owner)->delete('/master/ingredient-categories/1')->assertStatus(422);
        $this->actingAs($owner)->delete('/master/units/1')->assertStatus(422);
        $this->actingAs($owner)->delete('/master/suppliers/1')->assertStatus(422);
        $this->actingAs($owner)->delete('/master/ingredients/1')->assertStatus(422);
    }

    public function test_unreferenced_master_data_can_be_deleted(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();
        $category = IngredientCategory::create(['name' => 'Temporary Category']);
        $unit = Unit::create(['name' => 'Temporary Unit', 'symbol' => 'tmp']);
        $supplier = Supplier::create(['name' => 'Temporary Supplier', 'is_active' => true]);

        $this->actingAs($owner)->delete("/master/ingredient-categories/{$category->id}")->assertRedirect();
        $this->actingAs($owner)->delete("/master/units/{$unit->id}")->assertRedirect();
        $this->actingAs($owner)->delete("/master/suppliers/{$supplier->id}")->assertRedirect();

        $this->assertDatabaseMissing('ingredient_categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('units', ['id' => $unit->id]);
        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }

    public function test_ingredient_filters_support_category_supplier_active_status_and_stock_status(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();
        $caramel = Ingredient::where('name', 'Sirup Caramel')->firstOrFail();

        $this->actingAs($owner)->get('/master/ingredients?category_id='.$caramel->ingredient_category_id.'&supplier_id='.$caramel->primary_supplier_id.'&is_active=1&stock_status=out')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Generic/Index')
                ->where('filters.stock_status', 'out')
                ->has('rows.data', 1)
                ->where('rows.data.0.name', 'Sirup Caramel'));
    }

    public function test_inactive_ingredients_are_hidden_from_transaction_default_choices(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();
        $ingredient = Ingredient::where('name', 'Arabica Beans')->firstOrFail();
        $ingredient->forceFill(['is_active' => false])->save();

        $assertHidden = fn (Assert $page) => $page->where('ingredients', fn ($ingredients) => collect($ingredients)->doesntContain(fn ($item) => $item['id'] === $ingredient->id));

        $this->actingAs($owner)->get('/purchase-orders')->assertOk()->assertInertia($assertHidden);
        $this->actingAs($owner)->get('/stock-usages')->assertOk()->assertInertia($assertHidden);
        $this->actingAs($owner)->get('/stock-adjustments')->assertOk()->assertInertia($assertHidden);
    }

    public function test_owner_can_manage_menu_without_production_logs(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();

        $this->actingAs($owner)->post('/menu/menu-items', [
            'code' => '',
            'name' => 'Seasonal Test Menu',
            'category' => 'Seasonal',
            'selling_price' => 32000,
            'is_active' => true,
        ])->assertRedirect();

        $menu = MenuItem::where('name', 'Seasonal Test Menu')->firstOrFail();
        $this->assertMatchesRegularExpression('/^MENU-\d{8}-\d{4}$/', $menu->code);

        $this->actingAs($owner)->post('/menu/recipe-items', [
            'menu_item_id' => $menu->id,
            'ingredient_id' => 1,
            'quantity_per_serving' => 18,
            'notes' => 'Espresso base',
        ])->assertRedirect();

        $this->actingAs($owner)->put("/menu/menu-items/{$menu->id}", [
            'code' => $menu->code,
            'name' => 'Seasonal Test Menu Updated',
            'category' => 'Seasonal',
            'selling_price' => 33000,
            'is_active' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('menu_items', [
            'id' => $menu->id,
            'name' => 'Seasonal Test Menu Updated',
            'selling_price' => 33000,
            'is_active' => false,
        ]);

        $this->actingAs($owner)->delete("/menu/menu-items/{$menu->id}")->assertRedirect();

        $this->assertDatabaseMissing('menu_items', ['id' => $menu->id]);
        $this->assertDatabaseMissing('recipe_items', ['menu_item_id' => $menu->id]);
    }

    public function test_menu_with_production_logs_cannot_be_deleted(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();

        $this->actingAs($owner)->put('/menu/menu-items/1', [
            'code' => 'MENU-20260501-0001',
            'name' => 'Edited Historical Menu',
            'category' => 'Coffee',
            'selling_price' => 25000,
            'is_active' => false,
        ])->assertStatus(422);
        $this->actingAs($owner)->delete('/menu/menu-items/1')->assertStatus(422);
        $this->assertDatabaseHas('menu_items', [
            'id' => 1,
            'name' => 'Es Kopi Susu Aren',
            'is_active' => true,
        ]);
    }

    public function test_recipe_item_validation_blocks_duplicate_inactive_ingredient_and_invalid_quantity(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();
        $menu = MenuItem::where('name', 'Caramel Latte Iced')->firstOrFail();

        $this->actingAs($owner)->post('/menu/recipe-items', [
            'menu_item_id' => 1,
            'ingredient_id' => 1,
            'quantity_per_serving' => 1,
        ])->assertSessionHasErrors('ingredient_id');

        $this->actingAs($owner)->post('/menu/recipe-items', [
            'menu_item_id' => $menu->id,
            'ingredient_id' => 1,
            'quantity_per_serving' => 0,
        ])->assertSessionHasErrors('quantity_per_serving');

        Ingredient::where('id', 2)->update(['is_active' => false]);
        $this->actingAs($owner)->post('/menu/recipe-items', [
            'menu_item_id' => $menu->id,
            'ingredient_id' => 2,
            'quantity_per_serving' => 20,
        ])->assertSessionHasErrors('ingredient_id');
    }

    public function test_production_log_only_lists_active_menus_with_recipe(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();
        $menu = MenuItem::create([
            'code' => 'MENU-20260506-9999',
            'name' => 'Recipe Pending Menu',
            'category' => 'Test',
            'selling_price' => 25000,
            'is_active' => true,
        ]);

        $this->actingAs($owner)->get('/production-logs')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('menus', fn ($menus) => collect($menus)->doesntContain(fn ($item) => $item['id'] === $menu->id)));

        RecipeItem::create([
            'menu_item_id' => $menu->id,
            'ingredient_id' => 1,
            'quantity_per_serving' => 18,
        ]);

        $this->actingAs($owner)->get('/production-logs')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('menus', fn ($menus) => collect($menus)->contains(fn ($item) => $item['id'] === $menu->id)));

        $menu->forceFill(['is_active' => false])->save();

        $this->actingAs($owner)->get('/production-logs')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('menus', fn ($menus) => collect($menus)->doesntContain(fn ($item) => $item['id'] === $menu->id)));
    }

    public function test_recipe_changes_do_not_mutate_existing_production_snapshots(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();
        $recipe = RecipeItem::where('menu_item_id', 1)->where('ingredient_id', 1)->firstOrFail();

        $this->actingAs($owner)->put("/menu/recipe-items/{$recipe->id}", [
            'menu_item_id' => 1,
            'ingredient_id' => 1,
            'quantity_per_serving' => 99,
            'notes' => 'Updated for future production',
        ])->assertRedirect();

        $this->assertDatabaseHas('recipe_items', [
            'id' => $recipe->id,
            'quantity_per_serving' => 99,
        ]);
        $this->assertDatabaseHas('production_log_items', [
            'production_log_id' => 1,
            'ingredient_id' => 1,
            'quantity_per_serving_snapshot' => 18,
        ]);
    }

    public function test_non_owner_can_only_view_menu_and_recipe_pages(): void
    {
        $this->seed();
        $inventory = User::where('email', 'inventory@cafestock.test')->firstOrFail();
        $barista = User::where('email', 'dimas@cafestock.test')->firstOrFail();

        $this->actingAs($inventory)->get('/menu/menu-items')->assertOk();
        $this->actingAs($inventory)->get('/menu/recipe-items')->assertOk();
        $this->actingAs($barista)->get('/menu/menu-items')->assertOk();
        $this->actingAs($barista)->get('/menu/recipe-items')->assertOk();

        $this->actingAs($inventory)->post('/menu/menu-items', [
            'code' => '',
            'name' => 'Unauthorized Menu',
            'category' => 'Test',
            'selling_price' => 10000,
            'is_active' => true,
        ])->assertForbidden();
        $this->actingAs($barista)->post('/menu/recipe-items', [
            'menu_item_id' => 1,
            'ingredient_id' => 2,
            'quantity_per_serving' => 1,
        ])->assertForbidden();
        $this->actingAs($inventory)->delete('/menu/menu-items/1')->assertForbidden();
    }

    public function test_barista_permissions_follow_phase_three_rules(): void
    {
        $this->seed();
        $barista = User::where('email', 'dimas@cafestock.test')->firstOrFail();

        $this->actingAs($barista)->get('/menu/menu-items')->assertOk();
        $this->actingAs($barista)->get('/menu/recipe-items')->assertOk();
        $this->actingAs($barista)->get('/monitoring/ingredients')->assertOk();
        $this->actingAs($barista)->get('/production-logs')->assertOk();
        $this->actingAs($barista)->get('/purchase-orders')->assertForbidden();
        $this->actingAs($barista)->get('/master/ingredients')->assertForbidden();
        $this->actingAs($barista)->get('/reports/stock')->assertForbidden();
    }

    public function test_dashboard_summary_is_role_specific_for_owner(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();

        $this->actingAs($owner)->getJson(route('dashboard.summary'))
            ->assertOk()
            ->assertJsonPath('role', 'owner')
            ->assertJsonFragment(['label' => 'Total Bahan'])
            ->assertJsonFragment(['label' => 'Nilai Persediaan'])
            ->assertJsonStructure(['metrics', 'lowStock', 'recentActivities', 'refreshedAt']);
    }

    public function test_dashboard_summary_is_role_specific_for_inventory_staff(): void
    {
        $this->seed();
        $inventory = User::where('email', 'inventory@cafestock.test')->firstOrFail();

        $response = $this->actingAs($inventory)->getJson(route('dashboard.summary'))
            ->assertOk()
            ->assertJsonPath('role', 'inventory_staff')
            ->assertJsonFragment(['label' => 'Draft Adjustment'])
            ->assertJsonStructure(['metrics', 'lowStock', 'recentPurchaseOrders', 'recentStockUsages', 'draftAdjustments']);

        $this->assertSame([], $response->json('recentActivities'));
    }

    public function test_dashboard_summary_is_role_specific_for_barista(): void
    {
        $this->seed();
        $barista = User::where('email', 'dimas@cafestock.test')->firstOrFail();

        $response = $this->actingAs($barista)->getJson(route('dashboard.summary'))
            ->assertOk()
            ->assertJsonPath('role', 'barista')
            ->assertJsonFragment(['label' => 'Menu Aktif'])
            ->assertJsonStructure(['metrics', 'lowStock', 'productionLogs', 'activeMenus']);

        $this->assertSame([], $response->json('recentPurchaseOrders'));
        $this->assertSame([], $response->json('recentStockUsages'));
    }

    public function test_low_stock_and_out_of_stock_seed_are_present(): void
    {
        $this->seed();

        $low = Ingredient::whereColumn('current_stock', '<=', 'minimum_stock')->pluck('name')->all();
        $this->assertContains('Fresh Milk', $low);
        $this->assertContains('Sirup Caramel', $low);
        $this->assertContains('Matcha Powder', $low);
        $this->assertSame('Sirup Caramel', Ingredient::where('current_stock', 0)->first()->name);
    }

    public function test_monitoring_stock_pages_show_low_out_and_filtered_stock_data(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();

        $this->actingAs($owner)->get('/monitoring/low-stock')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Generic/Index')
            ->where('resource', 'low-stock')
            ->where('rows.data', fn ($rows) => collect($rows)->contains(fn ($row) => $row['name'] === 'Sirup Caramel')
                && collect($rows)->every(fn ($row) => (float) $row['current_stock'] <= (float) $row['minimum_stock'])));

        $this->actingAs($owner)->get('/monitoring/out-of-stock')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Generic/Index')
            ->where('resource', 'out-of-stock')
            ->has('rows.data', 1)
            ->where('rows.data.0.name', 'Sirup Caramel')
            ->where('rows.data.0.current_stock', '0.000'));

        $this->actingAs($owner)->get('/monitoring/ingredients?stock_status=low')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.stock_status', 'low')
            ->where('rows.data', fn ($rows) => collect($rows)->every(fn ($row) => (float) $row['current_stock'] <= (float) $row['minimum_stock'])));
    }

    public function test_stock_movement_history_can_be_filtered_and_has_detail_payload(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();

        $this->actingAs($owner)->get('/monitoring/stock-movements?ingredient_id=1&category_id=1&type=purchase_receipt&reference_type=purchase_orders&date_from=2000-01-01&date_to=2100-01-01')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Generic/Index')
                ->where('resource', 'stock-movements')
                ->where('filters.ingredient_id', '1')
                ->where('filters.category_id', '1')
                ->where('filters.type', 'purchase_receipt')
                ->where('filters.reference_type', 'purchase_orders')
                ->has('rows.data', 1)
                ->where('rows.data.0.ingredient_id', 1)
                ->where('rows.data.0.type', 'purchase_receipt')
                ->where('rows.data.0.reference_type', 'purchase_orders')
                ->where('rows.data.0.quantity_in', '2000.000')
                ->where('rows.data.0.stock_before', '4000.000')
                ->where('rows.data.0.stock_after', '6000.000')
                ->where('rows.data.0.ingredient.name', 'Arabica Beans')
                ->where('rows.data.0.ingredient.category.name', 'Kopi')
                ->where('rows.data.0.user.name', 'Rani Inventory'));
    }

    public function test_stock_movement_history_filters_reference_type_and_category_without_ingredient(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();

        $this->actingAs($owner)->get('/monitoring/stock-movements?category_id=4&type=waste&reference_type=stock_usages&date_from=2000-01-01&date_to=2100-01-01')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.category_id', '4')
                ->where('rows.data', fn ($rows) => collect($rows)->isNotEmpty()
                    && collect($rows)->every(fn ($row) => $row['type'] === 'waste'
                        && $row['reference_type'] === 'stock_usages'
                        && $row['ingredient']['ingredient_category_id'] === 4)));
    }

    public function test_stock_service_prevents_negative_stock_without_creating_movement(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();
        $caramel = Ingredient::where('name', 'Sirup Caramel')->firstOrFail();
        $movementCount = StockMovement::where('ingredient_id', $caramel->id)->count();

        try {
            app(\App\Services\StockService::class)->move($caramel, $owner->id, 'manual_usage', 'stock_usages', 9999, 0, 1, 50, 'Invalid negative stock test');
            $this->fail('StockService allowed negative stock.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('tidak cukup', $e->getMessage());
        }

        $this->assertSame('0.000', number_format((float) $caramel->fresh()->current_stock, 3, '.', ''));
        $this->assertSame($movementCount, StockMovement::where('ingredient_id', $caramel->id)->count());
    }

    public function test_draft_purchase_order_can_be_deleted_without_stock_movement(): void
    {
        $this->seed();
        $owner = User::where('role', 'owner')->firstOrFail();
        $draft = PurchaseOrder::where('purchase_code', 'PO-20260504-0001')->firstOrFail();

        $this->assertFalse(StockMovement::where('reference_type', 'purchase_orders')->where('reference_id', $draft->id)->exists());
        $this->actingAs($owner)->delete(route('purchase-orders.destroy', $draft))->assertRedirect();

        $this->assertDatabaseMissing('purchase_orders', ['purchase_code' => 'PO-20260504-0001']);
    }

    public function test_purchase_order_draft_is_created_with_backend_totals_and_without_stock_movement(): void
    {
        $this->seed();
        $inventory = User::where('email', 'inventory@cafestock.test')->firstOrFail();

        $this->actingAs($inventory)->post('/purchase-orders', [
            'supplier_id' => 1,
            'purchase_date' => '2026-05-06',
            'discount' => 1000,
            'notes' => 'Draft purchase test',
            'items' => [
                ['ingredient_id' => 1, 'quantity' => 10, 'unit_cost' => 160],
                ['ingredient_id' => 2, 'quantity' => 5, 'unit_cost' => 95],
            ],
        ])->assertRedirect();

        $order = PurchaseOrder::where('notes', 'Draft purchase test')->firstOrFail();
        $this->assertMatchesRegularExpression('/^PO-20260506-\d{4}$/', $order->purchase_code);
        $this->assertSame('2075.00', number_format((float) $order->subtotal, 2, '.', ''));
        $this->assertSame('1075.00', number_format((float) $order->total_amount, 2, '.', ''));
        $this->assertSame('draft', $order->status);
        $this->assertFalse(StockMovement::where('reference_type', 'purchase_orders')->where('reference_id', $order->id)->exists());
    }

    public function test_purchase_order_draft_can_be_updated_and_replaces_items_without_stock_movement(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();
        $draft = PurchaseOrder::where('purchase_code', 'PO-20260504-0001')->firstOrFail();

        $this->actingAs($owner)->put("/purchase-orders/{$draft->id}", [
            'supplier_id' => 1,
            'purchase_date' => '2026-05-06',
            'discount' => 50,
            'notes' => 'Draft updated',
            'items' => [
                ['ingredient_id' => 3, 'quantity' => 100, 'unit_cost' => 20],
                ['ingredient_id' => 1, 'quantity' => 2, 'unit_cost' => 160],
            ],
        ])->assertRedirect();

        $draft->refresh();
        $this->assertSame('2320.00', number_format((float) $draft->subtotal, 2, '.', ''));
        $this->assertSame('2270.00', number_format((float) $draft->total_amount, 2, '.', ''));
        $this->assertSame('Draft updated', $draft->notes);
        $this->assertSame(2, PurchaseOrderItem::where('purchase_order_id', $draft->id)->count());
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $draft->id,
            'ingredient_id' => 3,
            'quantity' => 100,
            'unit_cost' => 20,
            'subtotal' => 2000,
        ]);
        $this->assertFalse(StockMovement::where('reference_type', 'purchase_orders')->where('reference_id', $draft->id)->exists());
    }

    public function test_purchase_order_receive_adds_stock_updates_cost_and_creates_movements(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();
        $draft = PurchaseOrder::where('purchase_code', 'PO-20260504-0001')->firstOrFail();

        $this->actingAs($owner)->post("/purchase-orders/{$draft->id}/receive")->assertRedirect();

        $draft->refresh();
        $this->assertSame('received', $draft->status);
        $this->assertNotNull($draft->received_at);
        $this->assertSame('11450.000', number_format((float) Ingredient::findOrFail(11)->current_stock, 3, '.', ''));
        $this->assertSame('3900.000', number_format((float) Ingredient::findOrFail(4)->current_stock, 3, '.', ''));
        $this->assertSame('2.00', number_format((float) Ingredient::findOrFail(11)->last_unit_cost, 2, '.', ''));
        $this->assertSame(2, StockMovement::where('reference_type', 'purchase_orders')->where('reference_id', $draft->id)->where('type', 'purchase_receipt')->count());
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'receive_purchase',
            'module' => 'purchase_orders',
            'reference_type' => 'purchase_orders',
            'reference_id' => $draft->id,
        ]);
    }

    public function test_purchase_order_received_cannot_be_edited_received_again_or_deleted(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();
        $received = PurchaseOrder::where('purchase_code', 'PO-20260502-0001')->firstOrFail();

        $this->actingAs($owner)->put("/purchase-orders/{$received->id}", [
            'supplier_id' => 1,
            'purchase_date' => '2026-05-06',
            'discount' => 0,
            'items' => [
                ['ingredient_id' => 1, 'quantity' => 1, 'unit_cost' => 160],
            ],
        ])->assertStatus(422);

        $this->actingAs($owner)->post("/purchase-orders/{$received->id}/receive")->assertSessionHasErrors('stock');
        $this->actingAs($owner)->delete("/purchase-orders/{$received->id}")->assertStatus(422);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $received->id,
            'status' => 'received',
            'total_amount' => 440000,
        ]);
    }

    public function test_purchase_order_rejects_invalid_active_entities_items_and_discount(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();
        Supplier::where('id', 1)->update(['is_active' => false]);
        Ingredient::where('id', 1)->update(['is_active' => false]);

        $this->actingAs($owner)->post('/purchase-orders', [
            'supplier_id' => 1,
            'purchase_date' => '2026-05-06',
            'discount' => 0,
            'items' => [
                ['ingredient_id' => 1, 'quantity' => 1, 'unit_cost' => 160],
            ],
        ])->assertSessionHasErrors(['supplier_id', 'items.0.ingredient_id']);

        $this->actingAs($owner)->post('/purchase-orders', [
            'supplier_id' => 2,
            'purchase_date' => '2026-05-06',
            'discount' => 0,
            'items' => [
                ['ingredient_id' => 2, 'quantity' => 0, 'unit_cost' => 95],
            ],
        ])->assertSessionHasErrors('items.0.quantity');

        $this->actingAs($owner)->post('/purchase-orders', [
            'supplier_id' => 2,
            'purchase_date' => '2026-05-06',
            'discount' => 99999,
            'items' => [
                ['ingredient_id' => 2, 'quantity' => 1, 'unit_cost' => 95],
            ],
        ])->assertStatus(422);
    }

    public function test_barista_can_create_completed_production_log_with_snapshots_and_movements(): void
    {
        $this->seed();
        $barista = User::where('email', 'dimas@cafestock.test')->firstOrFail();

        $this->actingAs($barista)->post('/production-logs', [
            'menu_item_id' => 2,
            'quantity' => 1,
            'production_date' => '2026-05-06 10:00:00',
            'notes' => 'Americano test',
        ])->assertRedirect();

        $log = ProductionLog::where('notes', 'Americano test')->firstOrFail();
        $this->assertMatchesRegularExpression('/^PROD-20260506-\d{4}$/', $log->production_code);
        $this->assertSame('completed', $log->status);
        $this->assertSame('3760.00', number_format((float) $log->estimated_total_cost, 2, '.', ''));
        $this->assertSame('5184.000', number_format((float) Ingredient::findOrFail(1)->current_stock, 3, '.', ''));
        $this->assertDatabaseHas('production_log_items', [
            'production_log_id' => $log->id,
            'ingredient_id' => 1,
            'quantity_per_serving_snapshot' => 16,
            'quantity_used' => 16,
            'unit_cost_snapshot' => 160,
            'estimated_cost' => 2560,
        ]);
        $this->assertSame(4, StockMovement::where('reference_type', 'production_logs')->where('reference_id', $log->id)->where('type', 'production_usage')->count());
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'complete_production',
            'module' => 'production_logs',
            'reference_id' => $log->id,
        ]);
    }

    public function test_production_log_rejects_inactive_menu_missing_recipe_and_inactive_ingredient(): void
    {
        $this->seed();
        $barista = User::where('email', 'dimas@cafestock.test')->firstOrFail();
        $inactiveMenu = MenuItem::create([
            'code' => 'MENU-20260506-9001',
            'name' => 'Inactive Production Test',
            'category' => 'Test',
            'selling_price' => 20000,
            'is_active' => false,
        ]);
        $inactiveMenu->recipeItems()->create([
            'ingredient_id' => 1,
            'quantity_per_serving' => 1,
        ]);
        $noRecipeMenu = MenuItem::create([
            'code' => 'MENU-20260506-9002',
            'name' => 'No Recipe Production Test',
            'category' => 'Test',
            'selling_price' => 20000,
            'is_active' => true,
        ]);

        $this->actingAs($barista)->post('/production-logs', [
            'menu_item_id' => $inactiveMenu->id,
            'quantity' => 1,
            'production_date' => '2026-05-06 10:00:00',
            'notes' => 'Should fail inactive menu',
        ])->assertSessionHasErrors('stock');

        $this->actingAs($barista)->post('/production-logs', [
            'menu_item_id' => $noRecipeMenu->id,
            'quantity' => 1,
            'production_date' => '2026-05-06 10:00:00',
            'notes' => 'Should fail no recipe',
        ])->assertSessionHasErrors('stock');

        Ingredient::where('id', 1)->update(['is_active' => false]);
        $this->actingAs($barista)->post('/production-logs', [
            'menu_item_id' => 2,
            'quantity' => 1,
            'production_date' => '2026-05-06 10:00:00',
            'notes' => 'Should fail inactive ingredient',
        ])->assertSessionHasErrors('stock');

        $this->assertDatabaseMissing('production_logs', ['notes' => 'Should fail inactive menu']);
        $this->assertDatabaseMissing('production_logs', ['notes' => 'Should fail no recipe']);
        $this->assertDatabaseMissing('production_logs', ['notes' => 'Should fail inactive ingredient']);
    }

    public function test_barista_only_sees_own_production_logs_and_inventory_cannot_create(): void
    {
        $this->seed();
        $barista = User::where('email', 'dimas@cafestock.test')->firstOrFail();
        $inventory = User::where('email', 'inventory@cafestock.test')->firstOrFail();

        $this->actingAs($barista)->get('/production-logs')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('canCreate', true)
            ->where('canCancel', false)
            ->where('logs.data', fn ($logs) => collect($logs)->every(fn ($log) => $log['user_id'] === $barista->id)));

        $this->actingAs($inventory)->get('/production-logs')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('canCreate', false)
            ->where('canCancel', false));

        $this->actingAs($inventory)->post('/production-logs', [
            'menu_item_id' => 2,
            'quantity' => 1,
            'production_date' => '2026-05-06 10:00:00',
        ])->assertForbidden();
    }

    public function test_owner_can_cancel_completed_production_and_restore_stock_once(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();
        $log = ProductionLog::where('production_code', 'PROD-20260503-0002')->firstOrFail();

        $this->actingAs($owner)->post("/production-logs/{$log->id}/cancel")->assertRedirect();

        $log->refresh();
        $this->assertSame('cancelled', $log->status);
        $this->assertSame('5440.000', number_format((float) Ingredient::findOrFail(1)->current_stock, 3, '.', ''));
        $this->assertSame('8700.000', number_format((float) Ingredient::findOrFail(11)->current_stock, 3, '.', ''));
        $this->assertSame(4, StockMovement::where('reference_type', 'production_logs')->where('reference_id', $log->id)->where('type', 'cancel_production')->count());
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'cancel_production',
            'module' => 'production_logs',
            'reference_id' => $log->id,
        ]);

        $this->actingAs($owner)->post("/production-logs/{$log->id}/cancel")->assertSessionHasErrors('stock');
        $this->assertSame(4, StockMovement::where('reference_type', 'production_logs')->where('reference_id', $log->id)->where('type', 'cancel_production')->count());
    }

    public function test_only_owner_can_cancel_production_log(): void
    {
        $this->seed();
        $barista = User::where('email', 'dimas@cafestock.test')->firstOrFail();

        $this->actingAs($barista)->post('/production-logs/1/cancel')->assertForbidden();
        $this->assertDatabaseHas('production_logs', [
            'id' => 1,
            'status' => 'completed',
        ]);
    }

    public function test_stock_usage_draft_is_saved_without_changing_stock_or_movement(): void
    {
        $this->seed();
        $inventory = User::where('email', 'inventory@cafestock.test')->firstOrFail();

        $this->actingAs($inventory)->post('/stock-usages', [
            'usage_date' => '2026-05-06 11:00:00',
            'usage_type' => 'internal_use',
            'status' => 'draft',
            'notes' => 'Draft stock usage test',
            'items' => [
                ['ingredient_id' => 1, 'quantity' => 10, 'notes' => 'Training'],
            ],
        ])->assertRedirect();

        $usage = StockUsage::where('notes', 'Draft stock usage test')->firstOrFail();
        $this->assertMatchesRegularExpression('/^USE-20260506-\d{4}$/', $usage->usage_code);
        $this->assertSame('draft', $usage->status);
        $this->assertSame('1600.00', number_format((float) $usage->estimated_total_cost, 2, '.', ''));
        $this->assertSame('5200.000', number_format((float) Ingredient::findOrFail(1)->current_stock, 3, '.', ''));
        $this->assertFalse(StockMovement::where('reference_type', 'stock_usages')->where('reference_id', $usage->id)->exists());
    }

    public function test_stock_usage_completed_waste_reduces_stock_and_creates_waste_movement(): void
    {
        $this->seed();
        $inventory = User::where('email', 'inventory@cafestock.test')->firstOrFail();

        $this->actingAs($inventory)->post('/stock-usages', [
            'usage_date' => '2026-05-06 12:00:00',
            'usage_type' => 'waste',
            'status' => 'completed',
            'notes' => 'Waste completed test',
            'items' => [
                ['ingredient_id' => 4, 'quantity' => 100, 'notes' => 'Spill'],
            ],
        ])->assertRedirect();

        $usage = StockUsage::where('notes', 'Waste completed test')->firstOrFail();
        $this->assertSame('completed', $usage->status);
        $this->assertSame('2200.00', number_format((float) $usage->estimated_total_cost, 2, '.', ''));
        $this->assertSame('1800.000', number_format((float) Ingredient::findOrFail(4)->current_stock, 3, '.', ''));
        $this->assertDatabaseHas('stock_usage_items', [
            'stock_usage_id' => $usage->id,
            'ingredient_id' => 4,
            'quantity' => 100,
            'unit_cost_snapshot' => 22,
            'estimated_cost' => 2200,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'reference_type' => 'stock_usages',
            'reference_id' => $usage->id,
            'type' => 'waste',
            'quantity_out' => 100,
            'stock_after' => 1800,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'complete_usage',
            'module' => 'stock_usages',
            'reference_id' => $usage->id,
        ]);
    }

    public function test_stock_usage_completed_non_waste_creates_manual_usage_movement(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();

        $this->actingAs($owner)->post('/stock-usages', [
            'usage_date' => '2026-05-06 12:30:00',
            'usage_type' => 'sample',
            'status' => 'completed',
            'notes' => 'Sample completed test',
            'items' => [
                ['ingredient_id' => 6, 'quantity' => 10, 'notes' => 'Quality sample'],
            ],
        ])->assertRedirect();

        $usage = StockUsage::where('notes', 'Sample completed test')->firstOrFail();
        $this->assertSame('5120.000', number_format((float) Ingredient::findOrFail(6)->current_stock, 3, '.', ''));
        $this->assertDatabaseHas('stock_movements', [
            'reference_type' => 'stock_usages',
            'reference_id' => $usage->id,
            'type' => 'manual_usage',
            'quantity_out' => 10,
            'stock_after' => 5120,
        ]);
    }

    public function test_stock_usage_draft_can_be_updated_completed_or_deleted(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();
        $draft = StockUsage::where('usage_code', 'USE-20260504-0002')->firstOrFail();

        $this->actingAs($owner)->put("/stock-usages/{$draft->id}", [
            'usage_date' => '2026-05-06 13:00:00',
            'usage_type' => 'internal_use',
            'status' => 'completed',
            'notes' => 'Draft completed via update',
            'items' => [
                ['ingredient_id' => 2, 'quantity' => 10, 'notes' => 'Training'],
            ],
        ])->assertRedirect();

        $draft->refresh();
        $this->assertSame('completed', $draft->status);
        $this->assertSame('950.00', number_format((float) $draft->estimated_total_cost, 2, '.', ''));
        $this->assertSame('2190.000', number_format((float) Ingredient::findOrFail(2)->current_stock, 3, '.', ''));
        $this->assertSame(1, StockUsageItem::where('stock_usage_id', $draft->id)->count());
        $this->assertDatabaseHas('stock_movements', [
            'reference_type' => 'stock_usages',
            'reference_id' => $draft->id,
            'type' => 'manual_usage',
            'quantity_out' => 10,
        ]);

        $newDraft = StockUsage::create([
            'user_id' => $owner->id,
            'usage_code' => 'USE-20260506-9999',
            'usage_date' => '2026-05-06 14:00:00',
            'usage_type' => 'other',
            'status' => 'draft',
            'estimated_total_cost' => 0,
        ]);
        $this->actingAs($owner)->delete("/stock-usages/{$newDraft->id}")->assertRedirect();
        $this->assertDatabaseMissing('stock_usages', ['id' => $newDraft->id]);
    }

    public function test_stock_usage_completed_cannot_be_edited_or_deleted_directly(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();
        $completed = StockUsage::where('usage_code', 'USE-20260504-0001')->firstOrFail();

        $this->actingAs($owner)->put("/stock-usages/{$completed->id}", [
            'usage_date' => '2026-05-06 13:00:00',
            'usage_type' => 'waste',
            'status' => 'draft',
            'items' => [
                ['ingredient_id' => 4, 'quantity' => 1],
            ],
        ])->assertStatus(422);
        $this->actingAs($owner)->delete("/stock-usages/{$completed->id}")->assertStatus(422);

        $this->assertDatabaseHas('stock_usages', [
            'id' => $completed->id,
            'status' => 'completed',
        ]);
    }

    public function test_owner_can_cancel_completed_stock_usage_and_restore_stock_once(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();
        $usage = StockUsage::where('usage_code', 'USE-20260504-0001')->firstOrFail();

        $this->actingAs($owner)->post("/stock-usages/{$usage->id}/cancel")->assertRedirect();

        $usage->refresh();
        $this->assertSame('cancelled', $usage->status);
        $this->assertSame('2200.000', number_format((float) Ingredient::findOrFail(4)->current_stock, 3, '.', ''));
        $this->assertSame('1600.000', number_format((float) Ingredient::findOrFail(7)->current_stock, 3, '.', ''));
        $this->assertSame('1500.000', number_format((float) Ingredient::findOrFail(8)->current_stock, 3, '.', ''));
        $this->assertSame(3, StockMovement::where('reference_type', 'stock_usages')->where('reference_id', $usage->id)->where('type', 'cancel_usage')->count());
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'cancel_usage',
            'module' => 'stock_usages',
            'reference_id' => $usage->id,
        ]);

        $this->actingAs($owner)->post("/stock-usages/{$usage->id}/cancel")->assertSessionHasErrors('stock');
        $this->assertSame(3, StockMovement::where('reference_type', 'stock_usages')->where('reference_id', $usage->id)->where('type', 'cancel_usage')->count());
    }

    public function test_only_owner_can_cancel_stock_usage(): void
    {
        $this->seed();
        $inventory = User::where('email', 'inventory@cafestock.test')->firstOrFail();

        $this->actingAs($inventory)->post('/stock-usages/1/cancel')->assertForbidden();
        $this->assertDatabaseHas('stock_usages', [
            'id' => 1,
            'status' => 'completed',
        ]);
    }

    public function test_stock_usage_rejects_invalid_inactive_and_insufficient_stock_items(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();
        Ingredient::where('id', 1)->update(['is_active' => false]);

        $this->actingAs($owner)->post('/stock-usages', [
            'usage_date' => '2026-05-06 15:00:00',
            'usage_type' => 'waste',
            'status' => 'completed',
            'items' => [
                ['ingredient_id' => 1, 'quantity' => 1],
            ],
        ])->assertSessionHasErrors('items.0.ingredient_id');

        $this->actingAs($owner)->post('/stock-usages', [
            'usage_date' => '2026-05-06 15:00:00',
            'usage_type' => 'waste',
            'status' => 'completed',
            'items' => [
                ['ingredient_id' => 2, 'quantity' => 0],
            ],
        ])->assertSessionHasErrors('items.0.quantity');

        $this->actingAs($owner)->post('/stock-usages', [
            'usage_date' => '2026-05-06 15:00:00',
            'usage_type' => 'waste',
            'status' => 'completed',
            'notes' => 'Insufficient stock usage test',
            'items' => [
                ['ingredient_id' => 8, 'quantity' => 1],
            ],
        ])->assertSessionHasErrors('stock');

        $this->assertDatabaseMissing('stock_usages', ['notes' => 'Insufficient stock usage test']);
    }

    public function test_inventory_can_create_stock_adjustment_draft_without_changing_stock_or_movement(): void
    {
        $this->seed();
        $inventory = User::where('email', 'inventory@cafestock.test')->firstOrFail();

        $this->actingAs($inventory)->post('/stock-adjustments', [
            'adjustment_date' => '2026-05-06 16:00:00',
            'reason' => 'Stock opname draft test',
            'items' => [
                ['ingredient_id' => 1, 'counted_stock' => 5198, 'notes' => 'Counting difference'],
            ],
        ])->assertRedirect();

        $adjustment = StockAdjustment::where('reason', 'Stock opname draft test')->firstOrFail();
        $this->assertMatchesRegularExpression('/^ADJ-20260506-\d{4}$/', $adjustment->adjustment_code);
        $this->assertSame('draft', $adjustment->status);
        $this->assertSame('5200.000', number_format((float) Ingredient::findOrFail(1)->current_stock, 3, '.', ''));
        $this->assertDatabaseHas('stock_adjustment_items', [
            'stock_adjustment_id' => $adjustment->id,
            'ingredient_id' => 1,
            'system_stock' => 5200,
            'counted_stock' => 5198,
            'difference' => -2,
        ]);
        $this->assertFalse(StockMovement::where('reference_type', 'stock_adjustments')->where('reference_id', $adjustment->id)->exists());
    }

    public function test_stock_adjustment_draft_can_be_updated_and_approved_with_in_out_and_zero_difference(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();
        $draft = StockAdjustment::where('adjustment_code', 'ADJ-20260505-0001')->firstOrFail();

        $this->actingAs($owner)->put("/stock-adjustments/{$draft->id}", [
            'adjustment_date' => '2026-05-06 16:30:00',
            'reason' => 'Updated stock opname approval test',
            'items' => [
                ['ingredient_id' => 1, 'counted_stock' => 5195, 'notes' => 'Minus five'],
                ['ingredient_id' => 2, 'counted_stock' => 2210, 'notes' => 'Plus ten'],
                ['ingredient_id' => 6, 'counted_stock' => 5130, 'notes' => 'No difference'],
            ],
        ])->assertRedirect();

        $this->assertSame(3, StockAdjustmentItem::where('stock_adjustment_id', $draft->id)->count());

        $this->actingAs($owner)->post("/stock-adjustments/{$draft->id}/approve")->assertRedirect();

        $draft->refresh();
        $this->assertSame('approved', $draft->status);
        $this->assertSame($owner->id, $draft->approved_by);
        $this->assertNotNull($draft->approved_at);
        $this->assertSame('5195.000', number_format((float) Ingredient::findOrFail(1)->current_stock, 3, '.', ''));
        $this->assertSame('2210.000', number_format((float) Ingredient::findOrFail(2)->current_stock, 3, '.', ''));
        $this->assertSame('5130.000', number_format((float) Ingredient::findOrFail(6)->current_stock, 3, '.', ''));
        $this->assertDatabaseHas('stock_movements', [
            'reference_type' => 'stock_adjustments',
            'reference_id' => $draft->id,
            'ingredient_id' => 1,
            'type' => 'adjustment_out',
            'quantity_out' => 5,
            'stock_after' => 5195,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'reference_type' => 'stock_adjustments',
            'reference_id' => $draft->id,
            'ingredient_id' => 2,
            'type' => 'adjustment_in',
            'quantity_in' => 10,
            'stock_after' => 2210,
        ]);
        $this->assertSame(2, StockMovement::where('reference_type', 'stock_adjustments')->where('reference_id', $draft->id)->count());
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'approve_adjustment',
            'module' => 'stock_adjustments',
            'reference_id' => $draft->id,
        ]);
    }

    public function test_stock_adjustment_approved_cannot_be_edited_or_approved_again(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();
        $approved = StockAdjustment::where('adjustment_code', 'ADJ-20260504-0001')->firstOrFail();

        $this->actingAs($owner)->put("/stock-adjustments/{$approved->id}", [
            'adjustment_date' => '2026-05-06 17:00:00',
            'reason' => 'Should not update',
            'items' => [
                ['ingredient_id' => 1, 'counted_stock' => 5200],
            ],
        ])->assertStatus(422);
        $this->actingAs($owner)->post("/stock-adjustments/{$approved->id}/approve")->assertSessionHasErrors('stock');

        $this->assertDatabaseHas('stock_adjustments', [
            'id' => $approved->id,
            'status' => 'approved',
            'reason' => 'Selisih hasil timbang Arabica Beans saat closing stock opname',
        ]);
    }

    public function test_owner_can_cancel_approved_stock_adjustment_and_restore_stock_once(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();
        $adjustment = StockAdjustment::where('adjustment_code', 'ADJ-20260504-0001')->firstOrFail();

        $this->actingAs($owner)->post("/stock-adjustments/{$adjustment->id}/cancel")->assertRedirect();

        $adjustment->refresh();
        $this->assertSame('cancelled', $adjustment->status);
        $this->assertSame('5220.000', number_format((float) Ingredient::findOrFail(1)->current_stock, 3, '.', ''));
        $this->assertDatabaseHas('stock_movements', [
            'reference_type' => 'stock_adjustments',
            'reference_id' => $adjustment->id,
            'ingredient_id' => 1,
            'type' => 'cancel_adjustment',
            'quantity_in' => 20,
            'stock_after' => 5220,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'cancel_adjustment',
            'module' => 'stock_adjustments',
            'reference_id' => $adjustment->id,
        ]);

        $this->actingAs($owner)->post("/stock-adjustments/{$adjustment->id}/cancel")->assertSessionHasErrors('stock');
        $this->assertSame(1, StockMovement::where('reference_type', 'stock_adjustments')->where('reference_id', $adjustment->id)->where('type', 'cancel_adjustment')->count());
    }

    public function test_owner_can_cancel_draft_stock_adjustment_without_movement(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();
        $draft = StockAdjustment::where('adjustment_code', 'ADJ-20260505-0001')->firstOrFail();

        $this->actingAs($owner)->post("/stock-adjustments/{$draft->id}/cancel")->assertRedirect();

        $draft->refresh();
        $this->assertSame('cancelled', $draft->status);
        $this->assertFalse(StockMovement::where('reference_type', 'stock_adjustments')->where('reference_id', $draft->id)->exists());
    }

    public function test_only_owner_can_approve_or_cancel_stock_adjustment(): void
    {
        $this->seed();
        $inventory = User::where('email', 'inventory@cafestock.test')->firstOrFail();

        $this->actingAs($inventory)->post('/stock-adjustments/2/approve')->assertForbidden();
        $this->actingAs($inventory)->post('/stock-adjustments/1/cancel')->assertForbidden();
        $this->assertDatabaseHas('stock_adjustments', ['id' => 2, 'status' => 'draft']);
        $this->assertDatabaseHas('stock_adjustments', ['id' => 1, 'status' => 'approved']);
    }

    public function test_stock_adjustment_rejects_missing_reason_inactive_ingredient_and_invalid_counted_stock(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@cafestock.test')->firstOrFail();
        Ingredient::where('id', 1)->update(['is_active' => false]);

        $this->actingAs($owner)->post('/stock-adjustments', [
            'adjustment_date' => '2026-05-06 18:00:00',
            'reason' => '',
            'items' => [
                ['ingredient_id' => 2, 'counted_stock' => 2200],
            ],
        ])->assertSessionHasErrors('reason');

        $this->actingAs($owner)->post('/stock-adjustments', [
            'adjustment_date' => '2026-05-06 18:00:00',
            'reason' => 'Invalid ingredient',
            'items' => [
                ['ingredient_id' => 1, 'counted_stock' => 5200],
            ],
        ])->assertSessionHasErrors('items.0.ingredient_id');

        $this->actingAs($owner)->post('/stock-adjustments', [
            'adjustment_date' => '2026-05-06 18:00:00',
            'reason' => 'Invalid counted stock',
            'items' => [
                ['ingredient_id' => 2, 'counted_stock' => -1],
            ],
        ])->assertSessionHasErrors('items.0.counted_stock');
    }

    public function test_production_is_rejected_when_stock_is_not_enough(): void
    {
        $this->seed();
        $barista = User::where('email', 'dimas@cafestock.test')->firstOrFail();

        $this->actingAs($barista)->post(route('production-logs.store'), [
            'menu_item_id' => 4,
            'quantity' => 100,
            'production_date' => '2026-05-06 10:00:00',
        ])->assertSessionHasErrors('stock');
    }
}
