<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Feature tests for AuthController (Task 5 — Sanctum Authentication).
 *
 * Validates: Requirements 1
 */
class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // POST /api/auth/register
    // -------------------------------------------------------------------------

    public function test_register_creates_visitor_account_and_returns_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'     => 'John Doe',
            'email'    => 'john@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['token', 'user'])
                 ->assertJsonPath('user.email', 'john@example.com')
                 ->assertJsonPath('user.role', 'visitor');

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'role'  => 'visitor',
        ]);
    }

    public function test_register_always_assigns_visitor_role_even_if_admin_supplied(): void
    {
        // Requirement 1.8 — admin role must never be assignable via public register
        $response = $this->postJson('/api/auth/register', [
            'name'     => 'Evil Admin',
            'email'    => 'evil@example.com',
            'password' => 'password123',
            'role'     => 'admin',  // attacker tries to escalate
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'evil@example.com',
            'role'  => 'visitor',   // must remain 'visitor'
        ]);
        $this->assertDatabaseMissing('users', [
            'email' => 'evil@example.com',
            'role'  => 'admin',
        ]);
    }

    public function test_register_returns_422_when_email_is_missing(): void
    {
        $this->postJson('/api/auth/register', [
            'name'     => 'John Doe',
            'password' => 'password123',
        ])->assertStatus(422);
    }

    public function test_register_returns_422_when_password_too_short(): void
    {
        $this->postJson('/api/auth/register', [
            'name'     => 'John Doe',
            'email'    => 'john@example.com',
            'password' => 'short',
        ])->assertStatus(422);
    }

    public function test_register_returns_422_when_email_already_taken(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', [
            'name'     => 'Another',
            'email'    => 'taken@example.com',
            'password' => 'password123',
        ])->assertStatus(422);
    }

    public function test_register_token_expires_in_7_days(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'     => 'Token Test',
            'email'    => 'tokentest@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201);

        $token = PersonalAccessToken::findToken(
            explode('|', $response->json('token'), 2)[1] ?? $response->json('token')
        );

        $this->assertNotNull($token->expires_at);
        $this->assertTrue(
            $token->expires_at->between(now()->addDays(6)->subMinute(), now()->addDays(7)->addMinute()),
            'Token should expire in ~7 days'
        );
    }

    // -------------------------------------------------------------------------
    // POST /api/auth/login
    // -------------------------------------------------------------------------

    public function test_login_returns_token_and_user_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email'    => 'user@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'secret123',
        ]);

        $response->assertOk()
                 ->assertJsonStructure(['token', 'user'])
                 ->assertJsonPath('user.id', $user->id);
    }

    public function test_login_returns_401_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email'    => 'user@example.com',
            'password' => bcrypt('correct_password'),
        ]);

        $this->postJson('/api/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'wrong_password',
        ])->assertStatus(401);
    }

    public function test_login_token_expires_in_7_days(): void
    {
        User::factory()->create([
            'email'    => 'logintest@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'logintest@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk();

        $token = PersonalAccessToken::findToken(
            explode('|', $response->json('token'), 2)[1] ?? $response->json('token')
        );

        $this->assertNotNull($token->expires_at);
        $this->assertTrue(
            $token->expires_at->between(now()->addDays(6)->subMinute(), now()->addDays(7)->addMinute()),
            'Token should expire in ~7 days'
        );
    }

    // -------------------------------------------------------------------------
    // POST /api/auth/logout
    // -------------------------------------------------------------------------

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $tokenObj = $user->createToken('auth-token');

        $response = $this->withToken($tokenObj->plainTextToken)
                         ->postJson('/api/auth/logout');

        $response->assertOk();

        // Token must no longer exist in the database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenObj->accessToken->id,
        ]);
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/auth/logout')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // GET /api/auth/me
    // -------------------------------------------------------------------------

    public function test_me_returns_authenticated_user_with_relations(): void
    {
        $user = User::factory()->create(['role' => 'visitor']);

        $response = $this->actingAs($user, 'sanctum')
                         ->getJson('/api/auth/me');

        $response->assertOk()
                 ->assertJsonPath('id', $user->id)
                 ->assertJsonPath('role', 'visitor')
                 ->assertJsonStructure(['id', 'name', 'email', 'role', 'liked_posts', 'favorite_posts']);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Password storage (bcrypt)
    // -------------------------------------------------------------------------

    public function test_password_is_stored_hashed(): void
    {
        $this->postJson('/api/auth/register', [
            'name'     => 'Hash Test',
            'email'    => 'hash@example.com',
            'password' => 'plaintext_password',
        ]);

        $user = User::where('email', 'hash@example.com')->first();

        $this->assertNotEquals('plaintext_password', $user->password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('plaintext_password', $user->password));
    }
}
