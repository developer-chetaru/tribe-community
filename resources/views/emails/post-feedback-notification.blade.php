<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Offloading Received</title>
</head>
<body style="font-family: Arial, sans-serif; color:#333;">
    <h2>New Offloading Received</h2>
    <p><strong>User Email:</strong> {{ $user->email ?? 'N/A' }}</p>
    <p><strong>User Name:</strong> {{ $user->name ?? 'N/A' }}</p>
    
    <h3>Offloading Message:</h3>
    <p>{{ $message ?? 'N/A' }}</p>
    
    @if($imageUrl)
    <p><strong>Attached Image:</strong></p>
    <img src="{{ $imageUrl }}" alt="Offloading Image" style="max-width: 500px;">
    @endif

    <p style="margin-top: 30px;">Best Regards,<br>
    <strong>{{ config('app.name') }}</strong></p>
</body>
</html>

