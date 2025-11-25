<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>
        @if(!empty($inviterName))
            {{ $orgName }} Invitation
        @else
            Reset Your Tribe365 Password
        @endif
    </title>
</head>

<body style="font-family:Arial,Helvetica,sans-serif; background:#f2f2f2; padding:25px; margin:0;">
    <table width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center">

                <!-- Outer Container -->
                <table width="600" cellspacing="0" cellpadding="0"
                       style="background:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 3px 12px rgba(0,0,0,0.10);">

                    <!-- Top Accent -->
                    <tr>
                        <td style="background:#EB1C24; height:6px;"></td>
                    </tr>

                    <!-- Logo Section -->
                    <tr>
                        <td align="center" style="padding:30px 20px 10px;">
                            <img src="{{ asset('images/logo-tribe.png') }}"
                                 alt="Tribe365 Logo"
                                 style="width:150px; display:block;">
                        </td>
                    </tr>

                    <!-- Content Section -->
                    <tr>
                        <td style="padding:25px 35px; color:#333; font-size:15px; line-height:1.7;">

                            <p style="margin:0 0 20px;">Hello {{ ucfirst($userFullName) }},</p>

                            @if(!empty($inviterName))
                                <!-- Invitation Email -->

                                <p style="margin:0 0 18px;">
                                    You’ve been invited by {{ ucfirst($inviterName) }}
                                    to join {{ ucfirst($orgName) ?? '' }} on Tribe365.
                                </p>

                                <p style="margin:0 0 18px;">
                                    Tribe365 helps teams stay connected, aligned, and build a positive work culture together.
                                    Get started by setting up your password and activating your account:
                                </p>

                                <!-- Button -->
                                <div style="text-align:center; margin:35px 0;">
                                    <a href="{{ $resetUrl }}"
                                       style="
                                            background:#EB1C24;
                                            color:#ffffff;
                                            padding:10px 30px;
                                            font-size:14px;
                                            border-radius:5px;
                                            text-decoration:none;
                                            font-weight:bold;
                                            display:inline-block;">
                                        Set up your password to Join {{ ucfirst($orgName) ?? '' }}
                                    </a>
                                </div>

                                <p style="margin:0 0 18px;">
                                    Prefer not to join? You can decline this invitation,
                                    and we’ll let {{ ucfirst($inviterName) }} know.
                                </p>

                                <p style="margin:0 0 20px;">
                                    If you have any questions, reply to this email or reach out to
                                    {{ ucfirst($inviterName) }} directly.
                                </p>

                            @else
                                <!-- Password Reset Email -->

                                <p style="margin:0 0 18px;">
                                    We received a request to reset your Tribe365 password.
                                </p>

                                <p style="margin:0 0 25px;">
                                    Click the button below to create a new password for your account:
                                </p>

                                <!-- Button -->
                                <div style="text-align:center; margin:35px 0;">
                                    <a href="{{ $resetUrl }}"
                                       style="
                                            background:#EB1C24;
                                            color:#ffffff;
                                            padding:10px 30px;
                                            font-size:14px;
                                            border-radius:6px;
                                            text-decoration:none;
                                            font-weight:bold;
                                            display:inline-block;">
                                        Reset Password
                                    </a>
                                </div>

                                <p style="margin:0 0 20px;">
                                    If you didn’t request this, you can safely ignore this email —
                                    your account is secure.
                                </p>

                            @endif

                            <p style="margin:0 0 5px;">Thank you,</p>
                            <p style="margin:0;">The Tribe365 Team</p>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:15px 10px; background:#fafafa; text-align:center; color:#777; font-size:12px; border-top:1px solid #e6e6e6;">
                            © {{ date('Y') }} <strong style="color:#EB1C24;">Tribe365</strong> — All Rights Reserved
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>
</body>
</html>

