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
            <p style="margin-bottom:20px;">Hello {{ $user->first_name ?? $user->name }},</p>
            <p style="margin:0 0 15px 0;color:#EB1C24;font-weight:bold;font-size:18px;">🚨 Account Suspended</p>
            <p style="margin:0 0 15px 0;">Your account has been suspended due to payment failure. You will not be able to access your account until payment is received.</p>
            
            @if($invoice)
            <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:15px;margin:20px 0;">
              <p style="margin:0 0 10px 0;font-weight:bold;">Outstanding Invoice:</p>
              <p style="margin:5px 0;"><strong>Invoice #:</strong> {{ $invoice->invoice_number }}</p>
              <p style="margin:5px 0;"><strong>Amount Due:</strong> ${{ number_format($invoice->total_amount, 2) }}</p>
              <p style="margin:5px 0;"><strong>Due Date:</strong> {{ $invoice->due_date->format('M d, Y') }}</p>
            </div>
            @endif

            <div style="background:#f8d7da;border-left:4px solid #dc3545;padding:15px;margin:20px 0;">
              <p style="margin:0;color:#721c24;"><strong>Important:</strong> Your account will be permanently deleted after 37 days of suspension if payment is not received.</p>
            </div>

            <p style="margin:20px 0 15px 0;">To reactivate your account, please complete payment using the link below:</p>
            
            <table cellspacing="0" cellpadding="0" border="0" align="center" style="margin:25px auto;">
              <tr>
                <td bgcolor="#EB1C24" style="border-radius:5px;">
                  <a href="{{ route('account.suspended') }}" style="display:inline-block;padding:12px 30px;color:#ffffff;text-decoration:none;font-weight:bold;background-color:#EB1C24;border-radius:5px;">Reactivate Account</a>
                </td>
              </tr>
            </table>

            <p style="margin-top:20px;">If you have any questions, please contact our support team.</p>
            <p style="margin-top:20px;">Thank you,<br>The Tribe365 Team</p>
          </td>
        </tr>
        <tr>
          <td align="center" style="padding:15px;font-size:12px;color:#888;border-top:1px solid #e0e0e0;">
            © {{ date('Y') }} <span style="color:#EB1C24;font-weight:bold;">Tribe365 <sup>®</sup></span> — ALL RIGHTS RESERVED<br>
            <a href="mailto:{{ config('app.support_email', 'support@tribe365.com') }}" style="color:#888;text-decoration:none;">{{ config('app.support_email', 'support@tribe365.com') }}</a>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>

