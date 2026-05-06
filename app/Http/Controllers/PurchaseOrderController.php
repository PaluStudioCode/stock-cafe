<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Ingredient;
use App\Services\StockService;
use App\Support\CafeStock;
use App\Support\CafeStockMath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use RuntimeException;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        return Inertia::render('Transactions/PurchaseOrders', [
            'orders' => PurchaseOrder::with(['supplier', 'user', 'items.ingredient'])->latest('id')->paginate(20),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'ingredients' => Ingredient::with('unit:id,name,symbol')->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $request) {
            [$subtotal, $discount, $total] = $this->totals($data);
            $order = PurchaseOrder::create([
                'supplier_id' => $data['supplier_id'],
                'user_id' => $request->user()->id,
                'purchase_code' => CafeStock::code('PO', 'purchase_orders', 'purchase_code', $data['purchase_date']),
                'purchase_date' => $data['purchase_date'],
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total_amount' => $total,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
            ]);
            $this->replaceItems($order, $data['items']);
        });

        return back()->with('success', 'Draf pesanan pembelian dibuat.');
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        abort_unless($purchaseOrder->status === 'draft', 422, 'Pesanan pembelian yang sudah diterima tidak dapat diubah.');

        $data = $this->validated($request);

        DB::transaction(function () use ($data, $purchaseOrder) {
            $purchaseOrder = PurchaseOrder::lockForUpdate()->findOrFail($purchaseOrder->id);
            abort_unless($purchaseOrder->status === 'draft', 422, 'Pesanan pembelian yang sudah diterima tidak dapat diubah.');

            [$subtotal, $discount, $total] = $this->totals($data);
            $purchaseOrder->update([
                'supplier_id' => $data['supplier_id'],
                'purchase_date' => $data['purchase_date'],
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total_amount' => $total,
                'notes' => $data['notes'] ?? null,
            ]);
            $this->replaceItems($purchaseOrder, $data['items']);
        });

        return back()->with('success', 'Draf pesanan pembelian diperbarui.');
    }

    public function receive(PurchaseOrder $purchaseOrder, StockService $stock)
    {
        try {
            $stock->receivePurchaseOrder($purchaseOrder, auth()->id());
        } catch (RuntimeException $e) {
            return back()->withErrors(['stock' => $e->getMessage()]);
        }

        return back()->with('success', 'Pesanan pembelian diterima dan stok bertambah.');
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        abort_unless($purchaseOrder->status === 'draft', 422, 'Pesanan pembelian yang sudah diterima tidak dapat dihapus.');
        $purchaseOrder->delete();

        return back()->with('success', 'Draf pesanan pembelian dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('is_active', true)],
            'purchase_date' => ['required', 'date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', Rule::exists('ingredients', 'id')->where('is_active', true)],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);
    }

    private function totals(array $data): array
    {
        $subtotal = CafeStockMath::purchaseSubtotal($data['items']);
        $discount = (float) ($data['discount'] ?? 0);
        abort_if($discount > $subtotal, 422, 'Diskon tidak boleh melebihi subtotal.');

        return [$subtotal, $discount, CafeStockMath::purchaseTotal($subtotal, $discount)];
    }

    private function replaceItems(PurchaseOrder $order, array $items): void
    {
        $order->items()->delete();

        foreach ($items as $item) {
            PurchaseOrderItem::create([
                'purchase_order_id' => $order->id,
                'ingredient_id' => $item['ingredient_id'],
                'quantity' => $item['quantity'],
                'unit_cost' => $item['unit_cost'],
                'subtotal' => CafeStockMath::lineSubtotal($item['quantity'], $item['unit_cost']),
            ]);
        }
    }
}
