<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex w-full items-center justify-center rounded-lg bg-ink-navy px-4 py-2.5 text-sm font-semibold text-paper transition hover:bg-catalog-teal focus:outline-none focus:ring-2 focus:ring-catalog-teal focus:ring-offset-2 disabled:opacity-50']) }}>
    {{ $slot }}
</button>