<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MercadoPagoOrderService {
    private string $baseUrl = 'https://api.mercadopago.com';
    private string $accessToken;

    public function __construct() {
        $this->accessToken = config('services.mercado_pago.access_token');
    }

    /**
     * Crear una orden local (no en MP, solo para tracking)
     * Retorna un ID de orden para que el cliente pueda hacer pago
     */
    public function createOrder(array $items, float $totalAmount): array {
        // Generate order ID locally
        $orderId = uniqid('order_', true);

        return [
            'id' => $orderId,
            'total_amount' => $totalAmount,
            'items' => $items,
        ];
    }

    /**
     * Procesar pago via Mercado Pago Payments API (Checkout API)
     * POST /v1/payments
     * 
     * En development mode, acepta tokens de prueba (empiezan con "test_")
     */
    public function processOrderPayment(string $orderId, array $paymentData): array {
        $token = $paymentData['token'];

        // In development mode, accept test tokens
        if (app()->isLocal() && str_starts_with($token, 'test_')) {
            return $this->simulateTestPayment($orderId, $paymentData);
        }

        // Production: use real Mercado Pago API
        $payload = [
            'transaction_amount' => (float) $paymentData['amount'],
            'payment_method_id' => $paymentData['payment_method_id'] ?? 'credit_card',
            'token' => $token,
            'payer' => [
                'email' => $paymentData['payer_email'] ?? null,
            ],
            'installments' => (int) ($paymentData['installments'] ?? 1),
            'description' => "Order {$orderId}",
        ];

        $response = Http::withToken($this->accessToken)
            ->post("{$this->baseUrl}/v1/payments", $payload);

        if (!$response->successful()) {
            throw new \Exception('Error processing payment: ' . $response->body());
        }

        $result = $response->json();

        // Return standardized response
        return [
            'id' => $result['id'] ?? null,
            'status' => $result['status'] ?? 'pending',
            'status_detail' => $result['status_detail'] ?? null,
            'transaction_amount' => $result['transaction_amount'] ?? $paymentData['amount'],
        ];
    }

    /**
     * Simular un pago exitoso en modo development
     */
    private function simulateTestPayment(string $orderId, array $paymentData): array {
        return [
            'id' => uniqid('payment_test_', true),
            'status' => 'approved',
            'status_detail' => 'accredited',
            'transaction_amount' => (float) $paymentData['amount'],
        ];
    }

    /**
     * Obtener estado de un pago
     */
    public function getPayment(int $paymentId): array {
        $response = Http::withToken($this->accessToken)
            ->get("{$this->baseUrl}/v1/payments/{$paymentId}");

        if (!$response->successful()) {
            throw new \Exception('Error getting payment: ' . $response->body());
        }

        return $response->json();
    }
}
