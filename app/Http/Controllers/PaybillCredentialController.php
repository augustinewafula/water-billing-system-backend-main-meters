<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaybillCredentialRequest;
use App\Http\Requests\UpdatePaybillCredentialRequest;
use App\Models\PaybillCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class PaybillCredentialController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(PaybillCredential::all());
    }

    public function store(StorePaybillCredentialRequest $request): JsonResponse
    {
        if ($request->boolean('is_default')) {
            PaybillCredential::where('is_default', true)->update(['is_default' => false]);
        }

        $paybill = PaybillCredential::create([
            'id' => Str::uuid(),
            'shortcode' => $request->shortcode,
            'consumer_key' => $request->consumer_key,
            'consumer_secret' => $request->consumer_secret,
            'initiator_username' => $request->initiator_username,
            'is_default' => $request->boolean('is_default', false),
        ]);

        return response()->json($paybill, 201);
    }

    public function show(PaybillCredential $paybill_credential): JsonResponse
    {
        return response()->json($paybill_credential);
    }

    public function update(UpdatePaybillCredentialRequest $request, PaybillCredential $paybill_credential): JsonResponse
    {
        if ($request->boolean('is_default')) {
            PaybillCredential::where('is_default', true)->where('id', '!=', $paybill_credential->id)->update(['is_default' => false]);
        }

        $paybill_credential->update([
            'shortcode' => $request->shortcode,
            'consumer_key' => $request->consumer_key,
            'consumer_secret' => $request->consumer_secret,
            'initiator_username' => $request->initiator_username,
            'is_default' => $request->boolean('is_default', false),
        ]);

        return response()->json($paybill_credential);
    }

    public function destroy(PaybillCredential $paybill_credential): JsonResponse
    {
        $paybill_credential->delete();

        return response()->json(null, 204);
    }
}

