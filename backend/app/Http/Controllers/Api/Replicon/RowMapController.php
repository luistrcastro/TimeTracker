<?php

namespace App\Http\Controllers\Api\Replicon;

use App\Http\Controllers\Controller;
use App\Models\RepliconProject;
use App\Models\RepliconRowMap;
use App\Models\RepliconTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RowMapController extends Controller
{
    public function index(): JsonResponse
    {
        $map = RepliconRowMap::where('user_id', auth()->id())
            ->get()
            ->keyBy(fn($r) => "{$r->replicon_project_id}:{$r->replicon_task_id}")
            ->map(fn($r) => $r->row_index);

        return response()->json($map);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'map'   => ['required', 'array'],
            'map.*' => ['integer', 'min:0'],
        ]);

        foreach ($data['map'] as $key => $rowIndex) {
            [$projectId, $taskId] = explode(':', $key, 2);
            RepliconRowMap::updateOrCreate(
                [
                    'user_id'             => auth()->id(),
                    'replicon_project_id' => $projectId,
                    'replicon_task_id'    => $taskId,
                ],
                ['row_index' => $rowIndex]
            );
        }

        return $this->index();
    }

    /**
     * Called by the bookmarklet — accepts Replicon IDs and resolves them to internal UUIDs.
     *
     * Payload: { rows: [{ rowId, projectId, taskId, projectName, taskName }] }
     */
    public function storeFromBookmarklet(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rows'              => ['required', 'array'],
            'rows.*.rowId'      => ['required', 'integer', 'min:0'],
            'rows.*.projectId'  => ['required', 'string'],
            'rows.*.taskId'     => ['required', 'string'],
            'rows.*.projectName'=> ['nullable', 'string'],
            'rows.*.taskName'   => ['nullable', 'string'],
        ]);

        $userId = auth()->id();
        $count  = 0;

        foreach ($data['rows'] as $row) {
            $project = RepliconProject::where('user_id', $userId)
                ->where('replicon_id', (string) $row['projectId'])
                ->first();

            if (! $project) {
                continue;
            }

            $task = RepliconTask::where('replicon_project_id', $project->id)
                ->where('replicon_task_id', (string) $row['taskId'])
                ->first();

            if (! $task) {
                continue;
            }

            RepliconRowMap::updateOrCreate(
                [
                    'user_id'             => $userId,
                    'replicon_project_id' => $project->id,
                    'replicon_task_id'    => $task->id,
                ],
                ['row_index' => (int) $row['rowId']]
            );
            $count++;
        }

        return response()->json(['count' => $count, 'total' => count($data['rows'])]);
    }
}
