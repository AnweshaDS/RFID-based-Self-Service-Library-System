<?php

namespace App\Http\Controllers;

use App\Kiosk\Circulation\BorrowAction;
use App\Kiosk\Circulation\CirculationAction;
use App\Kiosk\Circulation\ReturnAction;
use App\Services\ActivityLogService;
use App\Services\KioskCartService;
use App\Services\KohaService;
use App\Support\CheckoutFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use App\Support\PatronCategory;

class KioskCartController extends Controller
{
    public function __construct(
        private KioskCartService $cart,
        private KohaService $koha,
        private ActivityLogService $activity,
    ) {}

    public function show(string $mode)
    {
        if (! $patron = Session::get('kiosk_patron')) {
            return $this->needsLogin();
        }

                return view('kiosk.cart', [
            'mode' => $mode,
            'patron' => $patron,
            'items' => $this->cart->items($mode),
            'token' => $this->cart->token($mode),
            'category' => PatronCategory::for($patron['category_code'] ?? null),
            'onLoan' => count($patron['borrowed_books'] ?? []),
            'limit' => $this->cart->limit($mode, $patron),
        ]);
    }

    public function add(Request $request, string $mode)
    {
        if (! $patron = Session::get('kiosk_patron')) {
            return $this->needsLogin();
        }

        $request->validate([
            'barcode' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._\-]+$/'],
        ]);

        $barcode = trim($request->input('barcode'));
        $patronId = (string) ($patron['patron_id'] ?? '');

        if ($this->cart->has($mode, $barcode)) {
                        return $this->reject($mode, 'This book is already in your ' . ($mode === 'borrow' ? 'borrow list.' : 'return list.'));
        }

                if ($this->cart->isFull($mode, $patron)) {
            return $this->reject($mode, $this->limitMessage($patron));
        }

        try {
            $item = $this->koha->getItemByBarcode($barcode);
        } catch (\Throwable $e) {
            return $this->fail($mode, $patronId, $barcode, null, 'Unable to retrieve book information. Please try again.');
        }

        if (! $item) {
            return $this->fail($mode, $patronId, $barcode, null, 'Book not found.');
        }

        $error = $this->action($mode)->validate($item, $patron);

        if ($error) {
            return $this->fail($mode, $patronId, $barcode, (int) ($item['item_id'] ?? 0), $error);
        }

        $entry = $this->summarize($item, $patron, $barcode);
        $this->cart->add($mode, $entry);

                return redirect()->route("kiosk.$mode")->with(
            'status',
            ($mode === 'borrow' ? 'Added to borrow list: ' : 'Added to return list: ') . $entry['title']
        );
    }

    public function remove(Request $request, string $mode)
    {
        if (! Session::has('kiosk_patron')) {
            return $this->needsLogin();
        }

        $request->validate(['barcode' => ['required', 'string', 'max:64']]);
        $this->cart->remove($mode, $request->input('barcode'));

        return redirect()->route("kiosk.$mode");
    }

    public function clear(string $mode)
    {
        if (! Session::has('kiosk_patron')) {
            return $this->needsLogin();
        }

        $this->cart->clear($mode);

        return redirect()->route("kiosk.$mode");
    }

    public function confirm(Request $request, string $mode)
    {
        if (! $patron = Session::get('kiosk_patron')) {
            return $this->needsLogin();
        }

        $patronId = (string) ($patron['patron_id'] ?? '');
        $lock = Cache::lock("kiosk-confirm-{$patronId}", 180);

        if (! $lock->get()) {
            return $this->reject($mode, 'Your request is already being processed.');
        }

        try {
	     $limit = $this->cart->limit($mode, $patron);

            if ($limit !== null && count($this->cart->items($mode)) > $limit) {
                return $this->reject($mode, $this->limitMessage($patron));
            }
            $items = $this->cart->consume($mode, (string) $request->input('cart_token'));

            if ($items === null) {
                return $this->reject($mode, 'Your cart is empty or has expired.');
            }

            $results = $this->process($this->action($mode), $mode, $patron, $items);
            $this->refreshPatron($patron, $mode, $results);
            $this->storeReceipt($mode, $patron, $results);
        } finally {
            $lock->release();
        }

        return redirect()->route('kiosk.success');
    }

    private function process(CirculationAction $action, string $mode, array $patron, array $items): array
    {
	set_time_limit(180);
        $patronId = (string) ($patron['patron_id'] ?? '');
        $results = [];

        foreach ($items as $item) {
            try {
                $outcome = $action->commit($patron, $item);

                $this->activity->log(
                    action: $mode,
                    status: 'success',
                    patronId: $patronId,
                    message: ($mode === 'borrow' ? 'Borrowed' : 'Returned') . ': ' . $item['title'],
                    barcode: $item['barcode'],
                    itemId: $item['item_id']
                );

                $results[] = $item + ['ok' => true, 'due_date' => $outcome['due_date'] ?? null, 'message' => null];
            } catch (\Throwable $e) {
                // Technical detail goes to the log only, never to the screen
                Log::warning("Kiosk {$mode} failed for {$item['barcode']}: " . $e->getMessage());

                $this->activity->log(
                    action: $mode,
                    status: 'failed',
                    patronId: $patronId,
                    message: $action->failureMessage(),
                    barcode: $item['barcode'],
                    itemId: $item['item_id']
                );

                $results[] = $item + ['ok' => false, 'due_date' => null, 'message' => $action->failureMessage()];
            }
        }

        return $results;
    }

    private function refreshPatron(array $patron, string $mode, array $results): void
    {
        try {
            $checkouts = $this->koha->getPatronCheckouts((int) ($patron['patron_id'] ?? 0));
            $patron['borrowed_books'] = CheckoutFormatter::format($checkouts);
        } catch (\Throwable $e) {
            // keep the existing list
        }

        if ($mode === 'return') {
            // Safety net: make sure returned books disappear even if Koha lags behind
            $returned = collect($results)->where('ok', true)->pluck('barcode')
                ->map(fn ($b) => strtolower($b))->all();

            $patron['borrowed_books'] = array_values(array_filter(
                $patron['borrowed_books'] ?? [],
                fn ($b) => ! in_array(strtolower((string) ($b['barcode'] ?? '')), $returned, true)
            ));
        }

        Session::put('kiosk_patron', $patron);
    }

    private function storeReceipt(string $mode, array $patron, array $results): void
    {
        $first = collect($results)->firstWhere('ok', true) ?? $results[0];

        Session::put('kiosk_last_receipt', [
            'type' => $mode,
            'patron_name' => $patron['name'] ?? '',
            'patron_id' => (string) ($patron['patron_id'] ?? ''),
            'items' => $results,
            // single-book keys kept so the current receipt page keeps working for now
            'title' => $first['title'],
            'author' => $first['author'],
            'barcode' => $first['barcode'],
            'due_date' => $first['due_date'],
            'timestamp' => now(),
        ]);
    }

    private function summarize(array $item, array $patron, string $scanned): array
    {
        $barcode = (string) ($item['barcode'] ?? $scanned);
        $loan = collect($patron['borrowed_books'] ?? [])->first(
            fn ($b) => strtolower((string) ($b['barcode'] ?? '')) === strtolower($barcode)
        );

        return [
            'item_id' => (int) ($item['item_id'] ?? 0),
            'barcode' => $barcode,
            'title' => $item['biblio']['title'] ?? $item['title'] ?? 'Unknown title',
            'author' => $item['biblio']['author'] ?? $item['author'] ?? null,
            'due' => data_get($loan, 'due'),
        ];
    }

    private function action(string $mode): CirculationAction
    {
        return app($mode === 'borrow' ? BorrowAction::class : ReturnAction::class);
    }

    private function needsLogin()
    {
        return redirect()->route('kiosk.welcome')
            ->withErrors(['uid' => 'Please scan your RFID card first.']);
    }

    private function reject(string $mode, string $message)
    {
        return redirect()->route("kiosk.$mode")->withErrors(['barcode' => $message]);
    }

        private function limitMessage(array $patron): string
    {
        $category = PatronCategory::for($patron['category_code'] ?? null);
        $onLoan = count($patron['borrowed_books'] ?? []);
        $max = $category['max_books'];
        $books = \Illuminate\Support\Str::plural('book', $max);

        if ($onLoan >= $max) {
            return "You already have {$onLoan} {$books} on loan, which is the most you can borrow at once. Please return a book first.";
        }

        return "You can borrow up to {$max} {$books} at once, and {$onLoan} " . ($onLoan === 1 ? 'is' : 'are') . " already on loan. Remove a book from your list to add another.";
    }

    private function fail(string $mode, string $patronId, string $barcode, ?int $itemId, string $message)
    {
        $this->activity->log(
            action: $mode,
            status: 'failed',
            patronId: $patronId,
            message: $message,
            barcode: $barcode,
            itemId: $itemId
        );

        return $this->reject($mode, $message);
    }
}