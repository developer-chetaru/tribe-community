<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reflection Resolved</title>
</head>
<body style="font-family: Arial, sans-serif; color:#333;">
    <h2>Dear {{ $reflection->user->name ?? 'User' }},</h2>
    <p>We hope this message finds you well.</p>
    <p>We would like to inform you that your Reflection on the topic <strong>{{ $reflection->topic ?? 'N/A' }}</strong>  has been successfully marked as <strong>Resolved</strong> by {{ $admin->name ?? 'Super Admin' }}.</p>
    <p>If you have any doubts or concerns regarding this, please be aware that the status of your Reflection may be updated accordingly, and further communication will follow to address any issues.</p>
    <p>Thank you for your attention, and we appreciate your continued engagement..</p>

    <p style="margin-top: 30px;">Best Regards,<br>
    <strong>{{ config('app.name') }}</strong></p>
</body>
</html>