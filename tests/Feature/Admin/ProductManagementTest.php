<?php

/**
 * Lista de requerimientos
 * // Crear productos
 * - Se puede crear un producto con todos los campos
 * - La validación rechaza productos sin nombre (obligatorio)
 * - La validación rechaza productos sin precio (obligatorio)
 * - Si se envía una imagen, debe guardarse en el storage de Laravel
 *
 * // Obtener productos
 * - Se puede obtener la lista de productos con paginación
 * - Se puede buscar productos por nombre usando query param 'search'
 * - Se puede filtrar productos por categoría usando query param 'categories' (valores separados por comas)
 * - Se puede limitar el número de resultados por página usando query param 'perPage'
 * - Se puede buscar, filtrar y limitar resultados simultáneamente
 *
 * // Actualizar productos
 * - Se puede actualizar un producto existente
 * - La validación rechaza actualizaciones sin nombre o precio
 * - Si se envía una imagen durante la actualización, debe guardarse en storage
 *
 * // Eliminar productos
 * - Se puede eliminar un producto (soft delete)
 * - El producto eliminado no aparece en la lista
 */

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

describe('Product Creation', function () {
    it('can create a product with all fields', function () {
        // Arrange
        Storage::fake('public');

        $productData = [
            'name' => 'Laptop Dell XPS 13',
            'price' => 1299.99,
            'category' => 'Electronics',
            'description' => 'High-performance laptop with latest processor',
            'image' => UploadedFile::fake()->image('product.jpg', 640, 480),
        ];

        // Act
        $response = $this->postJson('/api/products', $productData);

        // Assert
        $response->assertCreated()
            ->assertJsonStructure(['message', 'data' => ['id', 'name', 'price', 'category', 'description', 'image']]);

        expect($response->json('message'))->toBe('Product created successfully');
        $this->assertDatabaseHas('products', [
            'name' => 'Laptop Dell XPS 13',
            'price' => 1299.99,
        ]);
    });

    it('fails when name is missing', function () {
        // Arrange
        Storage::fake('public');

        $productData = [
            'price' => 1299.99,
            'category' => 'Electronics',
            'image' => UploadedFile::fake()->image('product.jpg', 640, 480),
        ];

        // Act
        $response = $this->postJson('/api/products', $productData);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    });

    it('fails when price is missing', function () {
        // Arrange
        Storage::fake('public');

        $productData = [
            'name' => 'Laptop Dell XPS 13',
            'category' => 'Electronics',
            'image' => UploadedFile::fake()->image('product.jpg', 640, 480),
        ];

        // Act
        $response = $this->postJson('/api/products', $productData);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors('price');
    });

    it('saves image to storage when provided', function () {
        // Arrange
        Storage::fake('public');

        $image = UploadedFile::fake()->image('product.jpg', 640, 480);

        $productData = [
            'name' => 'Laptop Dell XPS 13',
            'price' => 1299.99,
            'image' => $image,
        ];

        // Act
        $response = $this->postJson('/api/products', $productData);

        // Assert
        $response->assertCreated();
        Storage::disk('public')->assertExists('products/' . $response->json('data.image'));
    });
});

describe('Listing and Searching Products', function () {
    it('can list products with pagination', function () {
        // Arrange
        Product::factory(15)->create();

        // Act
        $response = $this->getJson('/api/products');

        // Assert
        $response->assertSuccessful()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'current_page',
                    'data' => [
                        '*' => ['id', 'name', 'price', 'category', 'description', 'image'],
                    ],
                    'first_page_url',
                    'from',
                    'last_page',
                    'last_page_url',
                    'links',
                    'next_page_url',
                    'path',
                    'per_page',
                    'prev_page_url',
                    'to',
                    'total',
                ],
            ]);

        expect($response->json('data.total'))->toBe(15);
        expect(count($response->json('data.data')))->toBeLessThanOrEqual(15);
    });

    it('can search products by name', function () {
        // Arrange
        Product::factory()->create(['name' => 'Laptop Dell XPS 13']);
        Product::factory()->create(['name' => 'Laptop HP Pavilion']);
        Product::factory()->create(['name' => 'Desktop PC Gaming']);

        // Act
        $response = $this->getJson('/api/products?search=Laptop');

        // Assert
        $response->assertSuccessful()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'data' => [
                        '*' => ['id', 'name', 'price', 'category', 'description', 'image'],
                    ],
                    'total',
                ],
            ]);

        expect($response->json('data.total'))->toBe(2);
        expect($response->json('data.data'))->toHaveCount(2);
        expect($response->json('data.data.*.name'))->each->toContain('Laptop');
    });

    it('can filter products by category', function () {
        // Arrange
        Product::factory()->create(['category' => 'Laptop']);
        Product::factory()->create(['category' => 'Smartphone']);
        Product::factory()->create(['category' => 'Smartphone']);
        Product::factory()->create(['category' => 'TV']);

        // Act
        $response = $this->getJson('/api/products?categories=Laptop,Smartphone');

        // Assert
        $response->assertSuccessful()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'data' => [
                        '*' => ['id', 'name', 'price', 'category', 'description', 'image'],
                    ],
                    'total',
                ],
            ]);

        expect($response->json('data.total'))->toBe(3);
        expect($response->json('data.data'))->toHaveCount(3);
        $categories = collect($response->json('data.data'))->pluck('category')->unique()->toArray();
        expect(count(array_intersect($categories, ['Laptop', 'Smartphone'])))->toBeGreaterThan(0);
    });

    it('can limit results per page', function () {
        // Arrange
        Product::factory(20)->create();

        // Act
        $response = $this->getJson('/api/products?perPage=5');

        // Assert
        $response->assertSuccessful()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'data' => [
                        '*' => ['id', 'name', 'price', 'category', 'description', 'image'],
                    ],
                    'per_page',
                    'total',
                ],
            ]);

        expect($response->json('data.per_page'))->toBe(5);
        expect(count($response->json('data.data')))->toBeLessThanOrEqual(5);
        expect($response->json('data.total'))->toBe(20);
    });

    it('can search and filter products simultaneously', function () {
        // Arrange
        Product::factory()->create(['name' => 'Laptop Dell XPS 13', 'category' => 'Laptop']);
        Product::factory()->create(['name' => 'Laptop HP Pavilion', 'category' => 'Laptop']);
        Product::factory()->create(['name' => 'iPhone 15', 'category' => 'Smartphone']);
        Product::factory()->create(['name' => 'Samsung Galaxy S24', 'category' => 'Smartphone']);
        Product::factory()->create(['name' => 'LG OLED TV 55', 'category' => 'TV']);

        // Act
        $response = $this->getJson('/api/products?search=Laptop&categories=Laptop,TV&perPage=10');

        // Assert
        $response->assertSuccessful()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'data' => [
                        '*' => ['id', 'name', 'price', 'category', 'description', 'image'],
                    ],
                    'total',
                    'per_page',
                ],
            ]);

        expect($response->json('data.total'))->toBe(2);
        expect($response->json('data.data'))->toHaveCount(2);
        expect($response->json('data.per_page'))->toBe(10);
        expect($response->json('data.data.*.name'))->each->toContain('Laptop');
    });
});

describe('Product Updates', function () {
    it('can update a product with all fields', function () {
        // Arrange
        Storage::fake('public');
        $product = Product::factory()->create();

        $updateData = [
            'name' => 'Updated Laptop',
            'price' => 999.99,
            'category' => 'Computers',
            'description' => 'Updated description',
            'image' => UploadedFile::fake()->image('updated.jpg', 640, 480),
        ];

        // Act
        $response = $this->putJson("/api/products/{$product->id}", $updateData);

        // Assert
        $response->assertSuccessful()
            ->assertJsonStructure(['message', 'data' => ['id', 'name', 'price', 'category', 'description', 'image']]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Laptop',
            'price' => 999.99,
        ]);
    });

    it('fails when name is missing during update', function () {
        // Arrange
        Storage::fake('public');
        $product = Product::factory()->create();

        $updateData = [
            'price' => 999.99,
        ];

        // Act
        $response = $this->putJson("/api/products/{$product->id}", $updateData);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    });

    it('fails when price is missing during update', function () {
        // Arrange
        Storage::fake('public');
        $product = Product::factory()->create();

        $updateData = [
            'name' => 'Updated Product',
        ];

        // Act
        $response = $this->putJson("/api/products/{$product->id}", $updateData);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors('price');
    });

    it('saves new image to storage when provided during update', function () {
        // Arrange
        Storage::fake('public');
        $product = Product::factory()->create();

        $newImage = UploadedFile::fake()->image('new-product.jpg', 640, 480);

        $updateData = [
            'name' => 'Updated Laptop',
            'price' => 999.99,
            'image' => $newImage,
        ];

        // Act
        $response = $this->putJson("/api/products/{$product->id}", $updateData);

        // Assert
        $response->assertSuccessful();
        Storage::disk('public')->assertExists('products/' . $response->json('data.image'));
    });
});

describe('Product Deletion', function () {
    it('can soft delete a product', function () {
        // Arrange
        $product = Product::factory()->create();

        // Act
        $response = $this->deleteJson("/api/products/{$product->id}");

        // Assert
        $response->assertSuccessful();
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    });

    it('deleted products do not appear in list', function () {
        // Arrange
        $product = Product::factory()->create();
        $product->delete();

        // Act
        $response = $this->getJson('/api/products');

        // Assert
        $response->assertSuccessful();
        expect($response->json('data'))->not->toContain(fn($item) => $item['id'] === $product->id);
    });
});
