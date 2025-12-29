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
                            <img src="https://community.tribe365.co/images/logo-tribe.png" width="130" alt="Tribe365 Logo">
                        </td>
                    </tr>

                    <!-- Email Body -->
                    <tr>
                        <td style="padding:20px 30px; color:#333; font-size:15px; line-height:1.6;">
                            <p>Hello {{ name | default: "there" }},</p>

                            <p>We noticed that your sentiment update for today is still pending.</p>

                            <p style="margin-bottom:20px;">
                                When you have a moment, please share how your day has been.
                                Your daily input helps keep your emotional journey consistent and valuable.
                            </p>

                            <!-- BUTTON -->
                            <div style="text-align:center; margin:25px 0;">
                                <a href="https://community.tribe365.co/app-redirect"
                                   style="background:#EB1C24;
                                          color:#ffffff;
                                          padding:10px 25px;
                                          text-decoration:none;
                                          border-radius:6px;
                                          font-weight:bold;
                                          display:inline-block;">
                                    Update Now
                                </a>
                            </div>

                            <p>
                                Thank you for staying consistent and engaged with
                                <strong style="color:#EB1C24;">Tribe365</strong>.
                            </p>

                            <p style="margin-top:25px;">
                               Thanks,<br>
                               Tribe365 Team
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:15px; text-align:center; font-size:12px; color:#888; border-top:1px solid #E0E0E0;">
                            © {{ "now" | date: "%Y" }} <strong style="color:#EB1C24;">Tribe365</strong> — All Rights Reserved
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>