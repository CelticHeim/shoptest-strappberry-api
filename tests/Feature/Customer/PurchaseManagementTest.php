<?php

/**
 * Lista de requerimientos
 * // Obtener compras del usuario
 * - El customer puede obtener su historial de compras
 * - El customer ve los detalles: id, status, total_amount, created_at
 * - El customer ve los productos con pivot (quantity, unit_price)
 * - Respeta paginación (per_page)
 *
 * // Actualizar status de transacción
 * - El status de una transacción se puede cambiar a 'paid'
 * - El status de una transacción se puede cambiar a 'rejected'
 */

use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;

describe('Purchase Management - List Purchases', function () {
    it('can retrieve user purchase history', function () {
        // Arrange
        $user = User::factory()->create();
        $products = Product::factory(2)->create();

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'status' => 'paid',
            'total_amount' => 300.00,
        ]);

        $transaction->products()->attach([
            $products[0]->id => ['quantity' => 2, 'unit_price' => 150.00, 'subtotal' => 300.00],
        ]);

        // Act
        $response = $this->actingAs($user)->getJson('/api/purchases');

        // Assert
        $response->assertSuccessful()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'current_page',
                    'data' => ['*' => ['id', 'status', 'total_amount', 'created_at', 'products']],
                    'total',
                ],
            ]);

        expect($response->json('data.total'))->toBe(1);
    });

    it('respects pagination perPage parameter', function () {
        // Arrange
        $user = User::factory()->create();

        for ($i = 0; $i < 20; $i++) {
            Transaction::create([
                'user_id' => $user->id,
                'status' => 'paid',
                'total_amount' => 100.00,
            ]);
        }

        // Act
        $response = $this->actingAs($user)->getJson('/api/purchases?per_page=5');

        // Assert
        $data = $response->json('data.data');
        expect(is_array($data))->toBeTrue();
        if (is_array($data)) {
            expect(count($data))->toBeLessThanOrEqual(5);
        }
    });

    it('shows products with quantities in each purchase', function () {
        // Arrange
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 50.00]);

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'status' => 'paid',
            'total_amount' => 150.00,
        ]);

        $transaction->products()->attach([
            $product->id => ['quantity' => 3, 'unit_price' => 50.00, 'subtotal' => 150.00],
        ]);

        // Act
        $response = $this->actingAs($user)->getJson('/api/purchases');

        // Assert
        $product_data = $response->json('data.data.0.products.0');
        expect($product_data['pivot']['quantity'])->toBe(3);
        expect((float) $product_data['pivot']['unit_price'])->toBe(50.00);
    });
});

describe('Purchase Management - Update Transaction Status', function () {
    it('can update transaction status to paid', function () {
        // Arrange
        $user = User::factory()->create();
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'status' => 'pending',
            'total_amount' => 100.00,
        ]);

        // Act
        $response = $this->actingAs($user)->putJson("/api/purchases/{$transaction->id}", [
            'status' => 'paid',
        ]);

        // Assert
        $response->assertSuccessful()
            ->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'paid',
        ]);
    });

    it('can update transaction status to rejected', function () {
        // Arrange
        $user = User::factory()->create();
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'status' => 'pending',
            'total_amount' => 100.00,
        ]);

        // Act
        $response = $this->actingAs($user)->putJson("/api/purchases/{$transaction->id}", [
            'status' => 'rejected',
        ]);

        // Assert
        $response->assertSuccessful()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'rejected',
        ]);
    });
});
