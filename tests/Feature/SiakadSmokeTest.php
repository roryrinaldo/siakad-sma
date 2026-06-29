<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiakadSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_and_open_core_pages(): void
    {
        $this->withoutVite();
        $this->seed();

        $response = $this->post('/login', [
            'email' => 'admin@sia.test',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        foreach (['/dashboard', '/students', '/teachers', '/school-classes', '/subjects', '/schedules', '/reports'] as $uri) {
            $this->get($uri)->assertOk();
        }

        $this->get('/reports/students/csv')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->get('/reports/students/pdf')->assertOk();
    }
}
