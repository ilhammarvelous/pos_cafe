<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{

    protected $midtransService;

    public function __construct(MidtransService $midtransService)
    {
        $this->midtransService = $midtransService;
    }

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
     * Process payment for order
     * POST /api/v1/orders/{id}/payments
     *
     * Pre-payment model: Customer must pay BEFORE barista can prepare
     *
     * Body: {
     *   "amount": 95000,
     *   "method": "CASH" | "CARD" | "E_WALLET" | "QRIS"
     * }
     */
    public function store(Request $request, $orderId)
    {
        try {
            $order = Order::with('orderItems.product', 'user')->find($orderId);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            // Validate input
            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'method' => 'required|in:CASH,CARD,E_WALLET,QRIS',
            ]);

            // Check: if order already paid
            if ($order->payment_status === 'PAID') {
                return response()->json([
                    'success' => false,
                    'message' => 'Order already paid',
                ], 422);
            }

            // Check: payment amount must match order total (pre-payment model)
            if ($validated['amount'] != $order->total_amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount must match order total',
                    'order_total' => $order->total_amount,
                    'payment_amount' => $validated['amount'],
                ], 422);
            }

            // Handle CASH payment (direct)
            if ($validated['method'] === 'CASH') {
                return $this->processCashPayment($order, $validated);
            }

            // Handle Midtrans payment (CARD, E_WALLET, QRIS)
            return $this->processMidtransPayment($order, $validated);


        } catch (\Illuminate\Validation\ValidationException $e) {
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

    /**
     * Process CASH payment (direct, no Midtrans)
     */
    private function processCashPayment($order, $validated)
    {
        try {
            // Create payment record
            $payment = Payment::create([
                'order_id' => $order->id,
                'amount' => $validated['amount'],
                'method' => $validated['method'],
                'status' => 'COMPLETED',
                'completed_at' => now(),
            ]);

            // Update order
            $order->update([
                'payment_status' => 'PAID',
                'payment_method' => $validated['method'],
            ]);

            // Audit log
            AuditLog::create([
                'user_id' => auth('api')->user()->id,
                'action' => 'PROCESS_PAYMENT',
                'details' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'payment_method' => 'CASH',
                    'amount' => $validated['amount'],
                ],
                'ip_address' => request()->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully (CASH)',
                'data' => [
                    'payment' => $payment,
                    'order' => $order,
                    'payment_status' => 'PAID',
                    'payment_type' => 'direct',
                ],
            ], 201);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Process Midtrans payment (CARD, E_WALLET, QRIS)
     */
    private function processMidtransPayment($order, $validated)
    {
        try {
            // Create pending payment record
            $payment = Payment::create([
                'order_id' => $order->id,
                'amount' => $validated['amount'],
                'method' => $validated['method'],
                'status' => 'PENDING',
            ]);

            // Create Midtrans transaction
            $midtrans_response = $this->midtransService->createTransaction($order, $validated['method']);

            // Save transaction ID
            $payment->update([
                'midtrans_transaction_id' => $midtrans_response['order_id'],
            ]);

            // Audit log
            AuditLog::create([
                'user_id' => auth('api')->user()->id,
                'action' => 'CREATE_MIDTRANS_TRANSACTION',
                'details' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'payment_method' => $validated['method'],
                    'amount' => $validated['amount'],
                    'midtrans_transaction_id' => $midtrans_response['order_id'],
                ],
                'ip_address' => request()->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment transaction created. Please complete payment.',
                'data' => [
                    'payment' => $payment,
                    'order' => [
                        'order_number' => $order->order_number,
                        'total_amount' => $order->total_amount,
                        'payment_status' => 'PENDING',
                    ],
                    'midtrans' => [
                        'snap_token' => $midtrans_response['snap_token'],
                        'transaction_id' => $midtrans_response['order_id'],
                        'payment_method' => $validated['method'],
                    ],
                    'payment_type' => 'midtrans',
                ],
            ], 201);
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
