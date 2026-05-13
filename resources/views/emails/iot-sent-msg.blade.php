<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Chat Message Received</title>
</head>
<body style="font-family: Arial, sans-serif; color:#333;">
    <h2>New Chat Message Received</h2>
    <p><strong>From:</strong> {{ $sender->email ?? 'N/A' }} ({{ $sender->name ?? 'N/A' }})</p>
    
    <h3>Original Offloading Message:</h3>
    <p>{{ $originalMessage ?? 'N/A' }}</p>
    
    <h3>New Chat Message:</h3>
    <p>{{ $newMessage ?? 'N/A' }}</p>
    
    @if($imageUrl)
    <p><strong>Attached Image:</strong></p>
    <img src="{{ $imageUrl }}" alt="Chat Image" style="max-width: 500px;">
    @endif

    <p style="margin-top: 30px;">Best Regards,<br>
    <strong>{{ config('app.name') }}</strong></p>
</body>
</html>

