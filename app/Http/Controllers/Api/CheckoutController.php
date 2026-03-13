<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Purchase\ConfirmPurchaseRequest;
use App\Http\Requests\Api\Purchase\CreatePreferenceRequest;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class CheckoutController extends Controller {
    public function createPreference(CreatePreferenceRequest $request) {
        // Fetch real product data from database
        $productIds = array_column($request->validated()['items'], 'product_id');
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        // Build Mercado Pago items array with real data from database
        $items = [];
        $totalAmount = 0;

        foreach ($request->validated()['items'] as $item) {
            $product = $products[$item['product_id']];
            $subtotal = $product->price * $item['quantity'];

            $items[] = [
                'id' => (string) $product->id,
                'title' => $product->name,
                'quantity' => $item['quantity'],
                'unit_price' => (float) $product->price,
            ];

            $totalAmount += $subtotal;
        }

        // Create Mercado Pago preference
        $redirectUrl = config('services.mercado_pago.redirect_url');
        $mpResponse = Http::withToken(config('services.mercado_pago.access_token'))
            ->post('https://api.mercadopago.com/checkout/preferences', [
                'items' => $items,
                'back_urls' => [
                    'success' => $redirectUrl,
                    'failure' => str_replace('/checkout-success', '/checkout-failure', $redirectUrl),
                    'pending' => str_replace('/checkout-success', '/checkout-pending', $redirectUrl),
                ],
                'auto_return' => 'approved',
            ]);

        if (!$mpResponse->successful()) {
            return response()->json([
                'message' => 'Error creating payment preference',
            ], 400);
        }

        return response()->json([
            'message' => 'Payment preference created successfully',
            'data' => [
                'preference_id' => $mpResponse->json('id'),
                'init_point' => $mpResponse->json('init_point'),
            ],
        ], 201);
    }

    public function verifyPayment($paymentId) {
        // Query Mercado Pago API for payment status
        $mpResponse = Http::withToken(config('services.mercado_pago.access_token'))
            ->get("https://api.mercadopago.com/v1/payments/{$paymentId}");

        if (!$mpResponse->successful()) {
            return response()->json([
                'message' => 'Failed to verify payment',
            ], 400);
        }

        return response()->json([
            'message' => 'Payment status retrieved',
            'data' => [
                'payment_id' => $mpResponse->json('id'),
                'status' => $mpResponse->json('status'),
                'amount' => (float) $mpResponse->json('transaction_amount'),
                'external_reference' => $mpResponse->json('external_reference'),
            ],
        ]);
    }

    public function confirmPurchase(ConfirmPurchaseRequest $request) {
        // Verify payment is approved in Mercado Pago
        $mpResponse = Http::withToken(config('services.mercado_pago.access_token'))
            ->get("https://api.mercadopago.com/v1/payments/{$request->validated()['payment_id']}");

        if (!$mpResponse->successful() || $mpResponse->json('status') !== 'approved') {
            return response()->json([
                'message' => 'Payment not approved',
            ], 422);
        }

        // Fetch real product data from database
        $productIds = array_column($request->validated()['items'], 'product_id');
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        // Calculate total amount
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

        // Create transaction
        $transaction = Transaction::create([
            'user_id' => Auth::id(),
            'status' => 'paid',
            'total_amount' => $totalAmount,
            'mercado_pago_payment_id' => $request->validated()['payment_id'],
        ]);

        // Link products to transaction
        $transaction->products()->attach($productsToAttach);

        return response()->json([
            'message' => 'Purchase confirmed successfully',
            'data' => [
                'id' => $transaction->id,
                'status' => $transaction->status,
                'total_amount' => $transaction->total_amount,
                'mercado_pago_payment_id' => $transaction->mercado_pago_payment_id,
                'products' => $transaction->products()->get()->map(fn($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'pivot' => $product->pivot,
                ])->toArray(),
            ],
        ], 201);
    }
}
