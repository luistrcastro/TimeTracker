<?php

namespace App\Http\Controllers\Api\Replicon;

use App\Http\Controllers\Controller;
use App\Services\Replicon\RepliconClient;
use App\Services\Replicon\RepliconSubmitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubmitController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rows'              => ['required', 'array', 'min:1'],
            'rows.*.projectId'  => ['required', 'string'],
            'rows.*.taskId'     => ['required', 'string'],
            'rows.*.rowIndex'   => ['required', 'integer', 'min:0'],
            'rows.*.hours'      => ['required', 'numeric', 'min:0'],
            'rows.*.comment'    => ['nullable', 'string'],
            'date'              => ['required', 'date_format:Y-m-d'],
        ]);

        $user    = auth()->user();
        $results = (new RepliconSubmitService(new RepliconClient($user)))->submit($user, $data['rows'], $data['date']);

        return response()->json(['results' => $results]);
    }
}
