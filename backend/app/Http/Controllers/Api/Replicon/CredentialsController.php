<?php

namespace App\Http\Controllers\Api\Replicon;

use App\Http\Controllers\Controller;
use App\Models\RepliconCredential;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CredentialsController extends Controller
{
    public function show(): JsonResponse
    {
        try {
            $cred = RepliconCredential::where('user_id', auth()->id())->first();

            return response()->json([
                'configured'           => (bool) $cred,
                'base_url'             => $cred?->base_url ?? '',
                'server_view_state_id' => $cred?->server_view_state_id ?? '',
                'session_id'           => $cred?->session_id ?? '',
                'cookie_set'           => (bool) $cred?->cookie_header,
            ]);
        } catch (DecryptException) {
            // Stale encryption from a rotated APP_KEY — report as not configured
            return response()->json([
                'configured'           => false,
                'base_url'             => '',
                'server_view_state_id' => '',
                'session_id'           => '',
                'cookie_set'           => false,
            ]);
        }
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'base_url'             => ['required', 'url'],
            'session_id'           => ['nullable', 'string'],
            'server_view_state_id' => ['nullable', 'string'],
            'cookie_header'        => ['nullable', 'string'],
        ]);

        // Blank cookie_header means "keep existing" — don't overwrite with null
        if (empty($data['cookie_header'])) {
            unset($data['cookie_header']);
        }

        $values = array_merge($data, [
            'last_request_index' => 0,
            'expires_at'         => now()->addMinutes(15),
        ]);

        try {
            $cred = RepliconCredential::updateOrCreate(
                ['user_id' => auth()->id()],
                $values
            );
        } catch (DecryptException) {
            // getDirty() failed to decrypt a stale field encrypted with a previous APP_KEY.
            // Drop the stale record and create a fresh one.
            RepliconCredential::where('user_id', auth()->id())->delete();
            $cred = RepliconCredential::create($values);
        }

        return response()->json([
            'configured'           => true,
            'base_url'             => $cred->base_url,
            'server_view_state_id' => $cred->server_view_state_id,
            'session_id'           => $cred->session_id,
            'cookie_set'           => (bool) $cred->cookie_header,
        ]);
    }

    public function destroy(): JsonResponse
    {
        RepliconCredential::where('user_id', auth()->id())->update([
            'session_id'           => null,
            'server_view_state_id' => null,
            'cookie_header'        => null,
            'expires_at'           => null,
        ]);

        $cred = RepliconCredential::where('user_id', auth()->id())->first();

        return response()->json([
            'configured'           => (bool) $cred,
            'base_url'             => $cred?->base_url ?? '',
            'server_view_state_id' => '',
            'session_id'           => '',
            'cookie_set'           => false,
        ]);
    }
}
