<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Support\AuditLog;
use Illuminate\Http\JsonResponse;

/**
 * Update profil milik user yang sedang login.
 * NIK dan role tidak bisa diubah dari sini (dikelola admin di menu User).
 */
class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui',
            'data' => $user->only(['id', 'nik', 'name', 'email']),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $request->user()->update(['password' => $request->validated('password')]);

        activity(AuditLog::AUTH)
            ->event('password_changed')
            ->performedOn($request->user())
            ->withProperties(AuditLog::requestContext())
            ->log('Ganti password');

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diganti',
            'data' => null,
        ]);
    }
}
