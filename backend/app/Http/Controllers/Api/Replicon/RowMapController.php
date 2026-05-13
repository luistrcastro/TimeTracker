<?php

namespace App\Http\Controllers\Api\Replicon;

use App\Http\Controllers\Controller;
use App\Models\RepliconRowMap;
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
}
