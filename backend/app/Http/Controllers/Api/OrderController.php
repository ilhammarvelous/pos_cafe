<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\InventoryLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Order::with(['user', 'orderItems.product']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by payment_status
            if ($request->has('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            // Filter by date
            if ($request->has('date')) {
                $query->whereDate('created_at', $request->date);
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $orders->items(),
                'meta' => [
                    'total' => $orders->total(),
                    'per_page' => $orders->perPage(),
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch orders: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $order = Order::with(['user', 'orderItems.product', 'orderItems.preparedByUser', 'payments'])
                ->find($id);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $order,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch order: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            // Validate input
            $validated = $request->validate([
                'table_number' => 'required|integer|min:1',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.notes' => 'nullable|string',
            ]);

            // Generate order number (auto-increment)
            $lastOrder = Order::orderBy('order_number', 'desc')->first();
            $orderNumber = $lastOrder ? $lastOrder->order_number + 1 : 1001;

            // Create order
            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => auth('api')->user()->id,
                'table_number' => $validated['table_number'],
                'status' => 'PENDING',
                'payment_status' => 'UNPAID',
            ]);

            // Create order items & calculate total
            $totalAmount = 0;

            foreach ($validated['items'] as $item) {
                $product = Product::find($item['product_id']);

                if (!$product) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Product not found: ' . $item['product_id'],
                    ], 404);
                }

                // Create order item
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'notes' => $item['notes'] ?? null,
                    'status' => 'PENDING',
                ]);

                // Calculate total
                $totalAmount += $product->price * $item['quantity'];

                // Create inventory log (SALE)
                InventoryLog::create([
                    'product_id' => $product->id,
                    'quantity_change' => -$item['quantity'],
                    'reason' => 'SALE',
                    'created_by' => auth('api')->user()->id,
                ]);

                // Update product stock
                $product->update([
                    'stock_quantity' => $product->stock_quantity - $item['quantity'],
                ]);
            }

            // Update order total
            $order->update(['total_amount' => $totalAmount]);

            // Audit log
            AuditLog::create([
                'user_id' => auth('api')->user()->id,
                'action' => 'Membuat Pesanan',
                'details' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'total_amount' => $totalAmount,
                    'items_count' => count($validated['items']),
                ],
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $order->load(['user', 'orderItems.product']),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update order status (Kasir/Barista/Manager)
     * PUT /api/v1/orders/{id}
     * Body: {"status": "PREPARING"}
     */
    public function update(Request $request, $id)
    {
        try {
            $order = Order::find($id);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pesanan tidak ditemukan',
                ], 404);
            }

            // PRE-PAYMENT CONSTRAINT: Check if order is paid
            if ($order->payment_status !== 'PAID') {
                return response()->json([
                    'success' => false,
                    'message' => 'Order belum dibayar. Status tidak bisa diubah sampai customer bayar.',
                    'order_number' => $order->order_number,
                    'payment_status' => $order->payment_status,
                    'total_amount' => $order->total_amount,
                ], 422);
            }

            // Validate status
            $validated = $request->validate([
                'status' => 'required|in:PENDING,PREPARING,READY,COMPLETED,CANCELLED',
            ]);

            $oldStatus = $order->status;
            $order->update(['status' => $validated['status']]);

            // If order completed, set completed_at
            if ($validated['status'] === 'COMPLETED') {
                $order->update(['completed_at' => now()]);
            }

            // Audit log
            AuditLog::create([
                'user_id' => auth('api')->user()->id,
                'action' => 'Memperbarui Status Pesanan',
                'details' => [
                    'order_id' => $order->id,
                    'old_status' => $oldStatus,
                    'new_status' => $validated['status'],
                ],
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully',
                'data' => $order,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel order (Kasir/Manager)
     * DELETE /api/v1/orders/{id}
     */
    public function destroy(Request $request, $id)
    {
        try {
            $order = Order::find($id);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            // Check if order can be cancelled
            if (in_array($order->status, ['COMPLETED', 'CANCELLED'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot cancel completed or already cancelled order',
                ], 422);
            }

            // Restore inventory
            $orderItems = OrderItem::where('order_id', $order->id)->get();

            foreach ($orderItems as $item) {
                $product = Product::find($item->product_id);
                $product->update([
                    'stock_quantity' => $product->stock_quantity + $item->quantity,
                ]);

                // Create inventory log (reverse)
                InventoryLog::create([
                    'product_id' => $product->id,
                    'quantity_change' => $item->quantity,
                    'reason' => 'ADJUSTMENT', // Cancelled order
                    'created_by' => auth('api')->user()->id,
                ]);
            }

            // Update order status
            $order->update(['status' => 'CANCELLED']);

            // Audit log
            AuditLog::create([
                'user_id' => auth('api')->user()->id,
                'action' => 'CANCEL_ORDER',
                'details' => ['order_id' => $order->id, 'order_number' => $order->order_number],
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order: ' . $e->getMessage(),
            ], 500);
        }
    }
}
