<?php

namespace App\Http\Controllers\Api\Replicon;

use App\Http\Controllers\Controller;
use App\Models\RepliconCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CredentialsController extends Controller
{
    public function show(): JsonResponse
    {
        $cred = RepliconCredential::where('user_id', auth()->id())->first();

        return response()->json([
            'configured'           => (bool) $cred,
            'base_url'             => $cred?->base_url ?? '',
            'server_view_state_id' => $cred?->server_view_state_id ?? '',
            'session_id'           => $cred?->session_id ?? '',
            'cookie_set'           => (bool) $cred?->cookie_header,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'base_url'             => ['required', 'url'],
            'session_id'           => ['required', 'string'],
            'server_view_state_id' => ['required', 'string'],
            'cookie_header'        => ['required', 'string'],
        ]);

        $cred = RepliconCredential::updateOrCreate(
            ['user_id' => auth()->id()],
            array_merge($data, [
                'last_request_index' => 0,
                'expires_at'         => now()->addMinutes(15),
            ])
        );

        return response()->json([
            'configured'           => true,
            'base_url'             => $cred->base_url,
            'server_view_state_id' => $cred->server_view_state_id,
            'session_id'           => $cred->session_id,
            'cookie_set'           => true,
        ]);
    }

    public function destroy(): JsonResponse
    {
        RepliconCredential::where('user_id', auth()->id())->delete();
        return response()->json(null, 204);
    }
}
