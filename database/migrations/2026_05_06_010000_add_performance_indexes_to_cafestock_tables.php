<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->index(['current_stock', 'minimum_stock'], 'ingredients_stock_threshold_idx');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->index(['status', 'purchase_date'], 'po_status_date_idx');
            $table->index(['supplier_id', 'status', 'purchase_date'], 'po_supplier_status_date_idx');
        });

        Schema::table('production_logs', function (Blueprint $table) {
            $table->index(['status', 'production_date'], 'prod_status_date_idx');
            $table->index(['menu_item_id', 'status', 'production_date'], 'prod_menu_status_date_idx');
            $table->index(['user_id', 'status', 'production_date'], 'prod_user_status_date_idx');
        });

        Schema::table('stock_usages', function (Blueprint $table) {
            $table->index(['status', 'usage_date'], 'usage_status_date_idx');
            $table->index(['usage_type', 'status', 'usage_date'], 'usage_type_status_date_idx');
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->index(['status', 'adjustment_date'], 'adjustment_status_date_idx');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['ingredient_id', 'type', 'created_at'], 'movement_ingredient_type_date_idx');
            $table->index(['reference_type', 'reference_id', 'type'], 'movement_reference_type_idx');
            $table->index(['reference_type', 'created_at'], 'movement_reference_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('movement_reference_date_idx');
            $table->dropIndex('movement_reference_type_idx');
            $table->dropIndex('movement_ingredient_type_date_idx');
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropIndex('adjustment_status_date_idx');
        });

        Schema::table('stock_usages', function (Blueprint $table) {
            $table->dropIndex('usage_type_status_date_idx');
            $table->dropIndex('usage_status_date_idx');
        });

        Schema::table('production_logs', function (Blueprint $table) {
            $table->dropIndex('prod_user_status_date_idx');
            $table->dropIndex('prod_menu_status_date_idx');
            $table->dropIndex('prod_status_date_idx');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex('po_supplier_status_date_idx');
            $table->dropIndex('po_status_date_idx');
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropIndex('ingredients_stock_threshold_idx');
        });
    }
};
