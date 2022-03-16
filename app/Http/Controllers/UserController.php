<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Traits\GeneratePassword;
use Exception;
use Hash;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Log;
use Spatie\Permission\Models\Role;
use Str;
use Throwable;

class UserController extends Controller
{
    use GeneratePassword;

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $users = User::role('user');
        $users->with('meter');
        $users = $this->filterQuery($request, $users);
        return response()->json($users->paginate(10));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param CreateUserRequest $request
     * @return JsonResponse
     * @throws Exception
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        $password = $this->generatePassword(10);
        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->phone = $request->phone;
        $user->meter_id = $request->meter_id;
        $user->password = Hash::make($password);
        $user->assignRole(Role::findByName('user'));
        $user->save();

        return response()->json($user, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $user = User::with('meter')
            ->where('id', $id)
            ->first();
        return response()->json($user);
    }

    public function billing_report(Request $request, $user_id): JsonResponse
    {
        $year = $request->query('year');
        return response()->json(
            User::select('meter_billing_reports.*')
                ->join('meters', 'meters.id', 'users.meter_id')
                ->join('meter_billing_reports', 'meter_billing_reports.meter_id', 'meters.id')
                ->where('meter_billing_reports.year', $year)
                ->where('users.id', $user_id)
                ->first()
        );
    }

    public function billing_report_years($user_id): JsonResponse
    {
        return response()->json(
            User::select('meter_billing_reports.year as text')
                ->join('meters', 'meters.id', 'users.meter_id')
                ->join('meter_billing_reports', 'meter_billing_reports.meter_id', 'meters.id')
                ->where('users.id', $user_id)
                ->distinct('meter_billing_reports.year')
                ->orderBy('meter_billing_reports.created_at', 'desc')
                ->get()
        );
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateUserRequest $request
     * @param User $user
     * @return JsonResponse
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user->update($request->validated());
        return response()->json($user);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param User $user
     * @return JsonResponse
     */
    public function destroy(User $user): JsonResponse
    {
        try {
            $user->delete();
            return response()->json('deleted');
        } catch (Throwable $throwable) {
            Log::error($throwable);
            $response = ['message' => 'Failed to delete'];
            return response()->json($response, 422);
        }
    }

    private function filterQuery(Request $request, Builder $users): Builder
    {
        $search = $request->query('search');
        $searchByNameAndPhone = $request->query('searchByNameAndPhone');
        $searchByNameAndMeterNo = $request->query('searchByNameAndMeterNo');
        $searchByMeterID = $request->query('searchByMeterID');
        $sortBy = $request->query('sortBy');
        $sortOrder = $request->query('sortOrder');
        $stationId = $request->query('station_id');

        //TODO::implement search with meter number. Currently not working
        if ($request->has('search') && Str::length($request->query('search')) > 0) {
            $users = $users->where(function ($users) use ($search, $stationId) {
                $users->orWhere('name', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->whereHas('meter', function ($query) use ($search) {
                        $query->orWhere('number', 'like', '%' . $search . '%');
                    });
            });
        }
        if ($request->has('searchByNameAndPhone') && Str::length($searchByNameAndPhone) > 0) {
            $users = $users->where(function ($users) use ($searchByNameAndPhone) {
                $users->orWhere('name', 'like', '%' . $searchByNameAndPhone . '%')
                    ->orWhere('phone', 'like', '%' . $searchByNameAndPhone . '%');
            });
        }
        if ($request->has('searchByNameAndMeterNo') && Str::length($searchByNameAndMeterNo) > 0) {
            $users = $users->whereHas('meter', function ($query) use ($searchByNameAndMeterNo) {
                $query->where('number', 'like', '%' . $searchByNameAndMeterNo . '%');
            })->orWhere('name', 'like', '%' . $searchByNameAndMeterNo . '%');
        }
        if ($request->has('searchByMeterID') && Str::length($searchByMeterID) > 0) {
            $users->where('meter_id', $searchByMeterID);
        }
        if ($request->has('searchByMeter') && Str::length($request->query('searchByMeter')) > 0) {
            //TODO::implement search with meter number
//            $users->where('meters.number', 'like', '%' . $search . '%');
        }
        if ($request->has('station_id')) {
            $users = $users->whereHas('meter', function ($query) use ($stationId) {
                $query->where('station_id', $stationId);
            });
        }
        if ($request->has('sortBy')) {
            $users = $users->orderBy($sortBy, $sortOrder);
        }
        return $users;
    }
}
