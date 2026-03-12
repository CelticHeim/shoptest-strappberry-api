<?php

/**
 * Lista de requerimientos
 * // Obtener compras del usuario
 * - El customer puede obtener su historial de compras con paginación
 * - El customer ve los detalles: fecha, status, total, productos comprados
 *
 * // Realizar una compra
 * - El customer puede realizar una compra con un array de productos y cantidades
 * - La validación rechaza compras sin productos
 * - La validación rechaza cantidades inválidas (0, negativas)
 * - Se registra correctamente en transactions con status 'pending'
 * - Se registran los productos en transaction_product con cantidad y precio
 *
 * // Actualizar status de transacción
 * - El status de una transacción se puede cambiar a 'paid'
 * - El status de una transacción se puede cambiar a 'rejected'
 */

use App\Models\Product;
use App\Models\User;

/**
 * Helper function to create a transaction
 */
function createTransaction($userId, $status, $totalAmount) {
    return Transaction::create([
        'user_id' => $userId,
        'status' => $status,
        'total_amount' => $totalAmount,
    ]);
}

describe('User Purchases - Get History', function () {
    it('can retrieve user purchase history with pagination', function () {
        // Arrange
        $user = User::factory()->create();
        $products = Product::factory(3)->create();

        // Create transactions for the user
        $transaction1 = createTransaction($user->id, 'paid', 500.00);
        $transaction2 = createTransaction($user->id, 'pending', 250.50);

        // Attach products to transactions
        $transaction1->products()->attach([
            $products[0]->id => ['quantity' => 2, 'unit_price' => 250.00, 'subtotal' => 500.00],
        ]);

        $transaction2->products()->attach([
            $products[1]->id => ['quantity' => 1, 'unit_price' => 250.50, 'subtotal' => 250.50],
        ]);

        // Act
        $response = $this->actingAs($user)->getJson('/api/purchases');

        // Assert
        $response->assertSuccessful()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'current_page',
                    'data' => [
                        '*' => [
                            'id',
                            'status',
                            'total_amount',
                            'created_at',
                            'products' => [
                                '*' => ['id', 'name', 'pivot' => ['quantity', 'unit_price']],
                            ],
                        ],
                    ],
                    'total',
                ],
            ]);

        expect($response->json('data.total'))->toBe(2);
        expect(count($response->json('data.data')))->toBeLessThanOrEqual(2);
    });
});

describe('User Purchases - Create Purchase', function () {
    it('can create a purchase with products and quantities', function () {
        // Arrange
        $user = User::factory()->create();
        $products = Product::factory(2)->create([
            'price' => 100.00,
        ]);

        $purchaseData = [
            'products' => [
                ['product_id' => $products[0]->id, 'quantity' => 2],
                ['product_id' => $products[1]->id, 'quantity' => 1],
            ],
        ];

        // Act
        $response = $this->actingAs($user)->postJson('/api/purchases', $purchaseData);

        // Assert
        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'status',
                    'total_amount',
                    'user_id',
                    'created_at',
                    'products' => [
                        '*' => ['id', 'name', 'pivot' => ['quantity', 'unit_price', 'subtotal']],
                    ],
                ],
            ]);

        expect($response->json('message'))->toBe('Purchase created successfully');
        expect($response->json('data.status'))->toBe('pending');
        expect($response->json('data.total_amount'))->toBe(300.00);
        expect($response->json('data.user_id'))->toBe($user->id);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'status' => 'pending',
            'total_amount' => 300.00,
        ]);
    });

    it('fails when products array is empty', function () {
        // Arrange
        $user = User::factory()->create();

        $purchaseData = [
            'products' => [],
        ];

        // Act
        $response = $this->actingAs($user)->postJson('/api/purchases', $purchaseData);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors('products');
    });

    it('fails when quantity is invalid', function () {
        // Arrange
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $purchaseData = [
            'products' => [
                ['product_id' => $product->id, 'quantity' => 0],
            ],
        ];

        // Act
        $response = $this->actingAs($user)->postJson('/api/purchases', $purchaseData);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors('products.0.quantity');
    });

    it('registers products in transaction_product with correct data', function () {
        // Arrange
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 150.00]);

        $purchaseData = [
            'products' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ];

        // Act
        $response = $this->actingAs($user)->postJson('/api/purchases', $purchaseData);

        // Assert
        $response->assertCreated();

        $this->assertDatabaseHas('transaction_product', [
            'transaction_id' => $response->json('data.id'),
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 150.00,
            'subtotal' => 450.00,
        ]);
    });
});

describe('User Purchases - Update Transaction Status', function () {
    it('can update transaction status to paid', function () {
        // Arrange
        $user = User::factory()->create();
        $transaction = createTransaction($user->id, 'pending', 100.00);

        $updateData = [
            'status' => 'paid',
        ];

        // Act
        $response = $this->actingAs($user)->putJson("/api/purchases/{$transaction->id}", $updateData);

        // Assert
        $response->assertSuccessful()
            ->assertJsonStructure(['message', 'data' => ['id', 'status']]);

        expect($response->json('data.status'))->toBe('paid');

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'paid',
        ]);
    });

    it('can update transaction status to rejected', function () {
        // Arrange
        $user = User::factory()->create();
        $transaction = createTransaction($user->id, 'pending', 100.00);

        $updateData = [
            'status' => 'rejected',
        ];

        // Act
        $response = $this->actingAs($user)->putJson("/api/purchases/{$transaction->id}", $updateData);

        // Assert
        $response->assertSuccessful()
            ->assertJsonStructure(['message', 'data' => ['id', 'status']]);

        expect($response->json('data.status'))->toBe('rejected');

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'rejected',
        ]);
    });
});
