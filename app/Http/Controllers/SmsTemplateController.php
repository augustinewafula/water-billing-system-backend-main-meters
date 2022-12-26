<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateSmsTemplateRequest;
use App\Models\SmsTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmsTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json( SmsTemplate::latest()->get());
    }

    public function store(CreateSmsTemplateRequest $request): JsonResponse
    {
        $smsTemplate = SmsTemplate::create($request->validated());

        return response()->json([
            'sms_template' => $smsTemplate,
        ]);
    }

    public function destroy(SmsTemplate $smsTemplate): JsonResponse
    {
        $smsTemplate->delete();

        return response()->json([
            'message' => 'Sms template deleted successfully', 204
        ]);
    }
}
