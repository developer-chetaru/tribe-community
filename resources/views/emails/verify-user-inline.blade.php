<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Basecamp Verify User</title>
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
            <td align="center" style="text-align:center;">
                <table width="600" cellspacing="0" cellpadding="0" border="0" style="background:#ffffff;border-radius:8px;overflow:hidden;max-width:600px;margin:0 auto;">
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
                            <h2 style="font-family: 'Lexend', Arial, Helvetica, sans-serif;margin:0 0 20px 0;font-size:24px;font-weight:600;text-align:center;">Basecamp Verify User</h2>
                            <p style="color:#eb1c24;margin:0 0 15px 0;font-family: 'Lexend', Arial, Helvetica, sans-serif;">Welcome to Tribe365® Basecamp!</p>
                            <p style="margin:0 0 15px 0;font-family: 'Lexend', Arial, Helvetica, sans-serif;">Hi {{ $user->first_name ?? '<Name>' }},</p>
                            <p style="margin:0 0 15px 0;font-family: 'Lexend', Arial, Helvetica, sans-serif;">Basecamp is your HPTM® High Performing Team Member safe space to reflect and move forwards delivering the best results all the time. You are now "HPTM® Registered" which reflects the commitment you are making today</p>
                            <p style="margin:0 0 20px 0;font-family: 'Lexend', Arial, Helvetica, sans-serif;">To get started, please verify your account by clicking this link, then setting your password and completing one month's payment</p>
                            <table cellspacing="0" cellpadding="0" border="0" align="center" style="margin:25px auto;">
                                <tr>
                                    <td bgcolor="#eb1c24" style="border-radius:5px;">
                                        <a href="{{ $verificationUrl }}" style="display:inline-block;padding:12px 30px;color:#ffffff;text-decoration:none;font-weight:600;background-color:#eb1c24;border-radius:5px;font-family: 'Lexend', Arial, Helvetica, sans-serif;">Verify Account</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:20px 0 15px 0;font-family: 'Lexend', Arial, Helvetica, sans-serif;">If you didn't sign up for Tribe365® Basecamp, you can safely ignore this email</p>
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
