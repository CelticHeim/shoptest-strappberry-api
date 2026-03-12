<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Purchase\StorePurchaseRequest;
use App\Http\Requests\Api\Purchase\UpdatePurchaseStatusRequest;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PurchaseController extends Controller {
    public function index(Request $request) {
        $user = Auth::user();
        $perPage = $request->query('per_page', 15);

        $purchases = $user->transactions()
            ->with('products')
            ->paginate($perPage);

        return response()->json([
            'message' => 'User purchase history retrieved',
            'data' => $purchases,
        ]);
    }

    public function store(StorePurchaseRequest $request) {
        $user = Auth::user();
        $products = $request->validated()['products'];

        $totalAmount = 0;
        $transactionProducts = [];

        foreach ($products as $item) {
            $product = Product::find($item['product_id']);
            $quantity = $item['quantity'];
            $subtotal = $product->price * $quantity;
            $totalAmount += $subtotal;

            $transactionProducts[] = [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $product->price,
                'subtotal' => $subtotal,
            ];
        }

        $transaction = $user->transactions()->create([
            'status' => 'pending',
            'total_amount' => $totalAmount,
        ]);

        foreach ($transactionProducts as $item) {
            $transaction->products()->attach($item['product_id'], [
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'subtotal' => $item['subtotal'],
            ]);
        }

        $transaction = $transaction->load('products');
        $data = $transaction->toArray();
        $data['total_amount'] = (float)$totalAmount;

        return response()->json([
            'message' => 'Purchase created successfully',
            'data' => $data,
        ], 201);
    }

    public function update(UpdatePurchaseStatusRequest $request, Transaction $transaction) {
        $transaction->update($request->validated());

        return response()->json([
            'message' => 'Purchase status updated successfully',
            'data' => $transaction->load('products'),
        ]);
    }
}

