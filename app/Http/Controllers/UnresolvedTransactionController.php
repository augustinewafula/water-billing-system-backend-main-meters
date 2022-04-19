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
use Throwable;

class UnresolvedTransactionController extends Controller
{
    use ProcessesMpesaTransaction;

    public function index(): JsonResponse
    {
        return response()->json(UnresolvedMpesaTransaction::select('unresolved_mpesa_transactions.reason', 'unresolved_mpesa_transactions.id', 'mpesa_transactions.TransID as transaction_reference', 'mpesa_transactions.TransAmount as amount', 'mpesa_transactions.BillRefNumber as account_number', 'mpesa_transactions.MSISDN as phone_number', 'mpesa_transactions.created_at as transaction_time')
            ->join('mpesa_transactions', 'mpesa_transactions.id', 'unresolved_mpesa_transactions.mpesa_transaction_id')
            ->latest('transaction_time')
            ->get());
    }

    /**
     * @throws Throwable
     * @throws JsonException
     */
    public function assign(AssignUnresolvedTransactionRequest $request)
    {
        try {
            $user = User::find($request->user_id);
            $unresolved_mpesa_transaction = UnresolvedMpesaTransaction::find($request->id);
            $mpesa_transaction = MpesaTransaction::find($unresolved_mpesa_transaction->mpesa_transaction_id);

            DB::beginTransaction();
            $mpesa_transaction->update([
                'BillRefNumber' => $user->account_number
            ]);
            $this->processMpesaTransaction($mpesa_transaction);
            $unresolved_mpesa_transaction->forceDelete();
            DB::commit();
        } catch (Throwable $throwable){
            DB::rollBack();
            Log::error($throwable);
            $response = ['message' => 'Something went wrong.'];
            return response($response, 422);
        }
        return response()->json('success');

    }
}
