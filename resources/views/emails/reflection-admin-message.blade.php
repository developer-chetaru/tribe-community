<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Admin Started Conversation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
            line-height: 1.6;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #EB1C24;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f9f9f9;
            padding: 30px;
            border: 1px solid #e0e0e0;
            border-top: none;
        }
        .button {
            display: inline-block;
            background-color: #EB1C24;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2 style="margin: 0;">Tribe365 - Reflection Update</h2>
    </div>
    
    <div class="content">
        <h2>Dear {{ $reflection->user->name ?? 'User' }},</h2>
        
        <p>We hope this message finds you well.</p>
        
        <p><strong>{{ $admin->name ?? 'Admin' }}</strong> has started a conversation with you about your reflection:</p>
        
        <div style="background-color: white; padding: 15px; border-left: 4px solid #EB1C24; margin: 20px 0;">
            <p style="margin: 0;"><strong>Topic:</strong> {{ $reflection->topic ?? 'N/A' }}</p>
            @if($reflection->message)
                <p style="margin: 10px 0 0 0;"><strong>Message:</strong> {{ Str::limit($reflection->message, 150) }}</p>
            @endif
        </div>
        
        <p>Please log in to your Tribe365 account to view and respond to the message.</p>
        
        <div style="text-align: center;">
            <a href="{{ url('/reflection-list') }}" class="button">View Reflection</a>
        </div>
        
        <p style="margin-top: 30px; font-size: 14px; color: #666;">
            You will receive notifications for all new messages in this conversation.
        </p>
    </div>
    
    <div class="footer">
        <p>Best Regards,<br>
        <strong>{{ config('app.name') }}</strong></p>
        <p style="margin-top: 10px;">This is an automated notification. Please do not reply to this email.</p>
    </div>
</body>
</html>
