<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for AdminMiddleware (Task 6).
 *
 * Verifies that admin routes are correctly protected:
 *  - 401 when no token is provided
 *  - 403 when a visitor token is provided
 *  - 200 when a valid admin token is provided
 *
 * Validates: Requirements 20
 */
class AdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A representative admin-only route used for all middleware tests.
     * GET /api/admin/posts — requires auth:sanctum + admin middleware.
     */
    private string $adminRoute = '/api/admin/posts';

    /**
     * Requirement 20.3 — Missing token on a protected route → HTTP 401.
     */
    public function test_admin_route_returns_401_when_no_token_provided(): void
    {
        $response = $this->getJson($this->adminRoute);

        $response->assertStatus(401);
    }

    /**
     * Requirement 20.1 — Visitor token on an admin route → HTTP 403.
     */
    public function test_admin_route_returns_403_when_visitor_token_provided(): void
    {
        $visitor = User::factory()->create(['role' => 'visitor']);

        $response = $this->actingAs($visitor, 'sanctum')
                         ->getJson($this->adminRoute);

        $response->assertStatus(403)
                 ->assertJsonFragment(['message' => 'Forbidden']);
    }

    /**
     * Requirement 20.1 — Valid admin token on an admin route → HTTP 200.
     */
    public function test_admin_route_returns_200_when_admin_token_provided(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
                         ->getJson($this->adminRoute);

        $response->assertStatus(200);
    }

    /**
     * Extra: ensure an expired / invalid raw token string returns 401.
     */
    public function test_admin_route_returns_401_when_invalid_token_provided(): void
    {
        $response = $this->withToken('invalid-token-string')
                         ->getJson($this->adminRoute);

        $response->assertStatus(401);
    }
}
