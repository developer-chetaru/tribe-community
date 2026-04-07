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
<body style="font-family: sans-serif; padding: 0; margin: 0; background: #F9F9F9;">
  <!-- :red_circle: Top Red Bar -->
   <div class="red-bar" style="height: 6px; width: 62%; background-color: red; margin: 0 auto; border-radius: 4px;"></div>
 <div style="max-width: 500px; margin: auto; background: #FFFFFF; padding: 20px; border-radius: 0 0 8px 8px; color: black;">

    <img src="{{ asset('images/logo-tribe.png') }}" alt="Tribe365 Logo" style="width:120px; max-width:160px; margin: 0 auto 15px auto; display:block;" />
    
    <h2 style="margin-bottom: 20px; color: black;">
      Welcome to Tribe365!
    </h2>

    <p style="margin: 0 0 15px 0; color: black;">Your Basecamp account lets you explore all activities, share daily check-ins, and track your work culture journey — without being tied to any organisation.</p>

    <p style="margin: 0 0 15px 0; color: black;">To get started, please verify your account by setting up your password:</p>

    <p>
      <a href="{{ $verificationUrl }}"
         style="display:inline-block; padding:10px 20px; background:red; color:white; border-radius:5px; text-decoration:none;">
        Verify
      </a>
    </p>
    
    <p style="margin: 0 0 15px 0; color: black;">If you didn’t sign up for Tribe365, you can safely ignore this email.</p>
    
    <p style="color: black;">Thank you,<br>The Tribe365 Team</p>
    
    <br>
    <hr style="border: none; border-top: 1px solid #ccc;">
    <p style="font-size: 12px; color: #888; text-align: center;">
      © {{ date('Y') }}
      <span style="color: red; font-weight: bold;">Tribe365 <sup>®</sup></span> - ALL RIGHTS RESERVED
      <br>
      <span>Email:
        <a href="mailto:{{ config('app.support_email') }}" 
           style="color: #888; text-decoration: none;">
           {{ config('app.support_email') }}
        </a></span>
    </p>
</div>

</body>
</html>