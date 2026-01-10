<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Account Suspended</title>
    <style>
        @media only screen and (max-width: 600px) {
            .red-bar { width: 100% !important; }
            .container { padding: 10px !important; }
        }
    </style>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; padding: 0; margin: 0; background: #F9F9F9;">
    <div class="red-bar" style="height: 6px; width: 62%; background-color: #dc2626; margin: 0 auto; border-radius: 4px;"></div>
    <div style="max-width: 500px; margin: auto; background: #FFFFFF; padding: 20px; border-radius: 0 0 8px 8px; color: black;">
        <img src="{{ asset('images/logo-tribe.png') }}" alt="Tribe365 Logo" style="width:120px; max-width:160px; margin: 0 auto 15px auto; display:block;" />
        
        <h2 style="margin-bottom: 20px; color: #dc2626;">⚠️ Account Suspended</h2>
        
        <p style="margin: 0 0 15px 0; color: black;">Hi {{ $user->first_name ?? 'there' }},</p>
        <p style="margin: 0 0 15px 0; color: black;">We regret to inform you that your Tribe365 account has been suspended as of <strong>{{ $suspensionDate }}</strong> due to unpaid subscription fees.</p>
        
        <div style="background: #fef2f2; border: 2px solid #dc2626; border-radius: 5px; padding: 15px; margin: 20px 0;">
            <p style="margin: 0 0 10px 0; color: #dc2626; font-weight: bold;">What this means:</p>
            <ul style="margin: 0; padding-left: 20px; color: black;">
                <li>You no longer have access to Tribe365 features</li>
                <li>Your data will be retained for {{ $dataRetentionDays }} days</li>
                <li>Your account will be permanently deleted on {{ $deletionDate }}</li>
            </ul>
        </div>
        
        @if($outstandingAmount && $outstandingAmount !== '£0.00')
        <div style="background: #f5f5f5; border-radius: 5px; padding: 15px; margin: 20px 0;">
            <table width="100%" cellspacing="0" cellpadding="5" border="0">
                <tr>
                    <td style="color: #666; font-size: 14px;">Outstanding Amount:</td>
                    <td align="right" style="color: #dc2626; font-weight: bold; font-size: 16px;">{{ $outstandingAmount }}</td>
                </tr>
            </table>
        </div>
        @endif
        
        <p style="margin: 0 0 15px 0; color: black;"><strong>To reactivate your account:</strong></p>
        <ol style="margin: 0 0 15px 0; padding-left: 20px; color: black;">
            <li>Update your payment method</li>
            <li>Pay any outstanding invoices</li>
            <li>Your account will be reactivated immediately upon payment</li>
        </ol>
        
        <div style="text-align: center; margin: 25px 0;">
            <a href="{{ $reactivationUrl }}" style="display: inline-block; padding: 12px 30px; background: #EB1C24; color: white; border-radius: 5px; text-decoration: none; font-weight: bold;">Reactivate Account</a>
        </div>
        
        <p style="margin: 0 0 15px 0; color: black; font-size: 14px;"><strong>Important:</strong> If you do not reactivate your account within {{ $dataRetentionDays }} days, your account and all associated data will be permanently deleted and cannot be recovered.</p>
        
        <p style="margin: 0 0 15px 0; color: black; font-size: 14px;">If you have any questions or need assistance, please contact our support team immediately.</p>
        <p style="margin-top: 20px; color: black;">Best regards,<br>The Tribe365 Team</p>
        
        <hr style="border: none; border-top: 1px solid #ccc; margin: 20px 0;">
        <p style="font-size: 12px; color: #888; text-align: center;">
            © {{ date('Y') }} <span style="color: #EB1C24; font-weight: bold;">Tribe365 <sup>®</sup></span> - ALL RIGHTS RESERVED<br>
            <a href="mailto:{{ config('app.support_email', 'support@tribe365.co') }}" style="color: #888; text-decoration: none;">{{ config('app.support_email', 'support@tribe365.co') }}</a>
        </p>
    </div>
</body>
</html>

