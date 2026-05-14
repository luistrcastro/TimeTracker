<?php

namespace App\Http\Controllers\Api\Replicon;

use App\Http\Controllers\Controller;
use App\Models\RepliconProject;
use App\Services\Replicon\RepliconClient;
use App\Services\Replicon\RepliconSyncService;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    public function store(): JsonResponse
    {
        $user   = auth()->user();
        $synced = (new RepliconSyncService(new RepliconClient($user)))->sync($user);

        $projects = RepliconProject::with('tasks')
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get()
            ->map(fn($p) => [
                'id'         => $p->id,
                'repliconId' => $p->replicon_id,
                'code'       => $p->code,
                'name'       => $p->name,
                'syncedAt'   => $p->synced_at?->toISOString(),
                'tasks'      => $p->tasks->map(fn($t) => [
                    'id'             => $t->id,
                    'repliconTaskId' => $t->replicon_task_id,
                    'name'           => $t->name,
                ]),
            ]);

        return response()->json([
            'message'  => "Synced {$synced['projects']} projects and {$synced['tasks']} tasks",
            'projects' => $projects,
        ]);
    }
}
