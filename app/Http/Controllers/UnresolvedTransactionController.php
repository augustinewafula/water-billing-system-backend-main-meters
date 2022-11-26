<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignUnresolvedTransactionRequest;
use App\Models\MpesaTransaction;
use App\Models\UnresolvedMpesaTransaction;
use App\Models\User;
use App\Traits\ProcessesMpesaTransaction;
use DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use Log;
use Str;
use Throwable;

class UnresolvedTransactionController extends Controller
{
    use ProcessesMpesaTransaction;

    public function __construct()
    {
        $this->middleware('permission:unresolved-mpesa-transaction-list', ['only' => ['index', 'show']]);
    }

    public function index(Request $request): JsonResponse
    {
        $sortBy = $request->query('sortBy');
        $search_filter = $request->query('search_filter');
        $sortOrder = $request->query('sortOrder');
        $search = $request->query('search');

        $unresolvedTransaction = UnresolvedMpesaTransaction::select('unresolved_mpesa_transactions.reason', 'unresolved_mpesa_transactions.id', 'mpesa_transactions.TransID as transaction_reference', 'mpesa_transactions.TransAmount as amount', 'mpesa_transactions.BillRefNumber as account_number', 'mpesa_transactions.MSISDN as phone_number', 'mpesa_transactions.created_at as transaction_time')
            ->join('mpesa_transactions', 'mpesa_transactions.id', 'unresolved_mpesa_transactions.mpesa_transaction_id');
        if ($sortBy !== 'undefined') {
            $unresolvedTransaction->orderBy($sortBy, $sortOrder);
        }
        $perPage = 10;
        if ($request->has('perPage')){
            $perPage = $request->perPage;
        }
        if ($request->has('search') && Str::length($search) > 0) {
            $unresolvedTransaction = $unresolvedTransaction->where(function ($query) use ($search, $search_filter) {
                $query->where($search_filter, 'like', '%' . $search . '%');
            });
        }

        return response()->json($unresolvedTransaction->paginate($perPage));
    }

    /**
     * @throws Throwable
     * @throws JsonException
     */
    public function assign(AssignUnresolvedTransactionRequest $request): JsonResponse
    {
        try {
            $user = User::find($request->user_id);
            $unresolved_mpesa_transaction = UnresolvedMpesaTransaction::find($request->id);
            $mpesa_transaction = MpesaTransaction::find($unresolved_mpesa_transaction->mpesa_transaction_id);

            $account_number = $user->account_number;
            if ($request->account_type === 2){
                $account_number = $user->account_number.'-meter';
            }

            DB::beginTransaction();
            $mpesa_transaction->update([
                'BillRefNumber' => $account_number
            ]);
            $this->processMpesaTransaction($mpesa_transaction);
            $unresolved_mpesa_transaction->forceDelete();
            DB::commit();
        } catch (Throwable $throwable){
            DB::rollBack();
            Log::error($throwable);
            $response = ['message' => 'Something went wrong.'];
            return response()->json($response, 422);
        }
        return response()->json('success');

    }


    public function destroy(UnresolvedMpesaTransaction $unresolvedMpesaTransaction): JsonResponse
    {
        $unresolvedMpesaTransaction->delete();
        return response()->json(['message' => 'Transaction deleted']);
    }
}
