<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class KioskController extends Controller
{
    // TEMPORARY: replace with a real Koha API lookup (see plan doc, "RFID → Koha Mapping").
    private array $mockPatrons = [
        '04:B2:11:8A:92:31' => [
            'patron_id' => '10021',
            'name' => 'Fariha Tabassum',
            'status' => 'Active',
            'borrowed_books' => [
                ['title' => 'Clean Code', 'due' => '2026-09-25'],
                ['title' => 'Computer Networks', 'due' => '2026-09-29'],
            ],
            'recent_activity' => [
                ['action' => 'Borrowed', 'title' => 'Clean Code', 'date' => '2026-09-11'],
                ['action' => 'Returned', 'title' => 'Operating Systems', 'date' => '2026-09-05'],
            ],
        ],
        '04:A3:91:7B:22:18' => [
            'patron_id' => '10023',
            'name' => 'Anwesha Das Sreya',
            'status' => 'Active',
            'borrowed_books' => [
                ['title' => 'Database System Concepts', 'due' => '2026-10-01'],
            ],
            'recent_activity' => [
                ['action' => 'Borrowed', 'title' => 'Database System Concepts', 'date' => '2026-09-17'],
            ],
        ],
    ];

    public function welcome()
    {
        return view('kiosk.welcome');
    }

    public function scan(Request $request)
    {
        $request->validate(['uid' => 'required|string']);

        $uid = strtoupper(trim($request->input('uid')));
        $patron = $this->mockPatrons[$uid] ?? null;

        if (! $patron) {
            return back()->withErrors([
                'uid' => 'RFID tag not recognized. This card is not linked to a library account.',
            ]);
        }

        Session::put('kiosk_patron', $patron);
        Session::put('kiosk_last_activity', now());

        return redirect()->route('kiosk.dashboard');
    }

    public function dashboard()
    {
        $patron = Session::get('kiosk_patron');

        if (! $patron) {
            return redirect()->route('kiosk.welcome');
        }

        $dueSoonCount = collect($patron['borrowed_books'])
            ->filter(fn ($book) => now()->diffInDays(\Illuminate\Support\Carbon::parse($book['due']), false) <= 3)
            ->count();

        return view('kiosk.dashboard', ['patron' => $patron, 'dueSoonCount' => $dueSoonCount]);
    }

    public function logout()
    {
        Session::forget('kiosk_patron');
        Session::forget('kiosk_last_activity');

        return redirect()->route('kiosk.welcome');
    }
}