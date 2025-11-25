<!DOCTYPE html>
<html>
<head>
    <title>Account Activation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 80px;
            background-color: #f9fafb;
        }
        .message {
            background: #ffffff;
            display: inline-block;
            padding: 40px 50px;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            max-width: 500px;
        }
        h2 {
            margin-bottom: 20px;
        }
        p {
            font-size: 16px;
            color: #555;
        }
        .success {
            color: #16a34a; /* green */
        }
        .already {
            color: #f97316; /* orange */
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: bold;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            margin-top: 20px;
        }
        .btn-login {
            background-color: #dc2626; /* red */
            color: #fff !important;
        }
        .btn-login:hover {
            background-color: #b91c1c;
        }
        .progress {
            margin: 20px auto;
            height: 8px;
            width: 80%;
            background: #e5e7eb;
            border-radius: 6px;
            overflow: hidden;
            position: relative;
        }
        .progress-bar {
            height: 100%;
            width: 0;
            background: linear-gradient(90deg, #ef4444, #b91c1c);
            animation: progressAnim 2s forwards;
        }
        @keyframes progressAnim {
            from { width: 0; }
            to { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="message">
        @if ($user->status)
            <h2 class="success">🎉 Your account has been activated successfully!</h2>
            <p>We’re setting things up for you. Please proceed to login.</p>
        @else
            <h2 class="already">⚡ Your account is already active!</h2>
            <p>You can go ahead and log in directly.</p>
        @endif

    

        {{-- Login Button --}}
        <a href="{{ url('/login') }}" class="btn btn-login">Go to Login</a>
    </div>
</body>
</html>
