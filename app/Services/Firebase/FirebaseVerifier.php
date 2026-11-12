<?php

namespace App\Services\Firebase;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class FirebaseVerifier
{
    protected const CERT_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

    protected static ?object $mockPayload = null;

    /**
     * Set a mock payload for testing.
     */
    public static function fake(?object $payload = null): void
    {
        static::$mockPayload = $payload ?? (object) [
            'uid' => 'firebase-test-uid-12345',
            'email' => 'user@example.com',
            'name' => 'Firebase Test User',
            'picture' => 'https://example.com/avatar.jpg',
            'email_verified' => true,
        ];
    }

    /**
     * Clear mock payload.
     */
    public static function resetFake(): void
    {
        static::$mockPayload = null;
    }

    /**
     * Verify a Firebase ID Token.
     *
     * @throws InvalidArgumentException|RuntimeException
     */
    public function verify(string $idToken): object
    {
        if (static::$mockPayload !== null) {
            return static::$mockPayload;
        }

        $projectId = config('services.firebase.project_id');
        if (empty($projectId)) {
            throw new RuntimeException('FIREBASE_PROJECT_ID belum dikonfigurasi di file .env');
        }

        $publicKeys = $this->getPublicKeys();
        if (empty($publicKeys)) {
            throw new RuntimeException('Gagal mengambil kunci publik sertifikat Google Firebase.');
        }

        $keyObjects = [];
        foreach ($publicKeys as $kid => $cert) {
            $keyObjects[$kid] = new Key($cert, 'RS256');
        }

        try {
            $decoded = JWT::decode($idToken, $keyObjects);
        } catch (Throwable $e) {
            throw new InvalidArgumentException('Token Firebase tidak valid atau telah kedaluwarsa: ' . $e->getMessage(), 0, $e);
        }

        // Validasi audience & issuer
        if (($decoded->aud ?? '') !== $projectId) {
            throw new InvalidArgumentException('Audience token Firebase (' . ($decoded->aud ?? '') . ') tidak cocok dengan Project ID: ' . $projectId);
        }

        $expectedIssuer = 'https://securetoken.google.com/' . $projectId;
        if (($decoded->iss ?? '') !== $expectedIssuer) {
            throw new InvalidArgumentException('Issuer token Firebase tidak valid.');
        }

        if (empty($decoded->sub)) {
            throw new InvalidArgumentException('Subject (UID) token Firebase kosong.');
        }

        return (object) [
            'uid' => (string) $decoded->sub,
            'email' => $decoded->email ?? null,
            'name' => $decoded->name ?? null,
            'picture' => $decoded->picture ?? null,
            'email_verified' => (bool) ($decoded->email_verified ?? false),
        ];
    }

    /**
     * Get Google's public x509 certificates for Firebase.
     */
    protected function getPublicKeys(): array
    {
        return Cache::remember('firebase_public_certs', 3600 * 6, function () {
            $verifySsl = config('services.firebase.curl_verify', file_exists('D:/laragon/etc/ssl/cacert.pem') ? 'D:/laragon/etc/ssl/cacert.pem' : true);

            $response = Http::withOptions(['verify' => $verifySsl])
                ->timeout(10)
                ->get(self::CERT_URL);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            return [];
        });
    }
}
