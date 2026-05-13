<?php

namespace App\Http\Controllers\Api\Replicon;

use App\Http\Controllers\Controller;
use App\Models\RepliconProject;
use Illuminate\Http\JsonResponse;

class ProjectsCacheController extends Controller
{
    public function index(): JsonResponse
    {
        $projects = RepliconProject::with('tasks')
            ->where('user_id', auth()->id())
            ->orderBy('name')
            ->get()
            ->map(function ($p) {
                return [
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
                ];
            });

        return response()->json(['projects' => $projects]);
    }
}
