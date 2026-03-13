<?php

/**
 * Lista de requerimientos
 * // CRUD de Productos - Solo Admins
 * - El admin puede crear productos
 * - El admin puede ver un producto
 * - El admin puede listar productos
 * - El admin puede actualizar productos
 * - El admin puede eliminar productos
 * - El customer NO puede crear productos
 * - El customer NO puede actualizar productos
 * - El customer NO puede eliminar productos
 * - El no autenticado NO puede acceder a endpoints admin
 *
 * // Shopping - Solo Customers
 * - El customer puede listar productos (GET /api/shopping)
 * - El admin NO puede crear compras si intenta usar checkout
 * - El admin NO puede acceder a /api/purchases
 *
 * // Checkout y Purchases - Solo Customers
 * - El customer puede crear preferencia de pago
 * - El customer puede verificar pago
 * - El customer puede confirmar compra
 * - El customer puede ver su historial de compras
 * - El admin NO puede acceder a /api/checkout
 * - El admin NO puede acceder a /api/purchases
 * - El no autenticado NO puede acceder a rutas protegidas
 */

use App\Models\Product;
use App\Models\User;

describe('Product CRUD - Admin Only Access', function () {
    it('admin can create a product', function () {
        // Arrange
        $admin = User::factory()->admin()->create();
        $productData = [
            'name' => 'Test Product',
            'price' => 99.99,
            'category' => 'electronics',
            'description' => 'Test description',
        ];

        // Act
        $response = $this->actingAs($admin)->postJson('/api/products', $productData);

        // Assert
        $response->assertCreated()
            ->assertJsonStructure(['message', 'data' => ['id', 'name', 'price']]);
        expect($response->json('data.name'))->toBe('Test Product');
    });

    it('customer cannot create a product', function () {
        // Arrange
        $customer = User::factory()->customer()->create();
        $productData = [
            'name' => 'Test Product',
            'price' => 99.99,
            'category' => 'electronics',
        ];

        // Act
        $response = $this->actingAs($customer)->postJson('/api/products', $productData);

        // Assert
        $response->assertForbidden();
    });

    it('admin can update a product', function () {
        // Arrange
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['name' => 'Old Name']);
        $updateData = ['name' => 'Updated Name', 'price' => 150.00];

        // Act
        $response = $this->actingAs($admin)->putJson("/api/products/{$product->id}", $updateData);

        // Assert
        $response->assertSuccessful()
            ->assertJsonPath('data.name', 'Updated Name');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Updated Name']);
    });

    it('customer cannot update a product', function () {
        // Arrange
        $customer = User::factory()->customer()->create();
        $product = Product::factory()->create();
        $updateData = ['name' => 'Hacked Name'];

        // Act
        $response = $this->actingAs($customer)->putJson("/api/products/{$product->id}", $updateData);

        // Assert
        $response->assertForbidden();
    });

    it('admin can delete a product', function () {
        // Arrange
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        // Act
        $response = $this->actingAs($admin)->deleteJson("/api/products/{$product->id}");

        // Assert
        $response->assertSuccessful();
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    });

    it('customer cannot delete a product', function () {
        // Arrange
        $customer = User::factory()->customer()->create();
        $product = Product::factory()->create();

        // Act
        $response = $this->actingAs($customer)->deleteJson("/api/products/{$product->id}");

        // Assert
        $response->assertForbidden();
    });

    it('unauthenticated user cannot create product', function () {
        // Act
        $response = $this->postJson('/api/products', ['name' => 'Test']);

        // Assert
        $response->assertUnauthorized();
    });

    it('unauthenticated user cannot update product', function () {
        // Arrange
        $product = Product::factory()->create();

        // Act
        $response = $this->putJson("/api/products/{$product->id}", ['name' => 'Hacked']);

        // Assert
        $response->assertUnauthorized();
    });

    it('unauthenticated user cannot delete product', function () {
        // Arrange
        $product = Product::factory()->create();

        // Act
        $response = $this->deleteJson("/api/products/{$product->id}");

        // Assert
        $response->assertUnauthorized();
    });
});

describe('Shopping Access Control', function () {
    it('customer can access shopping endpoint', function () {
        // Arrange
        $customer = User::factory()->customer()->create();
        Product::factory()->create();

        // Act
        $response = $this->actingAs($customer)->getJson('/api/shopping');

        // Assert
        $response->assertSuccessful();
    });

    it('unauthenticated user can access shopping endpoint', function () {
        // Arrange
        Product::factory()->create();

        // Act
        $response = $this->getJson('/api/shopping');

        // Assert
        $response->assertSuccessful();
    });
});

describe('Checkout Access Control', function () {
    it('customer can access checkout endpoints', function () {
        // Arrange
        $customer = User::factory()->customer()->create();
        $product = Product::factory()->create(['price' => 100.00]);

        $checkoutData = [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ];

        // Act
        $response = $this->actingAs($customer)->postJson('/api/checkout', $checkoutData);

        // Assert
        expect($response->status())->toBeIn([200, 201, 400]);
    });

    it('admin cannot access checkout', function () {
        // Arrange
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['price' => 100.00]);

        $checkoutData = [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ];

        // Act
        $response = $this->actingAs($admin)->postJson('/api/checkout', $checkoutData);

        // Assert
        $response->assertForbidden();
    });

    it('unauthenticated user cannot access checkout', function () {
        // Act
        $response = $this->postJson('/api/checkout', []);

        // Assert
        $response->assertUnauthorized();
    });
});

describe('Purchases Access Control', function () {
    it('customer can access purchases endpoint', function () {
        // Arrange
        $customer = User::factory()->customer()->create();

        // Act
        $response = $this->actingAs($customer)->getJson('/api/purchases');

        // Assert
        $response->assertSuccessful();
    });

    it('admin cannot access purchases endpoint', function () {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->getJson('/api/purchases');

        // Assert
        $response->assertForbidden();
    });

    it('unauthenticated user cannot access purchases endpoint', function () {
        // Act
        $response = $this->getJson('/api/purchases');

        // Assert
        $response->assertUnauthorized();
    });
});
