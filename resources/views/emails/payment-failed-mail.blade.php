<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Basecamp Payment Failed Mail</title>
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
                            <h2 style="color:#eb1c24;font-family: 'Lexend', Arial, Helvetica, sans-serif;margin:0 0 20px 0;font-size:24px;font-weight:600;">Payment Failed - Action Required</h2>
                            <p style="margin:0 0 15px 0;font-family: 'Lexend', Arial, Helvetica, sans-serif;">Hi {{ $user->first_name ?? '<Name>' }},</p>
                            <p style="margin:0 0 15px 0;font-family: 'Lexend', Arial, Helvetica, sans-serif;">We were unable to process your recent payment for your Tribe365® Basecamp subscription</p>
                            <p style="margin:0 0 15px 0;font-family: 'Lexend', Arial, Helvetica, sans-serif;">We have built in an automated cut-off of our services for 3 days after a missed payment so we don't build up costs that you may not be able to cover</p>
                            <p style="margin:0 0 15px 0;font-family: 'Lexend', Arial, Helvetica, sans-serif;">If you'd like to continue the Tribe365® Basecamp services please either complete payment within the next three days or contact us to explore options</p>
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
