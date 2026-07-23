<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Transaction;

class MidtransWebhookController extends Controller
{
    protected $midtransService;

    public function __construct(MidtransService $midtransService)
    {
        $this->midtransService = $midtransService;
    }

    /**
     * Handle Midtrans webhook callback
     * POST /api/v1/payments/midtrans-callback
     *
     * Midtrans sends notification saat:
     * - Payment successful
     * - Payment failed
     * - Payment pending
     */
    public function handleCallback(Request $request)
    {
        try {
            // Setup Midtrans config untuk verify signature
            Config::$serverKey = config('midtrans.server_key');
            Config::$clientKey = config('midtrans.client_key');
            Config::$isProduction = config('midtrans.is_production');

            $json = $request->getContent();
            $notification = json_decode($json);

            // Log webhook untuk debugging
            Log::info('Midtrans Webhook Received', [
                'transaction_id' => $notification->transaction_id,
                'order_id' => $notification->order_id,
                'transaction_status' => $notification->transaction_status,
            ]);

            // Get transaction status from Midtrans (verifikasi)
            $transactionData = Transaction::status($notification->transaction_id);

            // Find payment by Midtrans transaction ID
            $payment = Payment::where('midtrans_transaction_id', $notification->order_id)->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found',
                ], 404);
            }

            // Cast array to object (Midtrans returns array, we need object)
            $transaction = (object) $transactionData;

            $order = Order::find($payment->order_id);

            // Handle transaction status
            $fraud_status = $transaction->fraud_status ?? 'accept';

            if ($transaction->transaction_status == 'capture') {
                // Credit card payment successful
                if ($fraud_status == 'challenge') {
                    $payment->update(['status' => 'PENDING']);
                    $order->update(['payment_status' => 'PARTIAL']);
                } elseif ($fraud_status == 'accept') {
                    $payment->update([
                        'status' => 'COMPLETED',
                        'completed_at' => now(),
                    ]);
                    $order->update([
                        'payment_status' => 'PAID',
                        'payment_method' => 'CARD',
                    ]);
                    $this->logAudit($order, 'Payment successful (CARD)');
                }
            } elseif ($transaction->transaction_status == 'settlement') {
                // Payment settled
                $payment->update([
                    'status' => 'COMPLETED',
                    'completed_at' => now(),
                ]);
                $order->update([
                    'payment_status' => 'PAID',
                    'payment_method' => $this->getPaymentMethod($transaction),
                ]);
                $this->logAudit($order, 'Payment settled via ' . $this->getPaymentMethod($transaction));

            } elseif ($transaction->transaction_status == 'pending') {
                // Payment pending (customer belum bayar)
                $payment->update(['status' => 'PENDING']);
                $order->update(['payment_status' => 'PENDING']);
                $this->logAudit($order, 'Payment pending - waiting for customer');

            } elseif ($transaction->transaction_status == 'deny') {
                // Payment denied
                $payment->update(['status' => 'FAILED']);
                $order->update(['payment_status' => 'UNPAID']);
                $this->logAudit($order, 'Payment failed - rejected by bank');

            } elseif ($transaction->transaction_status == 'cancel' ||
                    $transaction->transaction_status == 'expire') {
                // Payment cancelled or expired
                $payment->update(['status' => 'FAILED']);
                $order->update(['payment_status' => 'UNPAID']);
                $this->logAudit($order, 'Payment cancelled or expired');

            } elseif ($transaction->transaction_status == 'refund') {
                // Payment refunded
                $payment->update(['status' => 'FAILED']);
                $order->update(['payment_status' => 'UNPAID']);
                $this->logAudit($order, 'Payment refunded');
            }

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Midtrans Webhook Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get payment method from transaction type
     */
    private function getPaymentMethod($transaction)
    {
        if (isset($transaction->payment_type)) {
            return match($transaction->payment_type) {
                'credit_card' => 'CARD',
                'gopay' => 'E_WALLET',
                'qris' => 'QRIS',
                default => 'MIDTRANS',
            };
        }
        return 'MIDTRANS';
    }

    /**
     * Log audit event
     */
    private function logAudit($order, $message)
    {
        AuditLog::create([
            'user_id' => null,
            'action' => 'MIDTRANS_CALLBACK',
            'details' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'message' => $message,
            ],
            'ip_address' => request()->ip(),
        ]);
    }
}
