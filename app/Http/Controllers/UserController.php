<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateSystemUserRequest;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Jobs\SendSetPasswordEmail;
use App\Models\ConnectionFeeCharge;
use App\Models\MeterStation;
use App\Models\MonthlyServiceChargeReport;
use App\Models\User;
use App\Traits\GeneratesMonthlyConnectionFee;
use App\Traits\GeneratesMonthlyServiceCharge;
use App\Traits\GeneratesPassword;
use Carbon\Carbon;
use DB;
use Exception;
use Hash;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JsonException;
use Log;
use Spatie\Permission\Models\Role;
use Str;
use Throwable;
use Illuminate\Support\Arr;

class UserController extends Controller
{
    use GeneratesPassword, GeneratesMonthlyServiceCharge, GeneratesMonthlyConnectionFee;

    public function __construct()
    {
        $this->middleware('permission:user-list', ['only' => ['index', 'show', 'download']]);
        $this->middleware('permission:user-create', ['only' => ['store']]);
        $this->middleware('permission:user-edit', ['only' => ['update']]);
        $this->middleware('permission:user-delete', ['only' => ['destroy']]);
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
        $users = $users->whereHas('roles', function ($query) {
            return $query->whereNotIn('name', ['user', 'super-admin']);
        });
        return response()->json($users->paginate(10));
    }

    public function download(Request $request): JsonResponse
    {
        $stationId = $request->query('station_id');
        $users = User::role('user')
            ->select('users.name as Name', 'users.phone as Contact', 'users.account_number as Account Number', 'meters.last_reading as Previous Reading', )
            ->join('meters', 'meters.id', 'users.meter_id');

        $fileName = 'Customers';
        if ($request->has('station_id')) {
            $users = $users->where('meters.station_id', $stationId);
            $station_name = MeterStation::find($stationId)->name;
            $fileName = "$station_name $fileName";
        }
        $month_and_year = Carbon::now()->isoFormat('MMMM YYYY');
        $fileName = "$fileName $month_and_year";
        $current_month = Carbon::now()->isoFormat('MMMM');
        $users = $users->addSelect(DB::raw("'' as 'Current Reading'"), DB::raw("'$current_month' as Month"));

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
            $data = $this->getRequestData($request, 'save');
            $user = User::create($data);
            $user->assignRole(Role::findByName('user'));
            MonthlyServiceChargeReport::create([
                'user_id' => $user->id,
                'year' => now()->year,
            ]);

//            $monthly_service_charge = Setting::where('key', 'monthly_service_charge')
//                ->first()
//                ->value;
//            $this->generateUserMonthlyServiceCharge($user, $monthly_service_charge);

            if ($user->should_pay_connection_fee){
                $this->generateConnectionFee($user);
            }
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

        $this->sendSetPasswordEmail($request->email);

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
     * @throws Throwable
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $this->getRequestData($request, 'update');
        $user->update($data);
        if ($user->should_pay_connection_fee){
            $this->generateConnectionFee($user);
        }
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
            $user->forceDelete();
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
                    ->orWhere('account_number', 'like', '%' . $search . '%')
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
        if ($sortBy !== 'undefined' && $request->has('sortBy')) {
            $users = $users->orderBy($sortBy, $sortOrder);
        }
        return $users;
    }

    /**
     * @param $email
     * @return void
     */
    public function sendSetPasswordEmail($email): void
    {
        $token = Str::random(64);
        DB::table('password_resets')->insert([
            'email' => $email,
            'token' => $token,
            'created_at' => Carbon::now()
        ]);

        $url = env('APP_FRONTEND_URL') . "reset-password/$token?email=$email&action=set";
        SendSetPasswordEmail::dispatch($email, $url);
    }

    /**
     * @param $user
     * @throws Throwable
     */
    private function generateConnectionFee($user): void
    {
        $user = User::where('id', $user->id)
            ->with('meter')
            ->firstOrFail();
        $connection_fee_charges = ConnectionFeeCharge::where('station_id', $user->meter->station_id)
            ->first();
        $connection_fee = $connection_fee_charges->connection_fee;
        if ($user->total_connection_fee_paid < $connection_fee) {
            $monthly_connection_fee = $connection_fee_charges->connection_fee_monthly_installment;
            $this->generateUserMonthlyConnectionFee($user, $monthly_connection_fee);
        }
    }

    /**
     * @param $request
     * @param $action
     * @return array
     * @throws JsonException
     * @throws Exception
     */
    private function getRequestData($request, $action): array
    {
        $communication_channels = json_decode($request->communication_channels, false, 512, JSON_THROW_ON_ERROR);
        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'meter_id' => $request->meter_id,
            'account_number' => $request->account_number,
            'first_connection_fee_on' => $request->first_connection_fee_on,
            'should_pay_connection_fee' => $request->should_pay_connection_fee,
            'use_custom_charges_for_cost_per_unit' => $request->use_custom_charges_for_cost_per_unit,
            'cost_per_unit' => $request->cost_per_unit,
            'communication_channels' => $communication_channels
        ];
        if ($action === 'save'){
            $password = $this->generatePassword(10);
            $data = Arr::add($data, 'password', $password);
        }
        return $data;
    }

}
