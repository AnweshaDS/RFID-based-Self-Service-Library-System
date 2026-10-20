@props(['title', 'subtitle' => null, 'back' => null, 'backLabel' => 'Back to Dashboard'])

<div class="kiosk-page-header">
    @if ($back)
        <a href="{{ $back }}" class="kiosk-back-btn"><span aria-hidden="true">&larr;</span> {{ $backLabel }}</a>
    @endif

    <h1 class="kiosk-page-title font-display">{{ $title }}</h1>

    @if ($subtitle)
        <p class="kiosk-page-subtitle">{{ $subtitle }}</p>
    @endif
</div>