<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Spatie\Permission\Models\Role;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirectToGoogle(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Obtain the user information from Google and authenticate.
     */
    public function handleGoogleCallback(): RedirectResponse
    {
        try {
            /** @var \Laravel\Socialite\Two\User $googleUser */
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException $e) {
            try {
                $googleUser = Socialite::driver('google')->stateless()->user();
            } catch (Throwable $statelessError) {
                Log::error('Google authentication stateless fallback failed: ' . $statelessError->getMessage());

                Notification::make()
                    ->title('Gagal masuk dengan Google')
                    ->body('Sesi login telah kedaluwarsa. Silakan coba kembali.')
                    ->danger()
                    ->send();

                return redirect('/admin/login');
            }
        } catch (Throwable $e) {
            Log::error('Google authentication failed: ' . $e->getMessage(), [
                'exception' => get_class($e),
            ]);

            Notification::make()
                ->title('Gagal masuk dengan Google')
                ->body($e->getMessage() ?: 'Terjadi kesalahan saat memverifikasi akun Google.')
                ->danger()
                ->send();

            return redirect('/admin/login');
        }

        if (! $googleUser || ! $googleUser->getEmail()) {
            Notification::make()
                ->title('Gagal masuk dengan Google')
                ->body('Akun Google tidak menyediakan alamat email valid.')
                ->danger()
                ->send();

            return redirect('/admin/login');
        }

        // Cari pengguna berdasarkan google_id atau email
        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if ($user) {
            // Update data Google jika belum ada atau diperbarui
            $user->update([
                'google_id' => $user->google_id ?? $googleUser->getId(),
                'avatar' => $googleUser->getAvatar() ?? $user->avatar,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
        } else {
            // Registrasi akun baru
            $user = User::create([
                'name' => $googleUser->getName() ?? $googleUser->getNickname() ?? 'Google User',
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'email_verified_at' => now(),
                'password' => null,
            ]);

            // Jika sistem menggunakan Spatie Role, berikan role admin untuk pengguna pertama
            if (class_exists(Role::class)) {
                try {
                    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
                    if (User::count() === 1 || ! $user->hasRole('admin')) {
                        $user->assignRole($adminRole);
                    }
                } catch (Throwable $roleError) {
                    Log::warning('Gagal memberikan role admin: ' . $roleError->getMessage());
                }
            }
        }

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        Notification::make()
            ->title('Berhasil Masuk')
            ->body('Selamat datang kembali, ' . $user->name)
            ->success()
            ->send();

        $adminPanel = Filament::getPanel('admin');
        if ($adminPanel && $user->canAccessPanel($adminPanel)) {
            return redirect()->intended($adminPanel->getUrl());
        }

        return redirect()->intended('/');
    }
}
