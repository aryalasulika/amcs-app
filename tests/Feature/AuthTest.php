<?php

namespace Tests\Feature;

use Tests\AmcsTestCase;

class AuthTest extends AmcsTestCase
{
    public function test_login_page_has_clear_placeholders_without_demo_credentials(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertSee('Masukkan email Anda')
            ->assertSee('Masukkan kata sandi Anda')
            ->assertDontSee('Akun contoh hasil seeder')
            ->assertDontSee('Amcs#2026');
    }

    public function test_user_can_login_and_receive_token(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'operator@amcs.local',
            'password' => 'Amcs#2026',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'user' => ['id', 'name', 'email', 'roles'], 'token']);

        $this->assertNotEmpty($response->json('token'));
        $this->assertContains('amc_operator', $response->json('user.roles'));
    }

    public function test_user_cannot_login_with_wrong_password(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'operator@amcs.local',
            'password' => 'salah-total',
        ])->assertStatus(422);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = $this->user('operator@amcs.local');
        $user->update(['active' => false]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'operator@amcs.local',
            'password' => 'Amcs#2026',
        ])->assertStatus(403);
    }

    public function test_guests_cannot_read_operational_data(): void
    {
        $this->getJson('/api/v1/stands')->assertStatus(401);
        $this->getJson('/api/v1/movements')->assertStatus(401);
    }

    public function test_inactive_user_session_is_rejected(): void
    {
        $user = $this->actingAsRole('operator@amcs.local');
        $user->update(['active' => false]);

        $this->getJson('/api/v1/stands')->assertStatus(403);
    }

    public function test_me_endpoint_returns_authenticated_user(): void
    {
        $this->actingAsRole('operator@amcs.local');

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'operator@amcs.local');
    }
}
