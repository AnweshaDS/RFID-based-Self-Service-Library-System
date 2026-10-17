<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 3mm; }
    body { font-family: 'Courier New', monospace; font-size: 9px; color: #000; margin: 0; padding: 0; }
    h1 { font-size: 12px; margin: 0 0 2px 0; text-align: center; letter-spacing: 1px; }
    .subtitle { font-size: 8px; text-align: center; margin: 0 0 6px 0; }
    .divider { border-top: 1px dashed #000; margin: 5px 0; }
    table.row { width: 100%; font-size: 8px; margin: 1px 0; border-collapse: collapse; }
    table.row td { padding: 1px 0; vertical-align: top; }
    table.row td.value { text-align: right; font-weight: bold; }
    .badge { text-align: center; font-weight: bold; font-size: 9px; border: 1px dashed #000; padding: 3px; margin: 5px 0; text-transform: uppercase; }
    .footer { text-align: center; font-size: 7px; margin-top: 6px; }
</style>
</head>
<body>
    <h1>KUET Central Library</h1>
    <p class="subtitle">Self-Service Transaction Receipt</p>

    <div class="badge">{{ $receipt['type'] === 'borrow' ? 'Book Borrowed' : 'Book Returned' }}</div>

    <div class="divider"></div>

    <table class="row">
        <tr><td>Date/Time</td><td class="value">{{ \Illuminate\Support\Carbon::parse($receipt['timestamp'])->format('d M y h:i A') }}</td></tr>
        <tr><td>Patron</td><td class="value">{{ $receipt['patron_name'] }}</td></tr>
        <tr><td>Patron ID</td><td class="value">{{ $receipt['patron_id'] }}</td></tr>
    </table>

    <div class="divider"></div>

    <table class="row">
        <tr><td>Title</td><td class="value">{{ $receipt['title'] }}</td></tr>
        @if (!empty($receipt['author']))
            <tr><td>Author</td><td class="value">{{ $receipt['author'] }}</td></tr>
        @endif
        <tr><td>Barcode</td><td class="value">{{ $receipt['barcode'] ?? 'N/A' }}</td></tr>
        @if ($receipt['type'] === 'borrow' && !empty($receipt['due_date']))
            <tr><td>Due Date</td><td class="value">{{ \Illuminate\Support\Carbon::parse($receipt['due_date'])->format('d M Y') }}</td></tr>
        @endif
    </table>

    <div class="divider"></div>

    <p class="footer">Please keep this receipt.<br>KUET Central Library</p>
</body>
</html>