<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class KioskLayout extends Component
{
    public function __construct(
        public bool $scanLine = true,
        public string $maxWidth = 'max-w-lg',
    ) {}

    public function render(): View
    {
        return view('layouts.kiosk');
    }
}