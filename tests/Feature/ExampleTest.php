<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_admin_shell_is_publicly_rendered_without_redirecting_to_login(): void
    {
        $response = $this->get('/admin');

        $response
            ->assertStatus(200)
            ->assertSee('data-surface="admin"', false);
    }

    public function test_login_route_persists_safe_next_destination(): void
    {
        $response = $this->get('/auth/login?next=/admin');

        $response->assertRedirect();
        $this->assertSame('/admin', session('url.intended'));
    }

    public function test_login_route_ignores_unsafe_next_destination(): void
    {
        $this->get('/auth/login?next=https://evil.example.test')->assertRedirect();

        $this->assertNull(session('url.intended'));
    }
}
