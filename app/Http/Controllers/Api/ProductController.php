<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Product\StoreProductRequest;
use App\Http\Requests\Api\Product\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller {
    public function index(Request $request) {
        $perPage = $request->input('perPage', null);

        $products = Product::filterByName($request->input('search'))
            ->filterByCategories($request->input('categories'))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'message' => 'Products retrieved successfully',
            'data' => $products,
        ]);
    }

    public function show(Product $product) {
        return response()->json([
            'message' => 'Product retrieved successfully',
            'data' => $product,
        ]);
    }

    public function store(StoreProductRequest $request) {
        $data = $request->validated();

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $ext = $image->getClientOriginalExtension();
            $fileName = now()->format('m_d_Y') . '.' . $ext;
            $image->storeAs('products', $fileName, 'public');
            $data['image'] = $fileName;
        }

        $product = Product::create($data);

        return response()->json([
            'message' => 'Product created successfully',
            'data' => $product,
        ], 201);
    }

    public function update(UpdateProductRequest $request, Product $product) {
        $data = $request->validated();

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            $oldImageName = $product->getRawOriginal('image');
            if ($oldImageName && Storage::disk('public')->exists('products/' . $oldImageName)) {
                Storage::disk('public')->delete('products/' . $oldImageName);
            }

            $image = $request->file('image');
            $ext = $image->getClientOriginalExtension();
            $fileName = now()->format('m_d_Y') . '.' . $ext;
            $image->storeAs('products', $fileName, 'public');
            $data['image'] = $fileName;
        }

        $product->update($data);

        return response()->json([
            'message' => 'Product updated successfully',
            'data' => $product,
        ]);
    }

    public function destroy(Product $product) {
        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }
}
