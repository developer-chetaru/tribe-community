<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Basecamp Email Verification Mail</title>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Lexend', Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
        }
        @media only screen and (max-width: 600px) {
            .container { width: 100% !important; padding: 10px !important; }
        }
    </style>
</head>
<body style="font-family: 'Lexend', Arial, Helvetica, sans-serif; margin: 0; padding: 0; background-color: #f9f9f9;">
    <table width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f9f9f9;padding:20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellspacing="0" cellpadding="0" border="0" style="background:#ffffff;border-radius:8px;overflow:hidden;max-width:600px;">
                    <tr>
                        <td style="background:#eb1c24;height:6px;"></td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:30px 20px 20px 20px;">
                            <img src="{{ asset('images/logo-tribe.png') }}" alt="Tribe365 Logo" width="130" style="display:block;margin:0 auto;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 40px 30px 40px;color:#333;font-size:15px;line-height:1.6;font-family: 'Lexend', Arial, Helvetica, sans-serif;">
                            <h2 style="color:#eb1c24;font-family: 'Lexend', Arial, Helvetica, sans-serif;margin:0 0 20px 0;font-size:24px;font-weight:600;">Basecamp Email Verification Mail</h2>
                            <h3 style="color:#eb1c24;font-family: 'Lexend', Arial, Helvetica, sans-serif;margin:0 0 15px 0;font-size:20px;font-weight:600;">Verify Your Email Address</h3>
                            <p style="margin:0 0 15px 0;font-family: 'Lexend', Arial, Helvetica, sans-serif;">Hi {{ $user->first_name ?? '<Name>' }},</p>
                            <p style="margin:0 0 15px 0;font-family: 'Lexend', Arial, Helvetica, sans-serif;">Thank you for signing up to Tribe365® Basecamp! Please verify your email address to continue your account setup</p>
                            <table cellspacing="0" cellpadding="0" border="0" align="center" style="margin:25px auto;">
                                <tr>
                                    <td bgcolor="#eb1c24" style="border-radius:5px;">
                                        <a href="{{ $verificationUrl }}" style="display:inline-block;padding:12px 30px;color:#ffffff;text-decoration:none;font-weight:600;background-color:#eb1c24;border-radius:5px;font-family: 'Lexend', Arial, Helvetica, sans-serif;">Verify Email</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 15px 0;color:#666;font-family: 'Lexend', Arial, Helvetica, sans-serif;">Or copy and paste this link into your browser</p>
                            <p style="margin:0 0 15px 0;color:#666;font-size:12px;word-break:break-all;font-family: 'Lexend', Arial, Helvetica, sans-serif;">{{ $verificationUrl }}</p>
                            <p style="margin:0 0 15px 0;color:#666;font-family: 'Lexend', Arial, Helvetica, sans-serif;">This verification link will expire in 24 hours</p>
                            <p style="margin:0 0 15px 0;font-family: 'Lexend', Arial, Helvetica, sans-serif;">If you didn't create a Tribe365® Basecamp account you can safely ignore this email</p>
                            <p style="margin:0 0 15px 0;font-family: 'Lexend', Arial, Helvetica, sans-serif;">If you have any questions please contact us</p>
                            <p style="margin-top:25px;font-family: 'Lexend', Arial, Helvetica, sans-serif;">Thank you<br>Team Tribe365®</p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:20px;font-size:12px;color:#888;border-top:1px solid #e0e0e0;font-family: 'Lexend', Arial, Helvetica, sans-serif;">
                            © 2026 <span style="color:#eb1c24;font-weight:600;">TRIBE365<sup>®</sup></span> - ALL RIGHTS RESERVED<br>
                            Contact us: +44 (0) 1325 734 846 | Email: <a href="mailto:team@tribe365.co" style="color:#888;text-decoration:none;">team@tribe365.co</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
