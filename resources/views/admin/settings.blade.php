<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Settings</h2>
    </x-slot>

    <style>
        .st-box { background: #fff; border: 2px solid #e5e7eb; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); padding: 20px; }
        .st-card { display: block; text-decoration: none; background: #fff; border: 2px solid #e5e7eb; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); padding: 18px 20px; }
        .st-card:hover { border-color: #818cf8; background: #eef2ff; }
        .st-card b { display: block; font-size: 16px; color: #111827; }
        .st-card span { display: block; margin-top: 4px; font-size: 13px; color: #6b7280; }
        .st-label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; font-weight: 700; }
        .st-value { margin-top: 4px; font-size: 15px; color: #111827; }
    </style>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <a href="{{ route('admin.retention.edit') }}" class="st-card">
                <b>Photo retention</b>
                <span>How long kiosk identity photos are kept before they are deleted automatically.</span>
            </a>

            <div class="st-box">
                <p class="st-label">About this system</p>
                <p class="st-value" style="font-weight: 600;">{{ config('smartlib.name') }}</p>
                <p class="st-value">{{ config('smartlib.full_title') }}</p>
            </div>
        </div>
    </div>
</x-app-layout>