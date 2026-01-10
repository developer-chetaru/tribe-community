<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payment Reminder</title>
    <style>
        @media only screen and (max-width: 600px) {
            .red-bar { width: 100% !important; }
            .container { padding: 10px !important; }
        }
    </style>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; padding: 0; margin: 0; background: #F9F9F9;">
    <div class="red-bar" style="height: 6px; width: 62%; background-color: #EB1C24; margin: 0 auto; border-radius: 4px;"></div>
    <div style="max-width: 500px; margin: auto; background: #FFFFFF; padding: 20px; border-radius: 0 0 8px 8px; color: black;">
        <img src="{{ asset('images/logo-tribe.png') }}" alt="Tribe365 Logo" style="width:120px; max-width:160px; margin: 0 auto 15px auto; display:block;" />
        
        <h2 style="margin-bottom: 20px; color: #f97316;">Payment Reminder</h2>
        
        <p style="margin: 0 0 15px 0; color: black;">Hi {{ $user->first_name ?? 'there' }},</p>
        <p style="margin: 0 0 15px 0; color: black;">This is a friendly reminder that you have an outstanding payment for your Tribe365 subscription. You have <strong>{{ $daysRemaining }} days</strong> remaining to complete your payment.</p>
        
        <div style="background: #fff7ed; border-left: 4px solid #f97316; border-radius: 5px; padding: 15px; margin: 20px 0;">
            <table width="100%" cellspacing="0" cellpadding="5" border="0">
                <tr>
                    <td style="color: #666; font-size: 14px;">Invoice Number:</td>
                    <td align="right" style="color: #333; font-weight: bold; font-size: 14px;">{{ $invoice->invoice_number ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td style="color: #666; font-size: 14px;">Amount Due:</td>
                    <td align="right" style="color: #333; font-weight: bold; font-size: 14px;">{{ $amount }}</td>
                </tr>
                <tr>
                    <td style="color: #666; font-size: 14px;">Due Date:</td>
                    <td align="right" style="color: #333; font-size: 14px;">{{ $invoice->due_date ? $invoice->due_date->format('M d, Y') : 'N/A' }}</td>
                </tr>
                <tr>
                    <td style="color: #666; font-size: 14px;">Days Remaining:</td>
                    <td align="right" style="color: #f97316; font-weight: bold; font-size: 14px;">{{ $daysRemaining }} days</td>
                </tr>
            </table>
        </div>
        
        <p style="margin: 0 0 15px 0; color: black;">Please update your payment method to avoid any service interruption.</p>
        
        <div style="text-align: center; margin: 25px 0;">
            <a href="{{ $paymentUrl }}" style="display: inline-block; padding: 12px 30px; background: #EB1C24; color: white; border-radius: 5px; text-decoration: none; font-weight: bold;">Pay Now</a>
        </div>
        
        <p style="margin: 0 0 15px 0; color: black; font-size: 14px;">If you have any questions, please don't hesitate to contact our support team.</p>
        <p style="margin-top: 20px; color: black;">Best regards,<br>The Tribe365 Team</p>
        
        <hr style="border: none; border-top: 1px solid #ccc; margin: 20px 0;">
        <p style="font-size: 12px; color: #888; text-align: center;">
            © {{ date('Y') }} <span style="color: #EB1C24; font-weight: bold;">Tribe365 <sup>®</sup></span> - ALL RIGHTS RESERVED<br>
            <a href="mailto:{{ config('app.support_email', 'support@tribe365.co') }}" style="color: #888; text-decoration: none;">{{ config('app.support_email', 'support@tribe365.co') }}</a>
        </p>
    </div>
</body>
</html>

