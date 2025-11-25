<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Monthly Report - Tribe365</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, sans-serif; background-color:#f9f9f9;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f9f9f9">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background:#ffffff; margin:20px auto; border-radius:8px; overflow:hidden; box-shadow:0 2px 6px rgba(0,0,0,0.1);">
                    
                    <!-- Header -->
                    <tr>
                        <td bgcolor="#ff4d4f" align="center" style="padding:20px; color:#ffffff; font-size:24px; font-weight:bold;">
                            Tribe365 - Monthly Report
                        </td>
                    </tr>

                    <!-- Greeting -->
                    <tr>
                        <td style="padding:20px; font-size:16px; color:#333333;">
                            <p>Hi {{ $user->name ?? 'Team Member' }},</p>
                            <p>Here’s your <strong>Monthly Report</strong> for <b>{{ now()->format('F Y') }}</b>.  
                               This summary highlights your overall engagement, happiness index, and performance trends.</p>
                        </td>
                    </tr>

                    <!-- Stats Section -->
                    <tr>
                        <td style="padding:20px;">
                            <table width="100%" cellpadding="10" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" bgcolor="#f3f3f3" style="border-radius:6px;">
                                        <h3 style="margin:0; color:#ff4d4f;">{{ $user->monthly_score ?? '75%' }}</h3>
                                        <p style="margin:0; color:#555;">Happiness Index</p>
                                    </td>
                                    <td align="center" bgcolor="#f3f3f3" style="border-radius:6px;">
                                        <h3 style="margin:0; color:#ff4d4f;">{{ $user->monthly_feedbacks ?? '12' }}</h3>
                                        <p style="margin:0; color:#555;">Feedbacks Submitted</p>
                                    </td>
                                    <td align="center" bgcolor="#f3f3f3" style="border-radius:6px;">
                                        <h3 style="margin:0; color:#ff4d4f;">{{ $user->monthly_participation ?? '89%' }}</h3>
                                        <p style="margin:0; color:#555;">Participation</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Summary -->
                    <tr>
                        <td style="padding:20px; font-size:14px; color:#444444; line-height:1.6;">
                            <p>✅ Great work this month! Keep engaging and sharing feedback with your team.</p>
                            <p>📊 Use your monthly insights to track progress and improve collaboration.</p>
                        </td>
                    </tr>

                    <!-- Button -->
                    <tr>
                        <td align="center" style="padding:20px;">
                            <a href="https://tribe365.io/dashboard" 
                               style="background:#ff4d4f; color:#fff; text-decoration:none; padding:12px 24px; border-radius:6px; font-size:16px; font-weight:bold;">
                               View Full Report
                            </a>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td bgcolor="#fafafa" align="center" style="padding:15px; font-size:12px; color:#888888;">
                            © {{ date('Y') }} Tribe365. All Rights Reserved.
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
