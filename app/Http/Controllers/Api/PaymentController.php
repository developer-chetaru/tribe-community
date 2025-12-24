<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/submit-payment",
     *     tags={"Payments"},
     *     summary="Submit payment for an invoice",
     *     description="Submit a payment for an invoice. Directors can submit payments for invoices from their organisation.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"invoice_id", "payment_method", "amount"},
     *                 @OA\Property(property="invoice_id", type="integer", example=1, description="ID of the invoice to pay"),
     *                 @OA\Property(property="payment_method", type="string", enum={"bank_transfer", "credit_card", "paypal", "other"}, example="bank_transfer", description="Payment method used"),
     *                 @OA\Property(property="amount", type="number", format="float", example=90.00, description="Payment amount"),
     *                 @OA\Property(property="transaction_id", type="string", example="TXN-123456", description="Transaction ID from payment provider", nullable=true),
     *                 @OA\Property(property="payment_notes", type="string", example="Payment made via bank transfer", description="Additional payment notes", nullable=true),
     *                 @OA\Property(property="payment_proof", type="string", format="binary", description="Payment proof document/image (max 2MB)", nullable=true)
     *             )
     *         ),
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"invoice_id", "payment_method", "amount"},
     *                 @OA\Property(property="invoice_id", type="integer", example=1),
     *                 @OA\Property(property="payment_method", type="string", enum={"bank_transfer", "credit_card", "paypal", "other"}, example="bank_transfer"),
     *                 @OA\Property(property="amount", type="number", format="float", example=90.00),
     *                 @OA\Property(property="transaction_id", type="string", example="TXN-123456", nullable=true),
     *                 @OA\Property(property="payment_notes", type="string", example="Payment made via bank transfer", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment submitted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payment submitted successfully. It will be reviewed by admin."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="payment_id", type="integer", example=1),
     *                 @OA\Property(property="invoice_id", type="integer", example=1),
     *                 @OA\Property(property="amount", type="number", format="float", example=90.00),
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="payment_date", type="string", format="date", example="2025-12-24")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - User does not have permission"),
     *     @OA\Response(response=404, description="Invoice not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function submitPayment(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized. Please login to submit payment.',
                ], 401);
            }

            // Check if user is director or super_admin
            if (!$user->hasRole('director') && !$user->hasRole('super_admin')) {
                return response()->json([
                    'status' => false,
                    'message' => 'Only directors and administrators can submit payments.',
                ], 403);
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'invoice_id' => 'required|integer|exists:invoices,id',
                'payment_method' => 'required|string|in:bank_transfer,credit_card,paypal,other',
                'amount' => 'required|numeric|min:0',
                'transaction_id' => 'nullable|string|max:255',
                'payment_notes' => 'nullable|string|max:1000',
                'payment_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Get invoice
            $invoice = Invoice::with('organisation')->findOrFail($request->invoice_id);

            // Check if user can access this invoice
            if (!$user->hasRole('super_admin')) {
                if ($invoice->organisation_id !== $user->orgId) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Unauthorized. You can only submit payments for invoices from your organisation.',
                    ], 403);
                }
            }

            // Handle payment proof upload
            $proofPath = null;
            if ($request->hasFile('payment_proof')) {
                $proofPath = $request->file('payment_proof')->store('payment-proofs', 'public');
            }

            // Create payment
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'organisation_id' => $invoice->organisation_id,
                'paid_by_user_id' => $user->id,
                'payment_method' => $request->payment_method,
                'amount' => $request->amount,
                'transaction_id' => $request->transaction_id,
                'status' => 'pending',
                'payment_date' => now()->toDateString(),
                'payment_notes' => $request->payment_notes,
                'payment_proof' => $proofPath,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Payment submitted successfully. It will be reviewed by admin.',
                'data' => [
                    'payment_id' => $payment->id,
                    'invoice_id' => $payment->invoice_id,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'payment_date' => $payment->payment_date->toDateString(),
                    'payment_method' => $payment->payment_method,
                ],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invoice not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to submit payment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/payments",
     *     tags={"Payments"},
     *     summary="Get payment history",
     *     description="Retrieve payment history for the authenticated user's organisation. Directors can view payments for their organisation.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="invoice_id",
     *         in="query",
     *         description="Filter payments by invoice ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter payments by status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending", "completed", "failed"}, example="pending")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payments retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payments retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="invoice_id", type="integer", example=1),
     *                     @OA\Property(property="invoice_number", type="string", example="INV-202512-0001"),
     *                     @OA\Property(property="amount", type="number", format="float", example=90.00),
     *                     @OA\Property(property="payment_method", type="string", example="bank_transfer"),
     *                     @OA\Property(property="status", type="string", example="pending"),
     *                     @OA\Property(property="payment_date", type="string", format="date", example="2025-12-24"),
     *                     @OA\Property(property="transaction_id", type="string", example="TXN-123456", nullable=true)
     *                 )
     *             ),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="total", type="integer", example=10)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getPayments(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized. Please login to view payments.',
                ], 401);
            }

            // Check if user is director or super_admin
            if (!$user->hasRole('director') && !$user->hasRole('super_admin')) {
                return response()->json([
                    'status' => false,
                    'message' => 'Only directors and administrators can view payments.',
                ], 403);
            }

            $query = Payment::with(['invoice', 'paidBy']);

            // Filter by organisation (directors can only see their org's payments)
            if (!$user->hasRole('super_admin')) {
                $query->where('organisation_id', $user->orgId);
            }

            // Filter by invoice_id if provided
            if ($request->has('invoice_id')) {
                $query->where('invoice_id', $request->invoice_id);
            }

            // Filter by status if provided
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $payments = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => true,
                'message' => 'Payments retrieved successfully',
                'data' => $payments->items(),
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve payments.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

