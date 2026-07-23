<?php

namespace App\Services;

use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Transaction;

class MidtransService
{
    public function __construct()
    {
        // Setup Midtrans configuration
        Config::$serverKey = config('midtrans.server_key');
        Config::$clientKey = config('midtrans.client_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    /**
     * Create Snap transaction (untuk semua payment method)
     * Return snap token yang bisa digunakan di frontend
     */
    public function createTransaction($order, $payment_method)
    {
        try {
            $transaction_details = [
                'order_id' => $order->order_number . '-' . time(),
                'gross_amount' => (int) $order->total_amount,
            ];

            $customer_details = [
                'first_name' => $order->user->name,
                'email' => $order->user->email,
            ];

            $item_details = [];
            foreach ($order->orderItems as $item) {
                $item_details[] = [
                    'id' => $item->product_id,
                    'price' => (int) $item->unit_price,
                    'quantity' => $item->quantity,
                    'name' => $item->product->name,
                ];
            }

            // Base transaction data
            $transaction_data = [
                'transaction_details' => $transaction_details,
                'customer_details' => $customer_details,
                'item_details' => $item_details,
                'callbacks' => [
                    'finish' => env('APP_URL') . '/payment-finish',
                    'error' => env('APP_URL') . '/payment-error',
                    'pending' => env('APP_URL') . '/payment-pending',
                ],
            ];

            // Add payment method specific config
            $transaction_data = $this->addPaymentMethodConfig($transaction_data, $payment_method);

            // Create Snap transaction
            $snap_token = Snap::getSnapToken($transaction_data);

            return [
                'snap_token' => $snap_token,
                'order_id' => $transaction_details['order_id'],
                'gross_amount' => $transaction_details['gross_amount'],
            ];
        } catch (\Exception $e) {
            throw new \Exception('Midtrans Error: ' . $e->getMessage());
        }
    }

    /**
     * Add payment method specific configuration
     */
    private function addPaymentMethodConfig($transaction_data, $payment_method)
    {
        switch ($payment_method) {
            case 'QRIS':
                // QRIS Configuration
                $transaction_data['payment_type'] = 'qris';
                break;

            case 'CARD':
                // Credit Card Configuration
                $transaction_data['payment_type'] = 'credit_card';
                $transaction_data['credit_card'] = [
                    'secure' => true,
                ];
                break;

            case 'E_WALLET':
                // E-Wallet (GoPay) Configuration
                $transaction_data['payment_type'] = 'gopay';
                $transaction_data['gopay'] = [
                    'enable_callback' => true,
                    'callback_url' => env('APP_URL') . '/api/v1/payments/midtrans-callback',
                ];
                break;

            default:
                throw new \Exception("Unsupported payment method: {$payment_method}");
        }

        return $transaction_data;
    }

    /**
     * Get transaction status from Midtrans
     */
    public function getTransactionStatus($transaction_id)
    {
        try {
            $status = Transaction::status($transaction_id);
            return $status;
        } catch (\Exception $e) {
            throw new \Exception('Failed to get transaction status: ' . $e->getMessage());
        }
    }

    /**
     * Verify notification signature from Midtrans (untuk webhook)
     */
    public function verifyNotification($notification_key, $order_id, $status_code, $gross_amount)
    {
        $server_key = config('midtrans.server_key');
        $signature_key = hash('sha512', $order_id . $status_code . $gross_amount . $server_key);

        return $signature_key === $notification_key;
    }
}
