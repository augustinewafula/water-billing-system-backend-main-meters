<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateAlertContactRequest;
use App\Models\AlertContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AlertContactController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $current_user_id = auth()->guard('api')->user()->id;
        $alert_contacts = AlertContact::where('user_id', $current_user_id)
            ->paginate(10);
        return response()->json($alert_contacts);

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param CreateAlertContactRequest $request
     * @return Response
     */
    public function store(CreateAlertContactRequest $request)
    {
        $current_user_id = auth()->guard('api')->user()->id;
        return AlertContact::create([
            'user_id' => $current_user_id,
            'type' => $request->type,
            'value' => $request->value
        ]);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param AlertContact $alertContact
     * @return JsonResponse
     */
    public function update(Request $request, AlertContact $alertContact)
    {
        $alertContact->create([
            'type' => $request->type,
            'value' => $request->value
        ]);
        return response()->json($alertContact);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param AlertContact $alertContact
     * @return JsonResponse
     */
    public function destroy(AlertContact $alertContact)
    {
        $alertContact->delete();
        return response()->json('deleted');
    }
}
