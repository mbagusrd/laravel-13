<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Firebase\FirebaseVerifier;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Throwable;

class FirebaseAuthController extends Controller
{
    /**
     * Verify Firebase ID Token and log in / register the user.
     */
    public function verifyToken(Request $request, FirebaseVerifier $verifier): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => 'required|string',
        ]);

        try {
            $payload = $verifier->verify($validated['id_token']);
        } catch (Throwable $e) {
            Log::error('Firebase Token Verification Failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Verifikasi autentikasi Firebase gagal: ' . $e->getMessage(),
            ], 401);
        }

        if (empty($payload->email)) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Firebase/Google tidak menyertakan alamat email valid.',
            ], 422);
        }

        // Cari pengguna berdasarkan firebase_uid atau email
        $user = User::where('firebase_uid', $payload->uid)
            ->orWhere('email', $payload->email)
            ->first();

        if ($user) {
            $user->update([
                'firebase_uid' => $user->firebase_uid ?? $payload->uid,
                'avatar' => $payload->picture ?? $user->avatar,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
        } else {
            $user = User::create([
                'name' => $payload->name ?? 'Google User',
                'email' => $payload->email,
                'firebase_uid' => $payload->uid,
                'avatar' => $payload->picture ?? null,
                'email_verified_at' => now(),
                'password' => null,
            ]);

            // Jika menggunakan Spatie Roles, jadikan admin untuk user pertama
            if (class_exists(Role::class)) {
                try {
                    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
                    if (User::count() === 1) {
                        $user->assignRole($adminRole);
                    }
                } catch (Throwable $roleError) {
                    Log::warning('Gagal memberikan role admin: ' . $roleError->getMessage());
                }
            }
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        Notification::make()
            ->title('Berhasil Masuk dengan Firebase')
            ->body('Selamat datang kembali, ' . $user->name)
            ->success()
            ->send();

        $redirectUrl = '/';
        $adminPanel = Filament::getPanel('admin');
        if ($adminPanel && $user->canAccessPanel($adminPanel)) {
            $redirectUrl = $adminPanel->getUrl();
        }

        return response()->json([
            'success' => true,
            'message' => 'Autentikasi berhasil.',
            'redirect_url' => $redirectUrl,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
