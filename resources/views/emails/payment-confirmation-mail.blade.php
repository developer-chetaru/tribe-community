<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payment Confirmation</title>
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
        
        <h2 style="margin-bottom: 20px; color: black;">Payment Confirmation</h2>
        
        <p style="margin: 0 0 15px 0; color: black;">Hi {{ $user->first_name ?? 'there' }},</p>
        <p style="margin: 0 0 15px 0; color: black;">Thank you for your payment! Your Tribe365 subscription payment has been successfully processed.</p>
        
        <div style="background: #f5f5f5; border-radius: 5px; padding: 15px; margin: 20px 0;">
            <table width="100%" cellspacing="0" cellpadding="5" border="0">
                <tr>
                    <td style="color: #666; font-size: 14px;">Invoice Number:</td>
                    <td align="right" style="color: #333; font-weight: bold; font-size: 14px;">{{ $invoice->invoice_number ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td style="color: #666; font-size: 14px;">Amount Paid:</td>
                    <td align="right" style="color: #333; font-weight: bold; font-size: 14px;">{{ $amount }}</td>
                </tr>
                <tr>
                    <td style="color: #666; font-size: 14px;">Payment Date:</td>
                    <td align="right" style="color: #333; font-weight: bold; font-size: 14px;">{{ $invoice->paid_date ? $invoice->paid_date->format('M d, Y') : now()->format('M d, Y') }}</td>
                </tr>
                @if($payment && $payment->transaction_id)
                <tr>
                    <td style="color: #666; font-size: 14px;">Transaction ID:</td>
                    <td align="right" style="color: #333; font-size: 12px;">{{ $payment->transaction_id }}</td>
                </tr>
                @endif
                @if($billingPeriod && $billingPeriod !== 'N/A')
                <tr>
                    <td style="color: #666; font-size: 14px;">Billing Period:</td>
                    <td align="right" style="color: #333; font-size: 14px;">{{ $billingPeriod }}</td>
                </tr>
                @endif
            </table>
        </div>
        
        <p style="margin: 0 0 15px 0; color: black;">Your subscription is now active and you can continue using all Tribe365 features.</p>
        
        <div style="text-align: center; margin: 25px 0;">
            <a href="{{ url('/dashboard') }}" style="display: inline-block; padding: 12px 30px; background: #EB1C24; color: white; border-radius: 5px; text-decoration: none; font-weight: bold;">Go to Dashboard</a>
        </div>
        
        <p style="margin: 0 0 15px 0; color: black;">If you have any questions about your subscription, please don't hesitate to contact us.</p>
        <p style="margin-top: 20px; color: black;">Thank you for being part of Tribe365!<br>The Tribe365 Team</p>
        
        <hr style="border: none; border-top: 1px solid #ccc; margin: 20px 0;">
        <p style="font-size: 12px; color: #888; text-align: center;">
            © {{ date('Y') }} <span style="color: #EB1C24; font-weight: bold;">Tribe365 <sup>®</sup></span> - ALL RIGHTS RESERVED<br>
            <a href="mailto:{{ config('app.support_email', 'support@tribe365.co') }}" style="color: #888; text-decoration: none;">{{ config('app.support_email', 'support@tribe365.co') }}</a>
        </p>
    </div>
</body>
</html>

