<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateConcentratorRequest;
use App\Http\Requests\UpdateConcentratorRequest;
use App\Models\Concentrator;
use App\Services\ConcentratorService;
use App\Traits\FiltersRequestQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class ConcentratorController extends Controller
{
    use FiltersRequestQuery;

    public function __construct()
    {
        $this->middleware('permission:concentrator-list', ['only' => ['index', 'show']]);
        $this->middleware('permission:concentrator-create', ['only' => ['store']]);
        $this->middleware('permission:concentrator-edit', ['only' => ['update']]);
        $this->middleware('permission:concentrator-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $concentrators = Concentrator::query();
        $concentrators = $this->filterQuery($request, $concentrators);

        $perPage = 10;
        if ($request->has('perPage')){
            $perPage = $request->perPage;
        }

        return response()->json($concentrators->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param CreateConcentratorRequest $request
     * @return JsonResponse
     * @throws \JsonException
     */
    public function store(CreateConcentratorRequest $request, ConcentratorService $concentratorService): JsonResponse
    {
        $concentrator = Concentrator::create($request->validated());
        $concentratorService->register(
            $concentrator->concentrator_id,
            $concentrator->name
        );
        return response()->json(['message' => $concentrator, 'status_code' => 201]);
    }

    /**
     * Display the specified resource.
     *
     * @param $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $concentrator = Concentrator::where('id', $id)->firstOrFail();
        return response()->json($concentrator);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateConcentratorRequest $request
     * @param Concentrator $concentrator
     * @return JsonResponse
     */
    public function update(UpdateConcentratorRequest $request, Concentrator $concentrator): JsonResponse
    {
        $oldConcentratorId = $concentrator->concentrator_id;
        $concentrator->update($request->validated());

        if ($oldConcentratorId !== $concentrator->concentrator_id) {
            $concentratorService = new ConcentratorService();
            $concentratorService->register(
                $concentrator->concentrator_id,
                $concentrator->name
            );
        }

        return response()->json($concentrator);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Concentrator $concentrator
     * @return JsonResponse
     */
    public function destroy(Concentrator $concentrator): JsonResponse
    {
        $concentrator->delete();
        return response()->json('deleted');
    }

    /**
     * Filtering query based on request.
     * Note: Extend this as needed.
     *
     * @param Request $request
     * @param Builder $concentrators
     * @return Builder
     */
    private function filterQuery(Request $request, Builder $concentrators): Builder
    {
        // Here, you can add the same kind of filtering logic you used in the MeterController
        // Example:
        $search = $request->query('search');

        if ($request->has('search') && $search != '') {
            $concentrators = $concentrators->where('name', 'like', '%' . $search . '%');
        }

        return $concentrators;
    }
}
