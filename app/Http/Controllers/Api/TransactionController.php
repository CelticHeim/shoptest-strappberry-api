<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Transaction\GetTransactionStatusRequest;
use App\Models\Transaction;
use App\Services\MercadoPagoOrderService;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller {
    private MercadoPagoOrderService $mpService;

    public function __construct(MercadoPagoOrderService $mpService) {
        $this->mpService = $mpService;
    }

    /**
     * Obtener todas las transacciones del usuario autenticado
     * GET /api/transactions
     */
    public function index() {
        $transactions = Auth::user()->transactions()
            ->with('products')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'message' => 'User transactions retrieved',
            'data' => $transactions,
        ]);
    }

    /**
     * Obtener estado de un pago en Mercado Pago
     * GET /api/transactions/{paymentId}/status
     */
    public function getStatus($paymentId) {
        try {
            $paymentStatus = $this->mpService->getPaymentStatus($paymentId);

            // Actualizar estado en BD si existe
            $transaction = Transaction::where('mercado_pago_payment_id', $paymentId)
                ->where('user_id', Auth::id())
                ->first();

            if ($transaction && $paymentStatus['status'] !== $transaction->status) {
                // Mapear status de MP a nuestro status interno
                $statusMap = [
                    'approved' => 'paid',
                    'pending' => 'pending',
                    'in_process' => 'pending',
                    'rejected' => 'failed',
                    'cancelled' => 'cancelled',
                    'refunded' => 'refunded',
                ];

                $transaction->update([
                    'status' => $statusMap[$paymentStatus['status']] ?? $paymentStatus['status'],
                ]);
            }

            return response()->json([
                'message' => 'Payment status retrieved',
                'data' => $paymentStatus,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving payment status: ' . $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Obtener una transacción específica por ID
     * GET /api/transactions/{id}
     */
    public function show($id) {
        $transaction = Transaction::where('id', $id)
            ->where('user_id', Auth::id())
            ->with('products')
            ->firstOrFail();

        return response()->json([
            'message' => 'Transaction retrieved',
            'data' => $transaction,
        ]);
    }
}
