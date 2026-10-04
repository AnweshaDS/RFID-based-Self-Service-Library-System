<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class PatronController extends Controller
{
    public function showLogin(): View
    {
        return view('patron.login');
    }

    /**
     * TODO: this is a placeholder. Decide what a successful card-number
     * entry should actually do - e.g. look up the patron in Koha and start
     * a patron-facing session, similar to the kiosk flow in KioskController.
     */
    public function login(Request $request)
    {
        $request->validate(['cardnumber' => 'required|string']);

        return back()->withErrors([
            'cardnumber' => 'Patron login is not yet implemented.',
        ]);
    }
}