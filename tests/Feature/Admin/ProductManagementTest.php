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
 * // Ver detalles de un producto
 * - Se puede obtener un producto específico por id
 * - Retorna 404 cuando el producto no existe
 *
 * // Actualizar productos
 * - Se puede actualizar un producto existente
 * - La validación rechaza actualizaciones sin nombre o precio
 * - Si se envía una imagen durante la actualización, debe guardarse en storage
 *
 * // Eliminar productos
 * - Se puede eliminar un producto (soft delete)
 * - El producto eliminado no aparece en la lista
 *
 * // Almacenamiento y acceso de imágenes
 * - Las imágenes se guardan con formato de fecha MM_DD_YYYY.extension
 * - El endpoint index() devuelve la URL completa de la imagen
 * - El endpoint show() devuelve la URL completa de la imagen
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
        $imageUrl = $response->json('data.image');
        $imageName = basename($imageUrl);
        Storage::disk('public')->assertExists('products/' . $imageName);
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

describe('Viewing Product Details', function () {
    it('can retrieve a single product by id', function () {
        // Arrange
        $product = Product::factory()->create([
            'name' => 'Laptop Dell XPS 13',
            'price' => 1299.99,
            'category' => 'Electronics',
            'description' => 'High-performance laptop',
            'image' => 'product.jpg',
        ]);

        // Act
        $response = $this->getJson("/api/products/{$product->id}");

        // Assert
        $response->assertSuccessful()
            ->assertJsonStructure(['message', 'data' => ['id', 'name', 'price', 'category', 'description', 'image']]);

        expect($response->json('message'))->toBe('Product retrieved successfully');
        expect($response->json('data.id'))->toBe($product->id);
        expect($response->json('data.name'))->toBe('Laptop Dell XPS 13');
        expect((float) $response->json('data.price'))->toBe(1299.99);
        expect($response->json('data.category'))->toBe('Electronics');
        expect($response->json('data.description'))->toBe('High-performance laptop');
    });

    it('returns 404 when product does not exist', function () {
        // Act
        $response = $this->getJson("/api/products/9999");

        // Assert
        $response->assertNotFound();
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
        $imageUrl = $response->json('data.image');
        $imageName = basename($imageUrl);
        Storage::disk('public')->assertExists('products/' . $imageName);
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

describe('Image Storage and Accessibility', function () {
    it('saves image with date format (MM_DD_YYYY) and returns full URL in index', function () {
        // Arrange
        Storage::fake('public');
        $image = UploadedFile::fake()->image('product.png', 640, 480);

        $productData = [
            'name' => 'Laptop with Image',
            'price' => 1299.99,
            'category' => 'Electronics',
            'image' => $image,
        ];

        // Act
        $response = $this->postJson('/api/products', $productData);
        $productId = $response->json('data.id');
        $imageUrl = $response->json('data.image');
        $imageName = basename($imageUrl);
        $indexResponse = $this->getJson('/api/products');

        // Assert
        $response->assertCreated();
        expect($imageName)->toMatch('/\d{2}_\d{2}_\d{4}\.\w+/'); // MM_DD_YYYY.ext format
        
        Storage::disk('public')->assertExists('products/' . $imageName);

        $productFromList = collect($indexResponse->json('data.data'))
            ->firstWhere('id', $productId);
        
        expect($productFromList['image'])->toContain(config('app.url') . '/storage/products/')
            ->and($productFromList['image'])->toContain($imageName);
    });

    it('returns image with full URL in show endpoint', function () {
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

        $showResponse = $this->getJson("/api/products/{$productId}");

        // Assert
        $showResponse->assertSuccessful();
        expect($showResponse->json('data.image'))->toContain(config('app.url') . '/storage/products/')
            ->and($showResponse->json('data.image'))->toContain($imageName);
    });
});
