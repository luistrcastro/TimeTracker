<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserProfileController extends Controller
{
    public function updateProfile(Request $request): UserResource
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->update($data);

        return new UserResource($request->user()->fresh());
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required'],
            'password'         => ['required', 'confirmed', Password::defaults()],
        ]);

        if (! Hash::check($request->current_password, $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $request->user()->update([
            'password' => $request->password,
        ]);

        return response()->json(['message' => 'Password updated.']);
    }

    public function uploadAvatar(Request $request): UserResource
    {
        $request->validate([
            'avatar' => ['required', 'file', 'image', 'max:512'],
        ]);

        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk()->delete($user->avatar_path);
        }

        $ext  = $request->file('avatar')->getClientOriginalExtension();
        $path = 'avatars/' . $user->id . '/' . Str::uuid() . '.' . $ext;
        Storage::disk()->put($path, file_get_contents($request->file('avatar')->getRealPath()));

        $user->update(['avatar_path' => $path]);

        return new UserResource($user->fresh());
    }

    public function deleteAvatar(Request $request): UserResource
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk()->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return new UserResource($user->fresh());
    }
}
