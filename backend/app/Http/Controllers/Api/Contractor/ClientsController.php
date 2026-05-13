<?php

namespace App\Http\Controllers\Api\Contractor;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientsController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ClientResource::collection(Client::with('tasks')->orderBy('name')->get());
    }

    public function store(Request $request): ClientResource
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'legalName' => ['nullable', 'string', 'max:500'],
            'address'   => ['nullable', 'string'],
            'phone'     => ['nullable', 'string', 'max:50'],
            'email'     => ['nullable', 'email', 'max:255'],
            'tasks'     => ['nullable', 'array'],
            'tasks.*'   => ['string', 'max:255'],
        ]);

        $client = Client::create([
            'name'       => $data['name'],
            'legal_name' => $data['legalName'] ?? '',
            'address'    => $data['address'] ?? '',
            'phone'      => $data['phone'] ?? '',
            'email'      => $data['email'] ?? '',
        ]);

        foreach ($data['tasks'] ?? [] as $taskName) {
            $client->tasks()->firstOrCreate(['name' => $taskName]);
        }

        return new ClientResource($client->load('tasks'));
    }

    public function show(Client $client): ClientResource
    {
        return new ClientResource($client->load('tasks'));
    }

    public function update(Request $request, Client $client): ClientResource
    {
        $data = $request->validate([
            'legalName' => ['nullable', 'string', 'max:500'],
            'address'   => ['nullable', 'string'],
            'phone'     => ['nullable', 'string', 'max:50'],
            'email'     => ['nullable', 'email', 'max:255'],
            'tasks'     => ['nullable', 'array'],
            'tasks.*'   => ['string', 'max:255'],
        ]);

        $client->update([
            'legal_name' => $data['legalName'] ?? $client->legal_name,
            'address'    => $data['address']   ?? $client->address,
            'phone'      => $data['phone']     ?? $client->phone,
            'email'      => $data['email']     ?? $client->email,
        ]);

        if (isset($data['tasks'])) {
            foreach ($data['tasks'] as $taskName) {
                $client->tasks()->firstOrCreate(['name' => $taskName]);
            }
        }

        return new ClientResource($client->load('tasks'));
    }

    public function destroy(Client $client): JsonResponse
    {
        $client->delete();
        return response()->json(null, 204);
    }
}
