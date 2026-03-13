<?php

/**
 * Lista de requerimientos
 * // Obtener compras del usuario
 * - El customer puede obtener su historial de compras con paginación
 * - El customer ve los detalles: id, status, total_amount, created_at
 * - El customer ve los productos: id, name, pivot (quantity, unit_price)
 * - Las compras aparecen con status: "pending", "paid" o "rejected"
 * - Las compras pagadas vía checkout aparecen con status "paid"
 * - Página respeta paginación (per_page)
 *
 * // Actualizar status de transacción
 * - El status de una transacción se puede cambiar a 'paid'
 * - El status de una transacción se puede cambiar a 'rejected'
 * - Se persiste correctamente en BD
 */

use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;

/**
 * Helper function to create a transaction
 */
function createTransaction($userId, $status, $totalAmount, $paymentId = null) {
    return Transaction::create([
        'user_id' => $userId,
        'status' => $status,
        'total_amount' => $totalAmount,
        'mercado_pago_payment_id' => $paymentId,
    ]);
}

describe('Purchase Management - Get Purchase History', function () {
    it('can retrieve user purchase history with pagination', function () {
        // Arrange
        $user = User::factory()->create();
        $products = Product::factory(3)->create();

        // Create transactions for the user
        $transaction1 = createTransaction($user->id, 'paid', 500.00, 988989);
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

    it('shows paid transactions from checkout confirmation', function () {
        // Arrange
        $user = User::factory()->create();
        $product = Product::factory()->create();

        // Create a paid transaction (simulating one from checkout)
        $transaction = createTransaction($user->id, 'paid', 300.00, 988989);
        $transaction->products()->attach([
            $product->id => ['quantity' => 2, 'unit_price' => 150.00, 'subtotal' => 300.00],
        ]);

        // Act
        $response = $this->actingAs($user)->getJson('/api/purchases');

        // Assert
        $response->assertSuccessful();
        $purchases = $response->json('data.data');
        
        expect(count($purchases))->toBe(1);
        expect($purchases[0]['status'])->toBe('paid');
        expect($purchases[0]['mercado_pago_payment_id'])->toBe(988989);
    });

    it('filters purchases by status when listing', function () {
        // Arrange
        $user = User::factory()->create();
        $products = Product::factory(2)->create();

        createTransaction($user->id, 'paid', 500.00, 988989)->products()->attach(
            $products[0]->id => ['quantity' => 1, 'unit_price' => 500.00, 'subtotal' => 500.00]
        );

        createTransaction($user->id, 'pending', 250.00)->products()->attach(
            $products[1]->id => ['quantity' => 1, 'unit_price' => 250.00, 'subtotal' => 250.00]
        );

        // Act
        $response = $this->actingAs($user)->getJson('/api/purchases');

        // Assert
        $purchases = $response->json('data.data');
        expect(count($purchases))->toBe(2);
        
        $statuses = array_column($purchases, 'status');
        expect(in_array('paid', $statuses))->toBeTrue();
        expect(in_array('pending', $statuses))->toBeTrue();
    });

    it('respects pagination per_page parameter', function () {
        // Arrange
        $user = User::factory()->create();
        Product::factory(20)->create();

        for ($i = 0; $i < 15; $i++) {
            createTransaction($user->id, 'pending', 100.00);
        }

        // Act
        $response = $this->actingAs($user)->getJson('/api/purchases?per_page=5');

        // Assert
        expect(count($response->json('data.data')))->toBe(5);
    });
});

describe('Purchase Management - Update Transaction Status', function () {
    it('can update transaction status from pending to paid', function () {
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

    it('can update transaction status from pending to rejected', function () {
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
