<?php

namespace Tests\Unit;

use App\Kiosk\Circulation\BorrowAction;
use App\Kiosk\Circulation\ReturnAction;
use App\Services\KohaService;
use Tests\TestCase;

class CirculationActionTest extends TestCase
{
    public function test_borrow_action_validation_fails_for_restricted_patron(): void
    {
        $action = new BorrowAction($this->createMock(KohaService::class));

        $patron = ['patron_id' => 10, 'restricted' => true];
        $item = ['item_id' => 100, 'barcode' => 'BOOK001'];

        $this->assertEquals(
            'Your account is restricted. Please contact the library desk.',
            $action->validate($item, $patron)
        );
    }

    public function test_borrow_action_validation_fails_for_unavailable_item(): void
    {
        $action = new BorrowAction($this->createMock(KohaService::class));
        $patron = ['patron_id' => 10, 'restricted' => false];

        // Item already checked out
        $this->assertEquals('This book is currently unavailable.', $action->validate(['item_id' => 1, 'checked_out' => true], $patron));

        // Item marked not for loan
        $this->assertEquals('This book is currently unavailable.', $action->validate(['item_id' => 1, 'notforloan' => 1], $patron));

        // Item lost
        $this->assertEquals('This book is currently unavailable.', $action->validate(['item_id' => 1, 'lost_status' => 1], $patron));

        // Item withdrawn
        $this->assertEquals('This book is currently unavailable.', $action->validate(['item_id' => 1, 'withdrawn' => 1], $patron));

        // Available item returns null
        $this->assertNull($action->validate(['item_id' => 1, 'barcode' => 'BOOK001'], $patron));
    }

    public function test_borrow_action_commit_calls_koha(): void
    {
        $mockKoha = $this->createMock(KohaService::class);
        $mockKoha->expects($this->once())
            ->method('checkoutItem')
            ->with(10, 100)
            ->willReturn(['checkout_id' => 55, 'due_date' => '2026-12-01']);

        $action = new BorrowAction($mockKoha);
        $result = $action->commit(['patron_id' => 10], ['item_id' => 100]);

        $this->assertEquals(55, $result['checkout_id']);
        $this->assertEquals('Book borrowed successfully.', $action->successMessage());
        $this->assertEquals('Unable to borrow this book. Please try again.', $action->failureMessage());
    }

    public function test_return_action_validation(): void
    {
        $action = new ReturnAction($this->createMock(KohaService::class));

        $patron = [
            'patron_id' => 10,
            'borrowed_books' => [
                ['barcode' => 'BOOK100', 'raw' => ['item_id' => 50]],
            ],
        ];

        // Matching barcode
        $this->assertNull($action->validate(['barcode' => 'book100', 'item_id' => 50], $patron));

        // Matching item_id even if barcode is different
        $this->assertNull($action->validate(['barcode' => 'OTHER', 'item_id' => 50], $patron));

        // Unborrowed item
        $this->assertEquals(
            'This book is not currently borrowed on your account.',
            $action->validate(['barcode' => 'NOT_MINE', 'item_id' => 999], $patron)
        );
    }

    public function test_return_action_commit_calls_koha(): void
    {
        $mockKoha = $this->createMock(KohaService::class);
        $mockKoha->expects($this->once())
            ->method('checkinItem')
            ->with(50, 'BOOK100')
            ->willReturn(['item_id' => 50, 'status' => 'returned']);

        $action = new ReturnAction($mockKoha);
        $result = $action->commit(['patron_id' => 10], ['item_id' => 50, 'barcode' => 'BOOK100']);

        $this->assertEquals('returned', $result['status']);
        $this->assertEquals('Book returned successfully.', $action->successMessage());
        $this->assertEquals('This book could not be returned. Please ask a librarian.', $action->failureMessage());
    }
}
