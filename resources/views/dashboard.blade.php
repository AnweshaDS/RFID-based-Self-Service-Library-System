<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard</title>
</head>
<body>
    <h1>Welcome, {{ $patron->full_name }}</h1>
    <p>Card: {{ $patron->cardnumber }}</p>
    <p>Category: {{ $patron->categorycode }}</p>
    <p>Branch: {{ $patron->branchcode }}</p>
    <p>Valid until: {{ $patron->dateexpiry?->format('d M Y') ?? 'N/A' }}</p>

    <form method="POST" action="{{ route('patron.logout') }}">
        @csrf
        <button type="submit">Log out</button>
    </form>
</body>
</html>
