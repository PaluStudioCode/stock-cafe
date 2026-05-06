<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredient_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('symbol', 20);
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->index();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_category_id')->constrained()->cascadeOnUpdate();
            $table->foreignId('unit_id')->constrained()->cascadeOnUpdate();
            $table->foreignId('primary_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name', 150)->index();
            $table->string('image_path')->nullable();
            $table->unsignedDecimal('last_unit_cost', 15, 2)->default(0);
            $table->unsignedDecimal('current_stock', 15, 3)->default(0);
            $table->unsignedDecimal('minimum_stock', 15, 3)->default(0)->index();
            $table->unsignedDecimal('reorder_level', 15, 3)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index(['ingredient_category_id', 'is_active']);
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150)->index();
            $table->string('category', 100)->nullable()->index();
            $table->unsignedDecimal('selling_price', 15, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->unsignedDecimal('quantity_per_serving', 15, 3);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['menu_item_id', 'ingredient_id']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('purchase_code', 30)->unique();
            $table->date('purchase_date')->index();
            $table->timestamp('received_at')->nullable();
            $table->unsignedDecimal('subtotal', 15, 2)->default(0);
            $table->unsignedDecimal('discount', 15, 2)->default(0);
            $table->unsignedDecimal('total_amount', 15, 2)->default(0);
            $table->enum('status', ['draft', 'received'])->default('draft')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->unsignedDecimal('quantity', 15, 3);
            $table->unsignedDecimal('unit_cost', 15, 2);
            $table->unsignedDecimal('subtotal', 15, 2);
            $table->timestamps();
        });

        Schema::create('production_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('production_code', 30)->unique();
            $table->dateTime('production_date')->index();
            $table->unsignedDecimal('quantity', 15, 3);
            $table->unsignedDecimal('estimated_total_cost', 15, 2)->default(0);
            $table->enum('status', ['completed', 'cancelled'])->default('completed')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('production_log_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->unsignedDecimal('quantity_per_serving_snapshot', 15, 3);
            $table->unsignedDecimal('quantity_used', 15, 3);
            $table->unsignedDecimal('unit_cost_snapshot', 15, 2);
            $table->unsignedDecimal('estimated_cost', 15, 2);
            $table->timestamps();
        });

        Schema::create('stock_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('usage_code', 30)->unique();
            $table->dateTime('usage_date')->index();
            $table->enum('usage_type', ['waste', 'expired', 'damaged', 'internal_use', 'sample', 'other'])->index();
            $table->unsignedDecimal('estimated_total_cost', 15, 2)->default(0);
            $table->enum('status', ['draft', 'completed', 'cancelled'])->default('draft')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_usage_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_usage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->unsignedDecimal('quantity', 15, 3);
            $table->unsignedDecimal('unit_cost_snapshot', 15, 2);
            $table->unsignedDecimal('estimated_cost', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('adjustment_code', 30)->unique();
            $table->dateTime('adjustment_date')->index();
            $table->enum('status', ['draft', 'approved', 'cancelled'])->default('draft')->index();
            $table->text('reason');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->unsignedDecimal('system_stock', 15, 3);
            $table->unsignedDecimal('counted_stock', 15, 3);
            $table->decimal('difference', 15, 3);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', [
                'opening_stock', 'purchase_receipt', 'production_usage', 'manual_usage', 'waste',
                'adjustment_in', 'adjustment_out', 'cancel_production', 'cancel_usage', 'cancel_adjustment',
            ])->index();
            $table->string('reference_type', 80)->index();
            $table->unsignedBigInteger('reference_id')->nullable()->index();
            $table->unsignedDecimal('quantity_in', 15, 3)->default(0);
            $table->unsignedDecimal('quantity_out', 15, 3)->default(0);
            $table->unsignedDecimal('stock_before', 15, 3);
            $table->unsignedDecimal('stock_after', 15, 3);
            $table->unsignedDecimal('unit_cost_snapshot', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['ingredient_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80)->index();
            $table->string('module', 80)->index();
            $table->text('description');
            $table->string('reference_type', 80)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'settings', 'activity_logs', 'stock_movements', 'stock_adjustment_items', 'stock_adjustments',
            'stock_usage_items', 'stock_usages', 'production_log_items', 'production_logs',
            'purchase_order_items', 'purchase_orders', 'recipe_items', 'menu_items', 'ingredients',
            'suppliers', 'units', 'ingredient_categories',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
