<?php

/**
 * Lista de requerimientos
 * // Crear orden de pago
 * - El customer puede crear una orden con su carrito
 * - Se envían los productos y cantidades a MP
 * - Retorna order_id para procesar el pago
 * - Valida que items no esté vacío
 *
 * // Procesar pago de orden
 * - El customer puede procesar el pago de una orden
 * - Si el pago está aprobado, crea la transacción en BD
 * - El status de la transacción es 'paid'
 * - Se registran los productos en transaction_product
 */

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Http;

describe('Checkout - Create Order', function () {
    it('can create checkout order with cart items', function () {
        // Arrange
        $user = User::factory()->create();
        $products = Product::factory(2)->create(['price' => 100.00]);

        // Act
        $response = $this->actingAs($user)->postJson('/api/checkout', [
            'items' => [
                ['product_id' => $products[0]->id, 'quantity' => 2],
                ['product_id' => $products[1]->id, 'quantity' => 1],
            ],
        ]);

        // Assert
        $response->assertCreated()
            ->assertJsonStructure(['message', 'data' => ['order_id', 'total_amount']]);
        
        expect($response->json('data.order_id'))->toMatch('/^order_/');
        expect((float) $response->json('data.total_amount'))->toBe(300.0);
    });

    it('validates items field is required', function () {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->postJson('/api/checkout', []);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    });
});

describe('Checkout - Process Payment', function () {
    it('can process payment and create transaction', function () {
        // Arrange
        Http::fake([
            'https://api.mercadopago.com/v1/payments' => Http::response([
                'id' => 123456789,
                'status' => 'approved',
                'transaction_amount' => 300.00,
            ], 201),
        ]);

        $user = User::factory()->create();
        $products = Product::factory(2)->create(['price' => 100.00]);

        // Act: First create order
        $orderResponse = $this->actingAs($user)->postJson('/api/checkout', [
            'items' => [
                ['product_id' => $products[0]->id, 'quantity' => 2],
                ['product_id' => $products[1]->id, 'quantity' => 1],
            ],
        ]);
        $orderId = $orderResponse->json('data.order_id');

        // Act: Then process payment
        $response = $this->actingAs($user)->postJson('/api/checkout/pay', [
            'order_id' => $orderId,
            'token' => 'tok_visa',
            'installments' => 1,
            'items' => [
                ['product_id' => $products[0]->id, 'quantity' => 2],
                ['product_id' => $products[1]->id, 'quantity' => 1],
            ],
        ]);

        // Assert
        $response->assertCreated()
            ->assertJsonStructure(['message', 'data' => ['id', 'status', 'total_amount', 'products']])
            ->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'status' => 'paid',
            'total_amount' => 300.00,
        ]);
    });

    it('registers products in transaction with correct quantities', function () {
        // Arrange
        Http::fake(['https://api.mercadopago.com/v1/payments' => Http::response([
            'id' => 987654321,
            'status' => 'approved',
            'transaction_amount' => 300.00,
        ], 201)]);

        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100.00]);

        // Act: First create order
        $orderResponse = $this->actingAs($user)->postJson('/api/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ]);
        $orderId = $orderResponse->json('data.order_id');

        // Act: Then process payment
        $response = $this->actingAs($user)->postJson('/api/checkout/pay', [
            'order_id' => $orderId,
            'token' => 'tok_visa',
            'installments' => 1,
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ]);

        // Assert
        $transaction_id = $response->json('data.id');
        $this->assertDatabaseHas('transaction_product', [
            'transaction_id' => $transaction_id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);
    });

    it('validates required fields in payment request', function () {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->postJson('/api/checkout/pay', []);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['order_id', 'token', 'items']);
    });
});
