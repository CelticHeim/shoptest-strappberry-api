<?php

/**
 * Lista de requerimientos
 * // Obtener productos en la tienda
 * - Se puede obtener la lista de productos con paginación
 * - Se puede buscar productos por nombre usando query param 'search'
 * - Se puede filtrar productos por categoría usando query param 'categories' (valores separados por comas)
 * - Se puede limitar el número de resultados por página usando query param 'perPage'
 * - Se puede buscar, filtrar y limitar resultados simultáneamente
 */

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

describe('Shopping Store - List Products', function () {
    it('can list all products with pagination', function () {
        // Arrange
        Product::factory(15)->create();

        // Act
        $response = $this->getJson('/api/shopping');

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

    it('can search products by name in shopping store', function () {
        // Arrange
        Product::factory()->create(['name' => 'Laptop Dell XPS 13']);
        Product::factory()->create(['name' => 'Laptop HP Pavilion']);
        Product::factory()->create(['name' => 'Desktop PC Gaming']);

        // Act
        $response = $this->getJson('/api/shopping?search=Laptop');

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

    it('can filter products by category in shopping store', function () {
        // Arrange
        Product::factory()->create(['category' => 'Laptop']);
        Product::factory()->create(['category' => 'Smartphone']);
        Product::factory()->create(['category' => 'Smartphone']);
        Product::factory()->create(['category' => 'TV']);

        // Act
        $response = $this->getJson('/api/shopping?categories=Laptop,Smartphone');

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

    it('can limit results per page in shopping store', function () {
        // Arrange
        Product::factory(20)->create();

        // Act
        $response = $this->getJson('/api/shopping?perPage=5');

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

    it('can search, filter and limit products simultaneously in shopping store', function () {
        // Arrange
        Product::factory()->create(['name' => 'Laptop Dell XPS 13', 'category' => 'Laptop']);
        Product::factory()->create(['name' => 'Laptop HP Pavilion', 'category' => 'Laptop']);
        Product::factory()->create(['name' => 'iPhone 15', 'category' => 'Smartphone']);
        Product::factory()->create(['name' => 'Samsung Galaxy S24', 'category' => 'Smartphone']);
        Product::factory()->create(['name' => 'LG OLED TV 55', 'category' => 'TV']);

        // Act
        $response = $this->getJson('/api/shopping?search=Laptop&categories=Laptop,TV&perPage=10');

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

    it('saves image with date format (MM_DD_YYYY) and returns full URL in shopping', function () {
        // Arrange
        Storage::fake('public');
        $image = UploadedFile::fake()->image('product.png', 640, 480);

        $productData = [
            'name' => 'Product with Image',
            'price' => 499.99,
            'category' => 'Electronics',
            'image' => $image,
        ];

        // Act
        $createResponse = $this->postJson('/api/products', $productData);
        $productId = $createResponse->json('data.id');
        $imageUrl = $createResponse->json('data.image');
        $imageName = basename($imageUrl);
        $shoppingResponse = $this->getJson('/api/shopping');

        // Assert
        $createResponse->assertCreated();
        expect($imageName)->toMatch('/\d{2}_\d{2}_\d{4}\.\w+/'); // MM_DD_YYYY.ext format
        
        Storage::disk('public')->assertExists('products/' . $imageName);

        $productFromList = collect($shoppingResponse->json('data.data'))
            ->firstWhere('id', $productId);
        
        expect($productFromList['image'])->toContain(config('app.url') . '/storage/products/')
            ->and($productFromList['image'])->toContain($imageName);
    });

    it('returns image with full URL in shopping endpoint', function () {
        // Arrange
        Storage::fake('public');
        $image = UploadedFile::fake()->image('product.jpg', 640, 480);

        $productData = [
            'name' => 'Phone with Image',
            'price' => 799.99,
            'category' => 'Smartphones',
            'image' => $image,
        ];

        // Act
        $createResponse = $this->postJson('/api/products', $productData);
        $productId = $createResponse->json('data.id');
        $imageName = $createResponse->json('data.image');
        $shoppingResponse = $this->getJson('/api/shopping');

        // Assert
        $shoppingResponse->assertSuccessful();
        $productFromList = collect($shoppingResponse->json('data.data'))
            ->firstWhere('id', $productId);
        
        expect($productFromList['image'])->toContain(config('app.url') . '/storage/products/')
            ->and($productFromList['image'])->toContain($imageName);
    });
});
