<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Checkout\CreateCheckoutOrderRequest;
use App\Http\Requests\Api\Checkout\ProcessCheckoutPaymentRequest;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\MercadoPagoOrderService;
use Illuminate\Support\Facades\Auth;

class CheckoutController extends Controller {
    private MercadoPagoOrderService $mpService;

    public function __construct(MercadoPagoOrderService $mpService) {
        $this->mpService = $mpService;
    }

    /**
     * Crear orden de pago en Mercado Pago (Checkout API vía Orders)
     * POST /api/checkout
     */
    public function store(CreateCheckoutOrderRequest $request) {
        // Validar y obtener productos reales
        $productIds = array_column($request->validated()['items'], 'product_id');
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        // Construir items para Mercado Pago
        $items = [];
        $totalAmount = 0;

        foreach ($request->validated()['items'] as $item) {
            $product = $products[$item['product_id']];
            $subtotal = $product->price * $item['quantity'];

            $items[] = [
                'sku_number' => (string) $product->id,
                'category' => 'product',
                'title' => $product->name,
                'description' => $product->description ?? '',
                'unit_price' => (float) $product->price,
                'quantity' => $item['quantity'],
                'unit_measure' => 'unit',
                'total_amount' => (float) $subtotal,
            ];

            $totalAmount += $subtotal;
        }

        // Crear orden en Mercado Pago
        $mpOrder = $this->mpService->createOrder($items, $totalAmount);

        return response()->json([
            'message' => 'Checkout order created',
            'data' => [
                'order_id' => $mpOrder['id'],
                'total_amount' => (float) $mpOrder['total_amount'],
            ],
        ], 201);
    }

    /**
     * Procesar pago de una orden (Checkout API vía Orders)
     * POST /api/checkout/pay
     */
    public function processPayment(ProcessCheckoutPaymentRequest $request) {
        // Validar y obtener productos reales
        $productIds = array_column($request->validated()['items'], 'product_id');
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        // Calcular monto total
        $totalAmount = 0;
        $productsToAttach = [];

        foreach ($request->validated()['items'] as $item) {
            $product = $products[$item['product_id']];
            $subtotal = $product->price * $item['quantity'];
            $totalAmount += $subtotal;

            $productsToAttach[$product->id] = [
                'quantity' => $item['quantity'],
                'unit_price' => (float) $product->price,
                'subtotal' => $subtotal,
            ];
        }

        // Procesar pago en Mercado Pago Payments API
        $paymentData = [
            'amount' => $totalAmount,
            'token' => $request->validated()['token'],
            'payment_method_id' => $request->validated()['payment_method_id'] ?? 'credit_card',
            'installments' => $request->validated()['installments'] ?? 1,
            'payer_email' => Auth::user()->email,
        ];

        $mpPayment = $this->mpService->processOrderPayment(
            $request->validated()['order_id'],
            $paymentData
        );

        // Crear transacción en BD solo si el pago fue aprobado
        // Status puede ser: approved, pending, in_process, rejected, cancelled, refunded
        if (!in_array($mpPayment['status'], ['approved', 'in_process'])) {
            return response()->json([
                'message' => 'Payment failed or pending',
                'status' => $mpPayment['status'],
                'status_detail' => $mpPayment['status_detail'] ?? null,
            ], 400);
        }

        $transaction = Transaction::create([
            'user_id' => Auth::id(),
            'status' => $mpPayment['status'] === 'approved' ? 'paid' : 'pending',
            'total_amount' => $totalAmount,
            'mercado_pago_payment_id' => (string) $mpPayment['id'],
        ]);

        // Vincular productos a la transacción
        $transaction->products()->attach($productsToAttach);

        return response()->json([
            'message' => 'Payment processed successfully',
            'data' => [
                'id' => $transaction->id,
                'status' => $transaction->status,
                'total_amount' => $transaction->total_amount,
                'products' => $transaction->products()->get()->map(fn($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'pivot' => $product->pivot,
                ])->toArray(),
            ],
        ], 201);
    }
}
