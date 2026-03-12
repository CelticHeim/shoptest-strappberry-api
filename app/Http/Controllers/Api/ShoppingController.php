<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ShoppingController extends Controller {
    public function index(Request $request) {
        $perPage = $request->input('perPage', null);

        $products = Product::filterByName($request->input('search'))
            ->filterByCategories($request->input('categories'))
            ->paginate($perPage);

        return response()->json([
            'message' => 'Shopping store products retrieved successfully',
            'data' => $products,
        ]);
    }
}
