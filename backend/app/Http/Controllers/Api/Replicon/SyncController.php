<?php

namespace App\Http\Controllers\Api\Replicon;

use App\Http\Controllers\Controller;
use App\Jobs\SyncRepliconProjects;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    public function store(): JsonResponse
    {
        SyncRepliconProjects::dispatch(auth()->id());

        return response()->json(['message' => 'Sync started', 'status' => 'queued']);
    }
}
