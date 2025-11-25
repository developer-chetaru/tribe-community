<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Weekly Sentiment Summary</title>
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
                        <img src="{{ asset('images/logo-tribe.png') }}" width="140" alt="Tribe365 Logo">
                    </td>
                </tr>

                <!-- Body -->
                <tr>
                    <td style="padding:25px 35px; color:#333; font-size:15px; line-height:1.7;">

                        <!-- Greeting -->
                        <p>Hi {{ $user->first_name }},</p>

                        <p>
                            Hope you had an amazing week ({{ $weekLabel }}).  
                            Please see your weekly report below:
                        </p>

                        <!-- PART 1: Engagement -->
                        <h3 style="color:#EB1C24; margin-top:25px;">Part 1: Engagement Summary</h3>

                        <div style="
                            background:#fafafa;
                            border:1px solid #eee;
                            padding:18px;
                            border-radius:8px;
                            font-size:14px;
                            margin-bottom:20px;">

                            {!! nl2br(e($engagementText)) !!}
                        </div>

                        <!-- PART 2: Individual AI Summary -->
                        <h3 style="color:#EB1C24; margin-top:25px;">Part 2: Your Emotional Summary</h3>

                        <div style="
                            background:#fafafa;
                            border:1px solid #eee;
                            padding:18px;
                            border-radius:8px;
                            font-size:14px;
                            margin-bottom:20px;">
                            {!! nl2br(e($summaryText)) !!}
                        </div>

                        <!-- PART 3: Organisation Summary -->
                        <h3 style="color:#EB1C24; margin-top:25px;">Part 3: Organisation Summary</h3>

                        <div style="
                            background:#fafafa;
                            border:1px solid #eee;
                            padding:18px;
                            border-radius:8px;
                            font-size:14px;">
                            {!! nl2br(e($organisationSummary)) !!}
                        </div>

                        <!-- Footer Message -->
                        <p style="margin-top:25px;">
                            Stay consistent. Your wellbeing journey matters.<br>
                            Tribe365 Team
                        </p>

                    </td>
                </tr>

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
