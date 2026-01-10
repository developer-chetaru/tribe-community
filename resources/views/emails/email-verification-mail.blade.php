<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Verify Your Email</title>
    <style>
        @media only screen and (max-width: 600px) {
            .red-bar { width: 100% !important; }
            .container { padding: 10px !important; }
        }
    </style>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; padding: 0; margin: 0; background: #F9F9F9;">
    <div class="red-bar" style="height: 6px; width: 62%; background-color: #EB1C24; margin: 0 auto; border-radius: 4px;"></div>
    <div style="max-width: 500px; margin: auto; background: #FFFFFF; padding: 20px; border-radius: 0 0 8px 8px; color: black;">
        <img src="{{ asset('images/logo-tribe.png') }}" alt="Tribe365 Logo" style="width:120px; max-width:160px; margin: 0 auto 15px auto; display:block;" />
        
        <h2 style="margin-bottom: 20px; color: black;">Verify Your Email Address</h2>
        
        <p style="margin: 0 0 15px 0; color: black;">Hi {{ $user->first_name ?? 'there' }},</p>
        <p style="margin: 0 0 15px 0; color: black;">Thank you for signing up for Tribe365! Please verify your email address to complete your account setup.</p>
        
        <div style="text-align: center; margin: 25px 0;">
            <a href="{{ $verificationUrl }}" style="display: inline-block; padding: 12px 30px; background: #EB1C24; color: white; border-radius: 5px; text-decoration: none; font-weight: bold;">Verify Email Address</a>
        </div>
        
        <p style="margin: 0 0 15px 0; color: black; font-size: 14px;">Or copy and paste this link into your browser:</p>
        <p style="margin: 0 0 15px 0; color: #666; font-size: 12px; word-break: break-all;">{{ $verificationUrl }}</p>
        
        <p style="margin: 0 0 15px 0; color: black; font-size: 14px;">This verification link will expire in 24 hours.</p>
        <p style="margin: 0 0 15px 0; color: black;">If you didn't create an account with Tribe365, you can safely ignore this email.</p>
        
        <p style="margin-top: 20px; color: black;">Thank you,<br>The Tribe365 Team</p>
        
        <hr style="border: none; border-top: 1px solid #ccc; margin: 20px 0;">
        <p style="font-size: 12px; color: #888; text-align: center;">
            © {{ date('Y') }} <span style="color: #EB1C24; font-weight: bold;">Tribe365 <sup>®</sup></span> - ALL RIGHTS RESERVED<br>
            <a href="mailto:{{ config('app.support_email', 'support@tribe365.co') }}" style="color: #888; text-decoration: none;">{{ config('app.support_email', 'support@tribe365.co') }}</a>
        </p>
    </div>
</body>
</html>

