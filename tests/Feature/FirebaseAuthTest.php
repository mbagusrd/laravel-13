<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Firebase\FirebaseVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class FirebaseAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        FirebaseVerifier::resetFake();
        parent::tearDown();
    }

    public function test_filament_login_page_renders_google_button(): void
    {
        $response = $this->get(route('filament.admin.auth.login'));

        $response->assertStatus(200);
        $response->assertSee('Masuk dengan Google');
        $response->assertSee('firebase-google-login-btn');
    }

    public function test_requires_id_token(): void
    {
        $response = $this->postJson(route('auth.firebase.verify'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['id_token']);
    }

    public function test_registers_and_authenticates_new_user_via_firebase(): void
    {
        FirebaseVerifier::fake((object) [
            'uid' => 'firebase-uid-new-123',
            'email' => 'bagus.firebase@example.com',
            'name' => 'Bagus Firebase',
            'picture' => 'https://lh3.googleusercontent.com/test-avatar.jpg',
            'email_verified' => true,
        ]);

        $response = $this->postJson(route('auth.firebase.verify'), [
            'id_token' => 'mocked-jwt-token-string',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Autentikasi berhasil.',
        ]);

        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'email' => 'bagus.firebase@example.com',
            'firebase_uid' => 'firebase-uid-new-123',
            'name' => 'Bagus Firebase',
        ]);

        $user = User::where('email', 'bagus.firebase@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_logs_in_existing_user_and_links_firebase_uid(): void
    {
        $user = User::factory()->create([
            'email' => 'existing.user@example.com',
            'name' => 'Existing User',
            'firebase_uid' => null,
        ]);

        FirebaseVerifier::fake((object) [
            'uid' => 'firebase-uid-linked-999',
            'email' => 'existing.user@example.com',
            'name' => 'Existing User Updated',
            'picture' => 'https://example.com/new-avatar.png',
            'email_verified' => true,
        ]);

        $response = $this->postJson(route('auth.firebase.verify'), [
            'id_token' => 'mocked-jwt-token-string',
        ]);

        $response->assertStatus(200);
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertEquals('firebase-uid-linked-999', $user->firebase_uid);
    }

    public function test_handles_verification_failure(): void
    {
        FirebaseVerifier::resetFake();

        // Menguji token tidak valid tanpa mock
        $response = $this->postJson(route('auth.firebase.verify'), [
            'id_token' => 'invalid-malformed-token',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
        ]);
        $this->assertGuest();
    }
}
