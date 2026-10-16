<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt - KUET Library</title>
    <style>
        @page { size: A4; margin: 20mm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            background: #f3f4f6;
            color: #111827;
            margin: 0;
            padding: 24px;
        }
        .receipt {
            max-width: 480px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #d1d5db;
            padding: 32px;
        }
        .receipt h1 {
            font-size: 18px;
            text-align: center;
            margin: 0 0 4px 0;
            letter-spacing: 0.05em;
        }
        .receipt .subtitle {
            text-align: center;
            font-size: 11px;
            color: #6b7280;
            margin: 0 0 20px 0;
        }
        .divider {
            border-top: 1px dashed #9ca3af;
            margin: 16px 0;
        }
        .row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin: 6px 0;
            gap: 16px;
        }
        .row .label { color: #6b7280; }
        .row .value { font-weight: 600; text-align: right; }
        .action-badge {
            text-align: center;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin: 16px 0;
            padding: 8px;
            border: 1px dashed #111827;
        }
        .footer-note {
            text-align: center;
            font-size: 11px;
            color: #6b7280;
            margin-top: 20px;
        }
        .actions {
            max-width: 480px;
            margin: 16px auto 0 auto;
            display: flex;
            gap: 12px;
        }
        .actions a, .actions button {
            flex: 1;
            text-align: center;
            padding: 10px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid #d1d5db;
            background: #ffffff;
            color: #111827;
            text-decoration: none;
            font-family: inherit;
        }
        .actions .print-btn {
            background: #16233E;
            color: #ffffff;
            border-color: #16233E;
        }

        @media print {
            body { background: #ffffff; padding: 0; }
            .actions { display: none; }
            .receipt { border: none; max-width: none; }
        }
    </style>
</head>
<body>
@php
    $isBorrow = $receipt['type'] === 'borrow';
    $lines = collect($receipt['items'] ?? [])->where('ok', true)->values();
    $failedCount = count($receipt['items'] ?? []) - $lines->count();

    // Older single-book receipts have no "items" list
    if (empty($receipt['items'])) {
        $lines = collect([[
            'title' => $receipt['title'] ?? '',
            'author' => $receipt['author'] ?? null,
            'barcode' => $receipt['barcode'] ?? null,
            'due_date' => $receipt['due_date'] ?? null,
        ]]);
        $failedCount = 0;
    }
@endphp
    <div class="receipt">
        <h1>KUET Central Library</h1>
        <p class="subtitle">Self-Service Transaction Receipt</p>

        <div class="action-badge">{{ $lines->count() }} {{ \Illuminate\Support\Str::plural('Book', $lines->count()) }} {{ $isBorrow ? 'Borrowed' : 'Returned' }}</div>

        <div class="divider"></div>

        <div class="row">
            <span class="label">Date &amp; Time</span>
            <span class="value">{{ \Illuminate\Support\Carbon::parse($receipt['timestamp'])->format('d M Y, h:i A') }}</span>
        </div>
        <div class="row">
            <span class="label">Patron</span>
            <span class="value">{{ $receipt['patron_name'] }}</span>
        </div>
        <div class="row">
            <span class="label">Patron ID</span>
            <span class="value">{{ $receipt['patron_id'] }}</span>
        </div>

        <div class="divider"></div>

                        @foreach ($lines as $line)
            <div class="row">
                <span class="label">{{ $loop->iteration }}. Title</span>
                <span class="value">{{ $line['title'] }}</span>
            </div>
            @if (!empty($line['author']))
                <div class="row">
                    <span class="label">Author</span>
                    <span class="value">{{ $line['author'] }}</span>
                </div>
            @endif
            <div class="row">
                <span class="label">Barcode</span>
                <span class="value">{{ $line['barcode'] ?? 'N/A' }}</span>
            </div>
            @if ($isBorrow && !empty($line['due_date']))
                <div class="row">
                    <span class="label">Due Date</span>
                    <span class="value">{{ \Illuminate\Support\Carbon::parse($line['due_date'])->format('d M Y') }}</span>
                </div>
            @endif
            @if (! $loop->last)
                <div class="divider"></div>
            @endif
        @endforeach

        @if ($failedCount > 0)
            <div class="divider"></div>
            <div class="row">
                <span class="label">Not completed</span>
                <span class="value">{{ $failedCount }} {{ \Illuminate\Support\Str::plural('book', $failedCount) }}</span>
            </div>
        @endif

        <div class="divider"></div>

        <p class="footer-note">Please keep this receipt for your records.<br>Khulna University of Engineering &amp; Technology</p>
    </div>

    <div class="actions">
        <button type="button" class="print-btn" onclick="window.print()">Print</button>
        <a href="{{ route('kiosk.dashboard') }}">Back to Dashboard</a>
    </div>
</body>
</html>