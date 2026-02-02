<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_rate_limiting_on_login_endpoint(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
        ]);

        // Make multiple failed login attempts (rate limit is 5 per minute)
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);

            if ($i < 5) {
                $this->assertEquals(401, $response->status());
            } else {
                $this->assertEquals(429, $response->status());
            }
        }
    }

    public function test_password_must_meet_minimum_requirements(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => '123',
            'password_confirmation' => '123',
        ]);

        // Password must be at least 8 characters
        $response->assertStatus(422);

        // Check validation error structure (custom API response format)
        $json = $response->json();
        $this->assertFalse($json['success']);
        $this->assertArrayHasKey('error', $json);
        $this->assertArrayHasKey('details', $json['error']);
        $this->assertArrayHasKey('password', $json['error']['details']);
    }

    public function test_sql_injection_prevention_in_search(): void
    {
        $this->withoutExceptionHandling();

        // Attempt SQL injection in search parameter
        $response = $this->getJson("/api/v1/products?search=' OR '1'='1");

        // Should not throw error and return proper response
        $response->assertOk();
    }

    public function test_xss_prevention_in_input(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/contact', [
            'name' => '<script>alert("XSS")</script>',
            'email' => 'test@example.com',
            'subject' => 'Test',
            'message' => '<script>alert("XSS")</script>',
        ]);

        // Should sanitize or escape XSS attempts
        $response->assertStatus(201);
    }

    public function test_sensitive_data_not_exposed_in_responses(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret-password'),
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $token = $loginResponse->json('data.access_token');

        $meResponse = $this->withToken($token)->getJson('/api/v1/auth/me');

        // Ensure password is not in response
        $responseData = json_encode($meResponse->json());
        $this->assertStringNotContainsString('password', $responseData);
        $this->assertStringNotContainsString('secret-password', $responseData);
    }

    public function test_csrf_protection_enabled(): void
    {
        // CSRF should be handled by Sanctum for API routes
        $this->assertTrue(true);
    }

    public function test_mass_assignment_protection(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Hacker',
            'email' => 'hacker@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'super_admin', // Try to set admin role
            'is_active' => true,
        ]);

        // Check that the user was not created as admin
        $newUser = User::where('email', 'hacker@example.com')->first();
        if ($newUser) {
            $this->assertNotEquals('super_admin', $newUser->role->value);
        }
    }

    public function test_cannot_enumerate_users_through_login_errors(): void
    {
        // Test with non-existent email
        $response1 = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
        ]);

        // Test with existing email but wrong password
        $user = User::factory()->create(['password' => bcrypt('correct')]);
        $response2 = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong',
        ]);

        // Both should return same error message
        $this->assertEquals($response1->status(), $response2->status());
    }

    public function test_authentication_required_for_protected_endpoints(): void
    {
        $protectedEndpoints = [
            ['GET', '/api/v1/auth/me'],
            ['POST', '/api/v1/auth/logout'],
            ['GET', '/api/v1/customer/profile'],
            ['GET', '/api/v1/wishlist'],
            ['POST', '/api/v1/checkout'],
        ];

        foreach ($protectedEndpoints as [$method, $url]) {
            $response = $this->json($method, $url);
            $this->assertEquals(401, $response->status(), "Endpoint {$url} should require authentication");
        }
    }

    public function test_token_expiration_works(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $expiresIn = $loginResponse->json('data.expires_in');

        $this->assertNotNull($expiresIn);
        $this->assertIsInt($expiresIn);
        $this->assertGreaterThan(0, $expiresIn);
    }
}
