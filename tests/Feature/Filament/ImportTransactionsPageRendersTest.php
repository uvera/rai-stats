<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportTransactionsPageRendersTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_renders_for_an_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/import-transactions');

        $response->assertOk();
        $response->assertSee('Log in');
    }

    public function test_it_redirects_guests_to_login(): void
    {
        $response = $this->get('/admin/import-transactions');

        $response->assertRedirect('/admin/login');
    }
}
