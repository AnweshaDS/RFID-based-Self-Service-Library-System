@props(['value'])

<label {{ $attributes->merge(['class' => 'mb-1.5 block text-sm font-medium text-ink-navy']) }}>
    {{ $value ?? $slot }}
</label>