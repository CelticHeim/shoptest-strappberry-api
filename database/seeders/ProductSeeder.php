<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder {
    public function run() {
        // 10 productos específicos de electrónica
        $specificProducts = [
            [
                'name' => 'Laptop ASUS VivoBook 15',
                'description' => 'Laptop profesional con procesador Intel Core i7 y 16GB RAM',
                'price' => 899.99,
            ],
            [
                'name' => 'Samsung Galaxy S23',
                'description' => 'Teléfono inteligente 5G con pantalla AMOLED de 6.1"',
                'price' => 799.99,
            ],
            [
                'name' => 'Sony WH-1000XM5 Audífonos',
                'description' => 'Audífonos inalámbricos con cancelación de ruido activa',
                'price' => 399.99,
            ],
            [
                'name' => 'Apple iPad Pro 12.9"',
                'description' => 'Tablet con chip M2 y pantalla Liquid Retina XDR',
                'price' => 1099.99,
            ],
            [
                'name' => 'Monitor Dell 27" 4K',
                'description' => 'Monitor gaming con resolución 4K y 144Hz',
                'price' => 599.99,
            ],
            [
                'name' => 'Mouse Logitech MX Master 3',
                'description' => 'Mouse inalámbrico ergonómico de precisión',
                'price' => 99.99,
            ],
            [
                'name' => 'Teclado Mecánico Corsair RGB',
                'description' => 'Teclado gaming mecánico con retroiluminación RGB',
                'price' => 199.99,
            ],
            [
                'name' => 'Cable USB-C 2m',
                'description' => 'Cable USB-C de carga rápida y transferencia de datos',
                'price' => 29.99,
            ],
            [
                'name' => 'Power Bank 20000mAh',
                'description' => 'Batería externa portátil con carga rápida',
                'price' => 49.99,
            ],
            [
                'name' => 'Webcam Full HD 1080p',
                'description' => 'Cámara web para streaming y videollamadas',
                'price' => 79.99,
            ],
        ];

        foreach ($specificProducts as $product) {
            Product::create($product);
        }

        // 20 productos aleatorios generados por factory
        Product::factory(20)->create();
    }
}
