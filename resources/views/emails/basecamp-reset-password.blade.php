<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invitation</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f9f9f9; padding:20px; margin:0;">
    <div style="max-width:600px; margin:0 auto; background:#fff; padding:30px; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.1); line-height:1.6;">
        
        <div style="text-align:center; margin-bottom:30px;">
            <img src="{{ asset('images/logo-tribe.png') }}" alt="Tribe365 Logo" style="width:140px;" />
        </div>

        <h2 style="color:#000000; margin:0 0 15px;">Hello {{ ucfirst($userFullName) }},</h2>
        
        <p style="color:#000000; margin:0 0 20px;">
            You’ve been invited by <strong>{{ ucfirst($inviterName) }}</strong> to join on Tribe365.
        </p>

     
        <p style="color:#000000; margin:0 0 25px;">
            Click on below button to set password. 
        </p>        

        <div style="margin:30px 0; text-align:center;">
            <a href="{{ $resetUrl }}"
               style="background:#e3342f; color:#fff; padding:14px 28px; text-decoration:none; border-radius:6px; font-weight:bold; display:inline-block;">
               Join 
            </a>
        </div>


      
        <p style="color:#000000; margin:0 0 5px;">Thank you,</p>
        <p style="color:#000000; margin:0;">The Tribe365 Team</p>
    </div>
</body>
</html>

