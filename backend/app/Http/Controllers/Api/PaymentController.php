<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    /**
     * Get payments for order
     * GET /api/v1/orders/{id}/payments
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

            $payments = Payment::where('order_id', $orderId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'order' => $order,
                    'payments' => $payments,
                    'total_paid' => $payments->where('status', 'COMPLETED')->sum('amount'),
                    'remaining' => $order->total_amount - $payments->where('status', 'COMPLETED')->sum('amount'),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payments: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process payment for order (Kasir)
     * POST /api/v1/orders/{id}/payments
     * Body: {
     *   "amount": 50000,
     *   "method": "CASH"
     * }
     */
    public function store(Request $request, $orderId)
    {
        try {
            $order = Order::find($orderId);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            // Validate input
            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'method' => 'required|in:CASH,CARD,E_WALLET',
            ]);

            // Create payment
            $payment = Payment::create([
                'order_id' => $order->id,
                'amount' => $validated['amount'],
                'method' => $validated['method'],
                'status' => 'COMPLETED',
                'completed_at' => now(),
            ]);

            // Calculate total paid
            $totalPaid = Payment::where('order_id', $order->id)
                ->where('status', 'COMPLETED')
                ->sum('amount');

            // Update order payment status
            if ($totalPaid >= $order->total_amount) {
                $order->update(['payment_status' => 'PAID']);
            } elseif ($totalPaid > 0) {
                $order->update(['payment_status' => 'PARTIAL']);
            }

            // Audit log
            AuditLog::create([
                'user_id' => auth('api')->user()->id,
                'action' => 'PROCESS_PAYMENT',
                'details' => [
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'amount' => $validated['amount'],
                    'method' => $validated['method'],
                    'total_paid' => $totalPaid,
                ],
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully',
                'data' => [
                    'payment' => $payment,
                    'order' => $order,
                    'total_paid' => $totalPaid,
                    'remaining' => max(0, $order->total_amount - $totalPaid),
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process payment: ' . $e->getMessage(),
            ], 500);
        }
    }
}
