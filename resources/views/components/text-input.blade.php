@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'block w-full rounded-lg border-slate/30 bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm transition focus:border-catalog-teal focus:ring-catalog-teal disabled:bg-paper disabled:opacity-60']) }}>