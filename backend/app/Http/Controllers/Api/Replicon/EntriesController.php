<?php

namespace App\Http\Controllers\Api\Replicon;

use App\Http\Controllers\Controller;
use App\Http\Resources\RepliconTimeEntryResource;
use App\Models\RepliconTimeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EntriesController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $entries = RepliconTimeEntry::query()
            ->when($request->date, fn($q) => $q->where('date', $request->date))
            ->orderBy('date')->orderBy('start')
            ->get();

        return RepliconTimeEntryResource::collection($entries);
    }

    public function store(Request $request): RepliconTimeEntryResource
    {
        $data = $request->validate([
            'date'           => ['required', 'date_format:Y-m-d'],
            'project'        => ['nullable', 'string', 'max:255'],
            'subProject'     => ['nullable', 'string', 'max:255'],
            'description'    => ['required', 'string', 'max:500'],
            'subDescription' => ['nullable', 'string', 'max:500'],
            'furtherInfo'    => ['nullable', 'string', 'max:500'],
            'start'          => ['nullable', 'date_format:H:i'],
            'finish'         => ['nullable', 'date_format:H:i'],
            'durationMinutes'=> ['required', 'integer', 'min:0'],
            'logged'         => ['boolean'],
        ]);

        $entry = RepliconTimeEntry::create([
            'date'            => $data['date'],
            'project'         => $data['project'] ?? '',
            'sub_project'     => $data['subProject'] ?? '',
            'description'     => $data['description'],
            'sub_description' => $data['subDescription'] ?? '',
            'further_info'    => $data['furtherInfo'] ?? '',
            'start'           => $data['start'] ?? null,
            'finish'          => $data['finish'] ?? null,
            'duration_minutes'=> $data['durationMinutes'],
            'logged'          => $data['logged'] ?? false,
        ]);

        return new RepliconTimeEntryResource($entry);
    }

    public function show(RepliconTimeEntry $entry): RepliconTimeEntryResource
    {
        return new RepliconTimeEntryResource($entry);
    }

    public function update(Request $request, RepliconTimeEntry $entry): RepliconTimeEntryResource
    {
        $data = $request->validate([
            'date'           => ['sometimes', 'date_format:Y-m-d'],
            'project'        => ['nullable', 'string', 'max:255'],
            'subProject'     => ['nullable', 'string', 'max:255'],
            'description'    => ['sometimes', 'required', 'string', 'max:500'],
            'subDescription' => ['nullable', 'string', 'max:500'],
            'furtherInfo'    => ['nullable', 'string', 'max:500'],
            'start'          => ['nullable', 'date_format:H:i'],
            'finish'         => ['nullable', 'date_format:H:i'],
            'durationMinutes'=> ['sometimes', 'required', 'integer', 'min:0'],
            'logged'         => ['boolean'],
        ]);

        $entry->update([
            'date'            => $data['date']            ?? $entry->date,
            'project'         => $data['project']         ?? $entry->project,
            'sub_project'     => $data['subProject']      ?? $entry->sub_project,
            'description'     => $data['description']     ?? $entry->description,
            'sub_description' => $data['subDescription']  ?? $entry->sub_description,
            'further_info'    => $data['furtherInfo']     ?? $entry->further_info,
            'start'           => $data['start']           ?? $entry->start,
            'finish'          => $data['finish']          ?? $entry->finish,
            'duration_minutes'=> $data['durationMinutes'] ?? $entry->duration_minutes,
            'logged'          => $data['logged']          ?? $entry->logged,
        ]);

        return new RepliconTimeEntryResource($entry->fresh());
    }

    public function destroy(RepliconTimeEntry $entry): JsonResponse
    {
        $entry->delete();
        return response()->json(null, 204);
    }
}
