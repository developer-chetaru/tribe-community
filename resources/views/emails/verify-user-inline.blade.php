<table width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f9f9f9;padding:20px 0;font-family:Arial,Helvetica,sans-serif;">
  <tr>
    <td align="center">
      <table width="520" cellspacing="0" cellpadding="0" border="0" style="background:#ffffff;border-radius:8px;overflow:hidden;">
        <tr>
          <td style="background:#EB1C24;height:6px;"></td>
        </tr>
        <tr>
          <td align="center" style="padding:25px 20px 10px 20px;">
            <img src="{{ asset('images/logo-tribe.png') }}" alt="Tribe365 Logo" width="130" style="display:block;margin:0 auto;">
          </td>
        </tr>
        <tr>
          <td style="padding:10px 30px 25px 30px;color:#333;font-size:15px;line-height:1.6;">
            <p style="margin-bottom:20px;">Welcome to Tribe365!</p>
            <p style="margin:0 0 15px 0;">Your Basecamp account lets you explore all activities, share daily check-ins, and track your work culture journey — without being tied to any organisation.</p>
            <p style="margin:0 0 15px 0;">To get started, please verify your account by setting up your password:</p>
            <table cellspacing="0" cellpadding="0" border="0" align="center" style="margin:25px auto;">
              <tr>
                <td bgcolor="#EB1C24" style="border-radius:5px;">
                  <a href="{{ $verificationUrl }}" style="display:inline-block;padding:12px 30px;color:#ffffff;text-decoration:none;font-weight:bold;background-color:#EB1C24;border-radius:5px;">Verify</a>
                </td>
              </tr>
            </table>
            <p style="margin:0 0 15px 0;">If you didn’t sign up for Tribe365, you can safely ignore this email.</p>
            <p style="margin-top:20px;">Thank you,<br>The Tribe365 Team</p>
          </td>
        </tr>
        <tr>
          <td align="center" style="padding:15px;font-size:12px;color:#888;border-top:1px solid #e0e0e0;">
            © {{ date('Y') }} <span style="color:#EB1C24;font-weight:bold;">Tribe365 <sup>®</sup></span> — ALL RIGHTS RESERVED<br>
            <a href="mailto:{{ config('app.support_email') }}" style="color:#888;text-decoration:none;">{{ config('app.support_email') }}</a>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
