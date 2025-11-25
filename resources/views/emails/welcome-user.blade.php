<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Daily Sentiment Reminder</title>
</head>

<body style="margin:0; padding:0; background:#f5f5f5; font-family: Arial, Helvetica, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5; padding:20px 0;">
        <tr>
            <td align="center">

                <table width="500" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:10px; overflow:hidden;">
                    
                    <!-- Header Bar -->
                    <tr>
                        <td style="background:#EB1C24; height:6px;"></td>
                    </tr>

                    <!-- Logo -->
                    <tr>
                        <td align="center" style="padding:25px 20px 10px;">
                            <img src="{{ asset('images/logo-tribe.png') }}" width="130" alt="Tribe365 Logo">
                        </td>
                    </tr>

                    <td style="text-align:center;">
                            <p style="color:#333; margin-bottom:10px;">Welcome to Tribe365</p>
                            <p style="font-size:15px; color:#555;">
                                Hi there! Your account has been created successfully.
                            </p>

                            <p style="font-size:15px; color:#555; margin-top:25px;">
                                You can now log in and start using the platform.
                            </p>

                            <a href="https://community.tribe365.co/login" 
                               style="display:inline-block; margin-top:25px; padding:12px 25px; 
                               background:#EB1C24; color:white; text-decoration:none; 
                               border-radius:6px; font-size:16px;">
                               Login Now
                            </a>

                            <p style="margin-top:30px; font-size:13px;"></p>
                        </td>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:15px; text-align:center; font-size:12px; color:#888; border-top:1px solid #e0e0e0;">
                            © {{ date('Y') }} <strong style="color:#EB1C24;">Tribe365</strong> — All Rights Reserved
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>
</body>
</html>
