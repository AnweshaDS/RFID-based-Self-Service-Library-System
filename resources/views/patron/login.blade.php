<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Library Login</title>
</head>
<body>
    <h1>Library Self-Service</h1>

    <form method="POST" action="{{ route('patron.login.submit') }}">
        @csrf
        <label for="cardnumber">Card number</label>
        <input id="cardnumber" name="cardnumber" type="text"
               value="{{ old('cardnumber') }}" autofocus autocomplete="off">
        @error('cardnumber')
            <p style="color:red">{{ $message }}</p>
        @enderror
        <button type="submit">Enter</button>
    </form>
</body>
</html>