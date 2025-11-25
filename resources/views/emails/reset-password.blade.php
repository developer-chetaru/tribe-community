<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Password Reset OTP</title>
  <style>
    @media only screen and (max-width: 600px) {
      .red-bar {
        width: 100% !important;
      }
    }
  </style>
</head>
<body style="font-family: sans-serif; padding: 0; margin: 0; background: #f9f9f9;">
   <div class="red-bar" style="height: 6px; width: 62%; background-color: #e3342f; margin: 0 auto; border-radius: 4px;"></div>

  <div style="max-width: 500px; margin: auto; background: #ffffff; padding: 20px; border-radius: 0 0 8px 8px;">
   <h2 style="color: #e3342f;">Password Reset</h2>
    <p>Hello {{ $user->name }},</p>

    <p>We received a request to reset your Tribe365 account password.</p>
           <p style="text-align:center; margin:30px 0;">
                <a href="{{ $url }}" 
                   style="background:#ff0918; color:white; text-decoration:none; padding:12px 24px; font-size:16px; border-radius:6px; display:inline-block;">
                   Reset Password
                </a>
            </p>
    <p style="color: #888;">This code is valid for the next 24 hour.</p>
    <p style="color: #888;">If you did not request a password reset, please ignore this message.</p>
    <br>
    <hr style="border: none; border-top: 1px solid #ccc;">
    <p style="font-size: 12px; color: #888; text-align: center;">
      © {{ date('Y') }}
      <span style="color: #e3342f; font-weight: bold;">TRIBE365<sup>®</sup></span> - ALL RIGHTS RESERVED
    </p>
    <p style="font-size: 12px; color: #888; text-align: center;">
      Contact us: +44 (0) 1325 734 846 | Email:
      <a href="mailto:team@tribe365.co" style="color: #888; text-decoration: none;">team@tribe365.co</a>
    </p>
  </div>
</body>
</html>
