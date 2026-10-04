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
    <div class="receipt">
        <h1>KUET Central Library</h1>
        <p class="subtitle">Self-Service Transaction Receipt</p>

        <div class="action-badge">{{ $receipt['type'] === 'borrow' ? 'Book Borrowed' : 'Book Returned' }}</div>

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

        <div class="row">
            <span class="label">Title</span>
            <span class="value">{{ $receipt['title'] }}</span>
        </div>
        @if (!empty($receipt['author']))
            <div class="row">
                <span class="label">Author</span>
                <span class="value">{{ $receipt['author'] }}</span>
            </div>
        @endif
        <div class="row">
            <span class="label">Barcode</span>
            <span class="value">{{ $receipt['barcode'] ?? 'N/A' }}</span>
        </div>
        @if ($receipt['type'] === 'borrow' && !empty($receipt['due_date']))
            <div class="row">
                <span class="label">Due Date</span>
                <span class="value">{{ \Illuminate\Support\Carbon::parse($receipt['due_date'])->format('d M Y') }}</span>
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