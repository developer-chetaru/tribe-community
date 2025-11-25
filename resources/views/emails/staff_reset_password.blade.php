<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Password Reset</title>
</head>
<body>
    <h2>Hello {{ $user->first_name }},</h2>

    <p>You requested a password reset for your account at <strong>{{ $organisation }}</strong>.</p>

    <p>
        <a href="{{ $url }}" 
           style="background:#ff2323;color:#fff;padding:10px 20px;text-decoration:none;border-radius:5px;">
           Reset Password
        </a>
    </p>

    <p>Thank you for using Tribe365!</p>
</body>
</html>
