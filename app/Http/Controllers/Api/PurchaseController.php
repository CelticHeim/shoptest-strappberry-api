<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Purchase\CreatePreferenceRequest;
use App\Http\Requests\Api\Purchase\ConfirmPurchaseRequest;
use App\Http\Requests\Api\Purchase\UpdatePurchaseStatusRequest;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

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

    public function update(UpdatePurchaseStatusRequest $request, Transaction $transaction) {
        $transaction->update($request->validated());

        return response()->json([
            'message' => 'Purchase status updated successfully',
            'data' => $transaction->load('products'),
        ]);
    }
}

