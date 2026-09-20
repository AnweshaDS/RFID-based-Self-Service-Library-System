<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class KioskController extends Controller
{
    // TEMPORARY: replace with a real Koha API lookup (see plan doc, "RFID → Koha Mapping").
    private array $mockPatrons = [
        '04:A3:91:7B:22:18' => [
            'name' => 'Fariha Tabassum',
            'borrowed_books' => [
                ['title' => 'Clean Code', 'due' => '2026-09-25'],
                ['title' => 'Computer Networks', 'due' => '2026-09-29'],
            ],
        ],
        '04:B2:11:8A:92:31' => [
            'name' => 'Anwesha Das Sreya',
            'borrowed_books' => [
                ['title' => 'Database System Concepts', 'due' => '2026-10-01'],
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

        return view('kiosk.dashboard', ['patron' => $patron]);
    }

    public function logout()
    {
        Session::forget('kiosk_patron');
        Session::forget('kiosk_last_activity');

        return redirect()->route('kiosk.welcome');
    }
}