<?php

namespace App\Http\Controllers\Api\Replicon;

use App\Http\Controllers\Controller;
use App\Models\RepliconProject;
use App\Models\RepliconTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectsCacheController extends Controller
{
    public function index(): JsonResponse
    {
        $projects = RepliconProject::with('tasks')
            ->where('user_id', auth()->id())
            ->orderBy('name')
            ->get()
            ->map(fn($p) => $this->formatProject($p));

        return response()->json(['projects' => $projects]);
    }

    public function updateProject(Request $request, RepliconProject $project): JsonResponse
    {
        $data = $request->validate(['active' => ['required', 'boolean']]);
        $project->update(['user_disabled' => ! $data['active']]);

        return response()->json($this->formatProject($project->fresh('tasks')));
    }

    public function updateTask(Request $request, RepliconTask $task): JsonResponse
    {
        abort_unless($task->project->user_id === auth()->id(), 404);

        $data = $request->validate(['active' => ['required', 'boolean']]);
        $task->update(['user_disabled' => ! $data['active']]);

        return response()->json($this->formatProject($task->project()->with('tasks')->first()));
    }

    private function formatProject(RepliconProject $p): array
    {
        return [
            'id'         => $p->id,
            'repliconId' => $p->replicon_id,
            'code'       => $p->code,
            'name'       => $p->name,
            'syncedAt'   => $p->synced_at?->toISOString(),
            'isActive'   => $p->is_active && ! $p->user_disabled,
            'syncActive' => $p->is_active,
            'tasks'      => $p->tasks->map(fn($t) => [
                'id'             => $t->id,
                'repliconTaskId' => $t->replicon_task_id,
                'name'           => $t->name,
                'path'           => $t->path ?? [],
                'isActive'       => $t->is_active && ! $t->user_disabled,
                'syncActive'     => $t->is_active,
            ]),
        ];
    }
}
