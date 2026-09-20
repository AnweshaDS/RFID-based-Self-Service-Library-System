@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'mb-4 rounded-lg bg-catalog-teal/10 px-4 py-2.5 text-sm font-medium text-catalog-teal']) }}>
        {{ $status }}
    </div>
@endif