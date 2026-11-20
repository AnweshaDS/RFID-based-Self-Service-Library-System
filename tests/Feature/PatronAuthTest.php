<?php

namespace Tests\Feature;

use Tests\TestCase;

class PatronAuthTest extends TestCase
{
    public function test_patron_login_page_renders_successfully(): void
    {
        $response = $this->get(route('patron.login'));

        $response->assertOk();
        $response->assertViewIs('patron.login');
    }

    public function test_patron_login_requires_cardnumber(): void
    {
        $response = $this->post(route('patron.login.submit'), []);

        $response->assertSessionHasErrors('cardnumber');
    }

    public function test_patron_login_returns_not_implemented_error(): void
    {
        $response = $this->post(route('patron.login.submit'), [
            'cardnumber' => 'STU001',
        ]);

        $response->assertSessionHasErrors([
            'cardnumber' => 'Patron login is not yet implemented.',
        ]);
    }
}
