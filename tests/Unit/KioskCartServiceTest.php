<?php

namespace Tests\Unit;

use App\Kiosk\Circulation\ReturnAction;
use App\Services\KioskCartService;
use App\Services\KohaService;
use Tests\TestCase;

class KioskCartServiceTest extends TestCase
{
    private function item(string $barcode): array
    {
        return ['item_id' => 1, 'barcode' => $barcode, 'title' => 'Book', 'author' => null, 'due' => null];
    }

    public function test_adds_removes_and_detects_duplicates(): void
    {
        $cart = new KioskCartService();
        $cart->add('borrow', $this->item('BOOK001'));

        $this->assertTrue($cart->has('borrow', 'book001'));    // case-insensitive
        $this->assertFalse($cart->has('return', 'BOOK001'));   // borrow and return carts are separate

        $cart->remove('borrow', 'BOOK001');
        $this->assertSame([], $cart->items('borrow'));
    }

    public function test_borrow_limit_depends_on_category_and_current_loans(): void
    {
        config(['kiosk.categories' => ['UG' => ['label' => 'Undergraduate', 'max_books' => 3]]]);
        $cart = new KioskCartService();
        $patron = ['category_code' => 'UG', 'borrowed_books' => [['barcode' => 'X1']]];

        $this->assertSame(2, $cart->limit('borrow', $patron));
        $this->assertNull($cart->limit('return', $patron));

        $cart->add('borrow', $this->item('A'));
        $this->assertFalse($cart->isFull('borrow', $patron));

        $cart->add('borrow', $this->item('B'));
        $this->assertTrue($cart->isFull('borrow', $patron));
    }

    public function test_unknown_category_uses_the_strict_default(): void
    {
        config(['kiosk.default_category' => ['label' => 'Patron', 'max_books' => 3]]);
        $cart = new KioskCartService();

        $this->assertSame(3, $cart->limit('borrow', ['category_code' => 'ZZ', 'borrowed_books' => []]));
    }

    public function test_confirm_token_works_only_once(): void
    {
        $cart = new KioskCartService();
        $cart->add('borrow', $this->item('BOOK001'));
        $token = $cart->token('borrow');

        $this->assertNull($cart->consume('borrow', 'wrong-token'));
        $this->assertCount(1, $cart->consume('borrow', $token));
        $this->assertNull($cart->consume('borrow', $token));    // replay is rejected
    }

    public function test_return_action_only_accepts_books_on_the_patrons_account(): void
    {
        $action = new ReturnAction($this->createMock(KohaService::class));
        $patron = ['borrowed_books' => [['barcode' => 'BOOK001', 'raw' => ['item_id' => 7]]]];

        $this->assertNull($action->validate(['barcode' => 'BOOK001', 'item_id' => 7], $patron));
        $this->assertNotNull($action->validate(['barcode' => 'BOOK009', 'item_id' => 9], $patron));
    }
}