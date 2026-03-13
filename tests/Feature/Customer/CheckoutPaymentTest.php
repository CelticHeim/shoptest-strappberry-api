<?php

/**
 * Lista de requerimientos
 * // Crear preferencia de pago en Mercado Pago
 * - El customer puede crear una preferencia con su carrito
 * - Se envían los productos, cantidades y precios a MP
 * - La respuesta incluye el init_point (URL de pago)
 * - Si hay error en MP, se retorna error 400
 * - Valida que items y total_amount no estén vacíos
 *
 * // Verificar pago completado
 * - El customer puede verificar si su pago fue aprobado
 * - Se consulta a MP por el payment_id
 * - Si el pago está aprobado, retorna approved + amount
 * - Si el pago está pendiente, retorna pending
 * - Si el pago fue rechazado, retorna rejected
 *
 * // Confirmar compra después de pago aprobado
 * - Una vez confirmado el pago, se crea la transacción en BD
 * - El status de la transacción es 'paid'
 * - Se registran los productos en transaction_product
 * - Se guarda el mercado_pago_payment_id
 * - Si el pago no está aprobado, rechaza con error 400
 */

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

describe('Checkout - Create Payment Preference', function () {
    it('can create a mercado pago preference with cart items', function () {
        // Arrange
        Http::fake([
            'https://api.mercadopago.com/checkout/preferences' => Http::response([
                'id' => 'pref_123456',
                'init_point' => 'https://www.mercadopago.com.ar/checkout/v1/p?pref_id=pref_123456',
                'status' => 'pending',
            ], 201),
        ]);

        $user = User::factory()->create();
        $products = Product::factory(2)->create([
            'price' => 100.00,
        ]);

        $checkoutData = [
            'items' => [
                [
                    'product_id' => $products[0]->id,
                    'quantity' => 2,
                ],
                [
                    'product_id' => $products[1]->id,
                    'quantity' => 1,
                ],
            ],
        ];

        // Act
        $response = $this->actingAs($user)->postJson('/api/checkout', $checkoutData);

        // Assert
        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'preference_id',
                    'init_point',
                ],
            ])
            ->assertJsonPath('data.preference_id', 'pref_123456')
            ->assertJsonPath('data.init_point', 'https://www.mercadopago.com.ar/checkout/v1/p?pref_id=pref_123456');

        // Verify that server sent correct data to MP (with product data from BD)
        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.mercadopago.com/checkout/preferences' &&
                   $request->method() === 'POST';
        });
    });

    it('returns error if mercado pago request fails', function () {
        // Arrange
        Http::fake([
            'https://api.mercadopago.com/checkout/preferences' => Http::response(
                ['message' => 'Invalid credentials'],
                401
            ),
        ]);

        $user = User::factory()->create();
        $products = Product::factory(1)->create(['price' => 100.00]);

        $checkoutData = [
            'items' => [
                [
                    'product_id' => $products[0]->id,
                    'quantity' => 1,
                ],
            ],
        ];

        // Act
        $response = $this->actingAs($user)->postJson('/api/checkout', $checkoutData);

        // Assert
        $response->assertClientError()
            ->assertJsonPath('message', 'Error creating payment preference');
    });

    it('validates required fields in checkout request', function () {
        // Arrange
        $user = User::factory()->create();

        // Act - Missing items
        $response = $this->actingAs($user)->postJson('/api/checkout', []);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    });
});

describe('Checkout - Verify Payment Status', function () {
    it('can verify an approved payment from mercado pago', function () {
        // Arrange
        Http::fake([
            'https://api.mercadopago.com/v1/payments/988989*' => Http::response([
                'id' => 988989,
                'status' => 'approved',
                'transaction_amount' => 300.0,
                'payer' => ['email' => 'test@example.com'],
            ], 200),
        ]);

        $user = User::factory()->create();
        $paymentId = 988989;

        // Act
        $response = $this->actingAs($user)->getJson("/api/checkout/verify-payment/{$paymentId}");

        // Assert
        $response->assertSuccessful()
            ->assertJsonPath('data.status', 'approved');
        expect((float) $response->json('data.amount'))->toBe(300.00);
    });

    it('can verify a pending payment from mercado pago', function () {
        // Arrange
        Http::fake([
            'https://api.mercadopago.com/v1/payments/988990*' => Http::response([
                'id' => 988990,
                'status' => 'pending',
                'transaction_amount' => 150.00,
            ], 200),
        ]);

        $user = User::factory()->create();
        $paymentId = 988990;

        // Act
        $response = $this->actingAs($user)->getJson("/api/checkout/verify-payment/{$paymentId}");

        // Assert
        $response->assertSuccessful()
            ->assertJsonPath('data.status', 'pending');
    });

    it('can verify a rejected payment from mercado pago', function () {
        // Arrange
        Http::fake([
            'https://api.mercadopago.com/v1/payments/988991*' => Http::response([
                'id' => 988991,
                'status' => 'rejected',
                'status_detail' => 'insufficient_funds',
            ], 200),
        ]);

        $user = User::factory()->create();
        $paymentId = 988991;

        // Act
        $response = $this->actingAs($user)->getJson("/api/checkout/verify-payment/{$paymentId}");

        // Assert
        $response->assertSuccessful()
            ->assertJsonPath('data.status', 'rejected');
    });
});

describe('Checkout - Confirm Purchase After Payment', function () {
    it('can confirm a purchase and create transaction after approved payment', function () {
        // Arrange
        $user = User::factory()->create();
        $products = Product::factory(2)->create(['price' => 100.00]);

        $confirmData = [
            'payment_id' => 988989,
            'preference_id' => 'pref_123456',
            'items' => [
                [
                    'product_id' => $products[0]->id,
                    'quantity' => 2,
                ],
                [
                    'product_id' => $products[1]->id,
                    'quantity' => 1,
                ],
            ],
        ];

        Http::fake([
            'https://api.mercadopago.com/v1/payments/988989*' => Http::response([
                'id' => 988989,
                'status' => 'approved',
                'transaction_amount' => 300.00,
            ], 200),
        ]);

        // Act
        $response = $this->actingAs($user)->postJson('/api/checkout/confirm', $confirmData);

        // Assert
        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'status',
                    'total_amount',
                    'mercado_pago_payment_id',
                    'products',
                ],
            ])
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.mercado_pago_payment_id', 988989);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'status' => 'paid',
            'total_amount' => 300.00,
            'mercado_pago_payment_id' => 988989,
        ]);
    });

    it('rejects confirmation if payment is not approved', function () {
        // Arrange
        Http::fake([
            'https://api.mercadopago.com/v1/payments/988990*' => Http::response([
                'id' => 988990,
                'status' => 'pending',
                'transaction_amount' => 150.00,
            ], 200),
        ]);

        $user = User::factory()->create();
        $products = Product::factory(1)->create(['price' => 150.00]);

        $confirmData = [
            'payment_id' => 988990,
            'preference_id' => 'pref_123456',
            'items' => [
                [
                    'product_id' => $products[0]->id,
                    'quantity' => 1,
                ],
            ],
        ];

        // Act
        $response = $this->actingAs($user)->postJson('/api/checkout/confirm', $confirmData);

        // Assert
        $response->assertClientError()
            ->assertJsonPath('message', 'Payment not approved');
    });

    it('validates required fields in confirm request', function () {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->postJson('/api/checkout/confirm', []);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_id', 'items']);
    });

    it('registers products in transaction_product with correct data', function () {
        // Arrange
        Http::fake([
            'https://api.mercadopago.com/v1/payments/988989*' => Http::response([
                'id' => 988989,
                'status' => 'approved',
                'transaction_amount' => 450.00,
            ], 200),
        ]);

        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 150.00]);

        $confirmData = [
            'payment_id' => 988989,
            'preference_id' => 'pref_123456',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3,
                ],
            ],
        ];

        // Act
        $response = $this->actingAs($user)->postJson('/api/checkout/confirm', $confirmData);

        // Assert
        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'status',
                    'total_amount',
                    'mercado_pago_payment_id',
                    'products',
                ],
            ]);

        $this->assertDatabaseHas('transaction_product', [
            'transaction_id' => $response->json('data.id'),
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 150.00,
            'subtotal' => 450.00,
        ]);
    });
});
