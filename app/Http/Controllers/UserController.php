<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateSystemUserRequest;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Jobs\SendSetPasswordEmail;
use App\Models\MeterStation;
use App\Models\MonthlyServiceChargeReport;
use App\Models\Setting;
use App\Models\User;
use App\Traits\GeneratesMonthlyServiceCharge;
use App\Traits\GeneratesPassword;
use DB;
use Exception;
use Hash;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Log;
use Spatie\Permission\Models\Role;
use Str;
use Throwable;

class UserController extends Controller
{
    use GeneratesPassword, GeneratesMonthlyServiceCharge;

    public function __construct()
    {
        $this->middleware('permission:user-list', ['only' => ['index', 'show', 'download']]);
        $this->middleware('permission:user-create', ['only' => ['store']]);
        $this->middleware('permission:user-edit', ['only' => ['update']]);
        $this->middleware('permission:user-delete', ['only' => ['destroy']]);
        $this->middleware('permission:admin-list', ['only' => ['systemUsersIndex']]);
        $this->middleware('permission:meter-billing-report-list', ['only' => ['billing_report', 'billing_report_years']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $users = User::query();
        $users->with('meter');
        $users = $this->filterQuery($request, $users);
        $users = $users->role('user');
        return response()->json($users->paginate(10));
    }

    public function systemUsersIndex(Request $request): JsonResponse
    {
        $users = User::with('roles');
        $users = $this->filterQuery($request, $users);
        $users = $users->role(['admin', 'supervisor']);
        return response()->json($users->paginate(10));
    }

    public function rolesIndex(): JsonResponse
    {
        return response()
            ->json(
                Role::where('name', '!=', 'super-admin')
                    ->where('name', '!=', 'user')
                    ->pluck('name')
                    ->all()
            );
    }

    public function download(Request $request): JsonResponse
    {
        $stationId = $request->query('station_id');
        $users = User::role('user')
            ->select('users.name as Name', 'users.phone as Phone', 'users.email as Email', 'meters.number as Meter Number', 'users.account_number as Account Number')
            ->join('meters', 'meters.id', 'users.meter_id');

        $fileName = 'Customers';
        if ($request->has('station_id')) {
            $users = $users->where('meters.station_id', $stationId);
            $station_name = MeterStation::find($stationId)->name;
            $fileName = "$station_name $fileName";
        }

        return response()->json([
            'users' => $users->get(),
            'filename' => $fileName,
        ]);
    }

    /**re
     * Store a newly created resource in storage.
     *
     * @param CreateUserRequest $request
     * @return Application|ResponseFactory|JsonResponse|Response
     * @throws Exception|Throwable
     */
    public function store(CreateUserRequest $request)
    {
        try {
            DB::beginTransaction();
            $password = $this->generatePassword(10);
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'meter_id' => $request->meter_id,
                'account_number' => $request->account_number,
                'first_monthly_service_fee_on' => $request->first_monthly_service_fee_on,
                'password' => Hash::make($password),
            ]);
            $user->assignRole(Role::findByName('user'));
            MonthlyServiceChargeReport::create([
                'user_id' => $user->id,
                'year' => now()->year,
            ]);

            $monthly_service_charge = Setting::where('key', 'monthly_service_charge')
                ->first()
                ->value;
            $this->generateUserMonthlyServiceCharge($user, $monthly_service_charge);
            DB::commit();
        } catch (Throwable $th) {
            DB::rollBack();
            Log::error($th);
            $response = ['message' => 'Something went wrong, please try again later'];
            return response($response, 422);
        }

        return response()->json($user, 201);
    }

    /**
     * @throws Exception
     */
    public function storeSystemUser(CreateSystemUserRequest $request): JsonResponse
    {
        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = Hash::make($this->generatePassword(10));
        $user->assignRole(Role::findByName($request->role));
        $user->save();

        SendSetPasswordEmail::dispatch($request->email);

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
        $user = User::with('meter.type')
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
        $searchByMeter = $request->query('searchByMeter');
        $searchByNameAndPhone = $request->query('searchByNameAndPhone');
        $searchByNameAndMeterNo = $request->query('searchByNameAndMeterNo');
        $searchByNameAndAccountNo = $request->query('searchByNameAndAccountNo');
        $searchByMeterID = $request->query('searchByMeterID');
        $sortBy = $request->query('sortBy');
        $sortOrder = $request->query('sortOrder');
        $stationId = $request->query('station_id');

        if ($request->has('search') && Str::length($search) > 0) {
            $users = $users->where(function ($users) use ($search, $stationId) {
                $users->whereHas('meter', function ($query) use ($search) {
                    $query->where('number', 'like', '%' . $search . '%');
                })->orWhere('name', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
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
        if ($request->has('searchByNameAndAccountNo') && Str::length($searchByNameAndAccountNo) > 0) {
            $users = $users->where(function ($users) use ($searchByNameAndAccountNo) {
                $users->orWhere('name', 'like', '%' . $searchByNameAndAccountNo . '%')
                    ->orWhere('account_number', 'like', '%' . $searchByNameAndAccountNo . '%');
            });
        }
        if ($request->has('searchByMeterID') && Str::length($searchByMeterID) > 0) {
            $users->where('meter_id', $searchByMeterID);
        }
        if ($request->has('searchByMeter') && Str::length($searchByMeter) > 0) {
            $users = $users->whereHas('meter', function ($query) use ($searchByMeter) {
                $query->where('number', 'like', '%' . $searchByMeter . '%');
            });
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
