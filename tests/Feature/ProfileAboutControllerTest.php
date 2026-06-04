<?php

namespace Tests\Feature;

use App\Models\AboutPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature tests for ProfileController and AboutController.
 *
 * Validates: Requirements 8, 9
 */
class ProfileAboutControllerTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // ProfileController — GET /api/profile
    // =========================================================================

    /**
     * Requirement 8.1 — Returns authenticated user's profile.
     */
    public function test_show_returns_authenticated_user_profile(): void
    {
        $user = User::factory()->create(['bio' => 'Hello world']);

        $response = $this->actingAs($user)->getJson('/api/profile');

        $response->assertOk()
            ->assertJsonFragment(['id' => $user->id, 'bio' => 'Hello world']);
    }

    /**
     * Requirement 8.1 — Returns 401 when not authenticated.
     */
    public function test_show_returns_401_without_auth(): void
    {
        $this->getJson('/api/profile')->assertUnauthorized();
    }

    // =========================================================================
    // ProfileController — PUT /api/profile
    // =========================================================================

    /**
     * Requirement 8.2 — Updates the user profile fields.
     */
    public function test_update_updates_profile_fields(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($user)->putJson('/api/profile', [
            'name' => 'New Name',
            'bio'  => 'A short bio',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'New Name', 'bio' => 'A short bio']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    /**
     * Requirement 8.2 — Password is updated when provided with confirmation.
     */
    public function test_update_changes_password_when_provided(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/profile', [
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertOk();
        // Verify the new password hashes correctly
        $user->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('newpassword123', $user->password));
    }

    /**
     * Requirement 8.2 — Returns 422 when password confirmation doesn't match.
     */
    public function test_update_returns_422_when_password_mismatch(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/profile', [
            'password'              => 'newpassword123',
            'password_confirmation' => 'wrongpassword',
        ])->assertUnprocessable();
    }

    /**
     * Requirement 8.2 — Returns 401 without auth.
     */
    public function test_update_returns_401_without_auth(): void
    {
        $this->putJson('/api/profile', ['name' => 'X'])->assertUnauthorized();
    }

    // =========================================================================
    // ProfileController — POST /api/profile/avatar
    // =========================================================================

    /**
     * Requirement 8.3 — Stores avatar locally and updates user avatar field.
     */
    public function test_avatar_stores_image_locally_and_updates_user(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $response = $this->actingAs($user)->postJson('/api/profile/avatar', [
            'avatar' => $file,
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('avatar'));

        // The avatar URL should be present in the DB
        $user->refresh();
        $this->assertNotNull($user->avatar);
    }

    /**
     * Requirement 8.6 — Returns 422 when file exceeds 5 MB.
     */
    public function test_avatar_returns_422_when_file_too_large(): void
    {
        $user = User::factory()->create();
        // Create a fake file larger than 5 MB (5 * 1024 + 1 = 5121 KB)
        $file = UploadedFile::fake()->create('big.jpg', 5121, 'image/jpeg');

        $this->actingAs($user)->postJson('/api/profile/avatar', [
            'avatar' => $file,
        ])->assertUnprocessable();
    }

    /**
     * Requirement 8.6 — Returns 422 when file is not an image.
     */
    public function test_avatar_returns_422_when_not_an_image(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->actingAs($user)->postJson('/api/profile/avatar', [
            'avatar' => $file,
        ])->assertUnprocessable();
    }

    /**
     * Requirement 8.3 — Returns 401 without auth.
     */
    public function test_avatar_returns_401_without_auth(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg');
        $this->postJson('/api/profile/avatar', ['avatar' => $file])->assertUnauthorized();
    }

    // =========================================================================
    // AboutController — GET /api/about
    // =========================================================================

    /**
     * Requirement 9.1 — Public endpoint returns about page content.
     */
    public function test_about_show_returns_about_page_content(): void
    {
        AboutPage::create([
            'bio'        => 'I am a developer',
            'links'      => ['github' => 'https://github.com/example'],
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/about');

        $response->assertOk()
            ->assertJsonFragment(['bio' => 'I am a developer']);
    }

    /**
     * Requirement 9.1 — Returns empty structure when no about page exists yet.
     */
    public function test_about_show_returns_null_fields_when_empty(): void
    {
        $response = $this->getJson('/api/about');

        $response->assertOk()
            ->assertJsonFragment(['bio' => null]);
    }

    /**
     * Requirement 9.1 — Public endpoint needs no authentication.
     */
    public function test_about_show_is_publicly_accessible(): void
    {
        $this->getJson('/api/about')->assertOk();
    }

    // =========================================================================
    // AboutController — PUT /api/about  [admin only]
    // =========================================================================

    /**
     * Requirement 9.2, 9.3 — Creates the about page if it doesn't exist.
     */
    public function test_about_update_creates_entry_when_none_exists(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->putJson('/api/admin/about', [
            'bio'   => 'Hello from admin',
            'links' => ['twitter' => 'https://twitter.com/example'],
        ]);

        $response->assertOk()
            ->assertJsonFragment(['bio' => 'Hello from admin']);

        $this->assertDatabaseCount('about_page', 1);
    }

    /**
     * Property 4 — Only one row in about_page table regardless of how many PUT calls.
     */
    public function test_about_update_always_results_in_single_row(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->putJson('/api/admin/about', ['bio' => 'First']);
        $this->actingAs($admin)->putJson('/api/admin/about', ['bio' => 'Second']);
        $this->actingAs($admin)->putJson('/api/admin/about', ['bio' => 'Third']);

        $this->assertDatabaseCount('about_page', 1);
        $this->assertDatabaseHas('about_page', ['bio' => 'Third']);
    }

    /**
     * Requirement 9.2 — Admin can update existing about page.
     */
    public function test_about_update_updates_existing_entry(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        AboutPage::create(['bio' => 'Old bio', 'updated_at' => now()]);

        $response = $this->actingAs($admin)->putJson('/api/admin/about', [
            'bio' => 'Updated bio',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['bio' => 'Updated bio']);

        $this->assertDatabaseCount('about_page', 1);
    }

    /**
     * Requirement 9.2 — Returns 403 when visitor tries to update about page.
     */
    public function test_about_update_returns_403_for_visitor(): void
    {
        $visitor = User::factory()->create(['role' => 'visitor']);

        $this->actingAs($visitor)->putJson('/api/admin/about', [
            'bio' => 'Unauthorized',
        ])->assertForbidden();
    }

    /**
     * Requirement 9.2 — Returns 401 when unauthenticated.
     */
    public function test_about_update_returns_401_without_auth(): void
    {
        $this->putJson('/api/admin/about', ['bio' => 'Unauthorized'])->assertUnauthorized();
    }
}
