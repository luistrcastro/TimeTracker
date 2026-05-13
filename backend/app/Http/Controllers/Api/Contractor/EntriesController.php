<?php

namespace App\Http\Controllers\Api\Contractor;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContractorTimeEntryResource;
use App\Models\ContractorTimeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EntriesController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $entries = ContractorTimeEntry::query()
            ->when($request->date, fn($q) => $q->where('date', $request->date))
            ->orderBy('date')->orderBy('start')
            ->get();

        return ContractorTimeEntryResource::collection($entries);
    }

    public function store(Request $request): ContractorTimeEntryResource
    {
        $data = $request->validate([
            'clientId'        => ['nullable', 'uuid', 'exists:clients,id'],
            'task'            => ['nullable', 'string', 'max:255'],
            'description'     => ['required', 'string', 'max:500'],
            'subDescription'  => ['nullable', 'string', 'max:500'],
            'date'            => ['required', 'date_format:Y-m-d'],
            'start'           => ['nullable', 'date_format:H:i'],
            'finish'          => ['nullable', 'date_format:H:i'],
            'durationMinutes' => ['required', 'integer', 'min:0'],
            'invoiced'        => ['boolean'],
        ]);

        $entry = ContractorTimeEntry::create([
            'client_id'        => $data['clientId'] ?? null,
            'task'             => $data['task'] ?? '',
            'description'      => $data['description'],
            'sub_description'  => $data['subDescription'] ?? '',
            'date'             => $data['date'],
            'start'            => $data['start'] ?? null,
            'finish'           => $data['finish'] ?? null,
            'duration_minutes' => $data['durationMinutes'],
            'invoiced'         => $data['invoiced'] ?? false,
        ]);

        return new ContractorTimeEntryResource($entry);
    }

    public function show(ContractorTimeEntry $entry): ContractorTimeEntryResource
    {
        return new ContractorTimeEntryResource($entry);
    }

    public function update(Request $request, ContractorTimeEntry $entry): ContractorTimeEntryResource
    {
        $data = $request->validate([
            'clientId'        => ['nullable', 'uuid', 'exists:clients,id'],
            'task'            => ['nullable', 'string', 'max:255'],
            'description'     => ['sometimes', 'required', 'string', 'max:500'],
            'subDescription'  => ['nullable', 'string', 'max:500'],
            'date'            => ['sometimes', 'required', 'date_format:Y-m-d'],
            'start'           => ['nullable', 'date_format:H:i'],
            'finish'          => ['nullable', 'date_format:H:i'],
            'durationMinutes' => ['sometimes', 'required', 'integer', 'min:0'],
        ]);

        $entry->update([
            'client_id'        => array_key_exists('clientId', $data) ? $data['clientId'] : $entry->client_id,
            'task'             => $data['task'] ?? $entry->task,
            'description'      => $data['description'] ?? $entry->description,
            'sub_description'  => $data['subDescription'] ?? $entry->sub_description,
            'date'             => $data['date'] ?? $entry->date,
            'start'            => $data['start'] ?? $entry->start,
            'finish'           => $data['finish'] ?? $entry->finish,
            'duration_minutes' => $data['durationMinutes'] ?? $entry->duration_minutes,
        ]);

        return new ContractorTimeEntryResource($entry->fresh());
    }

    public function destroy(ContractorTimeEntry $entry): JsonResponse
    {
        $entry->delete();
        return response()->json(null, 204);
    }
}
