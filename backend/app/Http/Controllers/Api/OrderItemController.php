<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class OrderItemController extends Controller
{
     /**
     * Get order items for specific order
     * GET /api/v1/orders/{orderId}/items
     */
    public function index($orderId)
    {
        try {
            $order = Order::find($orderId);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            $items = OrderItem::where('order_id', $orderId)
                ->with(['product', 'preparedByUser'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $items,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch order items: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark item as done (Barista)
     * PUT /api/v1/orders/{orderId}/items/{itemId}
     * Body: {"status": "DONE"}
     */
    public function update(Request $request, $orderId, $itemId)
    {
        try {
            $order = Order::find($orderId);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            // PRE-PAYMENT CONSTRAINT: Check if order is paid
            if ($order->payment_status !== 'PAID') {
                return response()->json([
                    'success' => false,
                    'message' => 'Order belum dibayar. Barista tidak bisa prepare sampai customer bayar.',
                    'order_number' => $order->order_number,
                    'payment_status' => $order->payment_status,
                    'total_amount' => $order->total_amount,
                ], 422);
            }

            $item = OrderItem::where('id', $itemId)
                ->where('order_id', $orderId)
                ->first();

            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order item not found',
                ], 404);
            }

            // Validate status
            $validated = $request->validate([
                'status' => 'required|in:PENDING,PREPARING,DONE',
            ]);

            $oldStatus = $item->status;
            $barista = auth('api')->user();

            // Update item
            $item->update([
                'status' => $validated['status'],
                'prepared_by' => $barista->id,
            ]);

            // Audit log
            AuditLog::create([
                'user_id' => $barista->id,
                'action' => 'UPDATE_ORDER_ITEM',
                'details' => [
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'product_name' => $item->product->name,
                    'old_status' => $oldStatus,
                    'new_status' => $validated['status'],
                    'prepared_by' => $barista->name,
                ],
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order item updated successfully',
                'data' => $item->load(['product', 'preparedByUser']),
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
                'message' => 'Failed to update order item: ' . $e->getMessage(),
            ], 500);
        }
    }
}
