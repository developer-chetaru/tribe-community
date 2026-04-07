<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Monthly Sentiment Summary</title>
</head>

<body style="margin:0; padding:0; background:#f5f5f5; font-family: Arial, Helvetica, sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5; padding:20px 0;">
    <tr>
        <td align="center">

            <table width="550" cellpadding="0" cellspacing="0"
                   style="background:#ffffff; border-radius:10px; overflow:hidden;">

                <!-- Top Red Bar -->
                <tr>
                    <td style="background:#EB1C24; height:6px;"></td>
                </tr>

                <!-- Logo -->
                <tr>
                    <td align="center" style="padding:25px 20px 10px;">
                        <img src="{!! $logoUrl !!}" width="140" alt="Tribe365 Logo">
                    </td>
                </tr>

                <!-- Body -->
                <tr>
                    <td style="padding:25px 35px; color:#333; font-size:15px; line-height:1.7;">

            

                        <p>Hello {{ $userFirstName }},</p>

                        <p>
                            Here's your emotional summary for
                            <strong style="color:#EB1C24;">{{ $monthName }}</strong>:
                        </p>

                        <!-- Summary Box -->
                        <div style="
                                background:#fafafa;
                                border:1px solid #eee;
                                padding:18px;
                                margin:20px 0;
                                border-radius:8px;
                                font-size:14px;">
                            {!! nl2br(e($summaryText)) !!}
                        </div>

                        <p style="margin-top:10px;">
                            Keep reflecting on your progress and stay inspired!
                        </p>

                        <p style="margin-top:25px;">
                            Thanks,<br>
                            <strong>The Tribe365 Team</strong>
                        </p>

                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td style="padding:15px; text-align:center; font-size:12px; color:#888; border-top:1px solid #e0e0e0;">
                        © {{ $currentYear }} <strong style="color:#EB1C24;">Tribe365</strong> — All Rights Reserved
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>
</body>
</html>
