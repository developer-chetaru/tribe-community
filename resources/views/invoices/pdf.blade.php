<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            border-bottom: 2px solid #EB1C24;
            padding-bottom: 20px;
        }
        .company-info h1 {
            color: #EB1C24;
            margin: 0;
        }
        .invoice-info {
            text-align: right;
        }
        .invoice-details {
            margin: 30px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .total-row {
            font-weight: bold;
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-info">
            <h1>Tribe 365</h1>
            <p>Subscription Invoice</p>
        </div>
        <div class="invoice-info">
            <h2>INVOICE</h2>
            <p><strong>Invoice #:</strong> {{ $invoice->invoice_number }}</p>
            <p><strong>Date:</strong> {{ $invoice->invoice_date->format('M d, Y') }}</p>
            <p><strong>Due Date:</strong> {{ $invoice->due_date->format('M d, Y') }}</p>
        </div>
    </div>

    <div class="invoice-details">
        <h3>Bill To:</h3>
        @if($organisation)
            <p><strong>{{ $organisation->name }}</strong></p>
            @if($organisation->address)
                <p>{{ $organisation->address }}</p>
            @endif
        @elseif(isset($user) && $user)
            <p><strong>{{ $user->first_name }} {{ $user->last_name }}</strong></p>
            <p>{{ $user->email }}</p>
            @if($user->phone)
                <p>{{ $user->phone }}</p>
            @endif
        @else
            <p><strong>N/A</strong></p>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Subscription - {{ $invoice->user_count }} Users</td>
                <td>{{ $invoice->user_count }}</td>
                <td>${{ number_format($invoice->price_per_user, 2) }}</td>
                <td>${{ number_format($invoice->subtotal, 2) }}</td>
            </tr>
            @if($invoice->tax_amount > 0)
            <tr>
                <td colspan="3" style="text-align: right;">Tax:</td>
                <td>${{ number_format($invoice->tax_amount, 2) }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td colspan="3" style="text-align: right;"><strong>Total:</strong></td>
                <td><strong>${{ number_format($invoice->total_amount, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <!-- Subscription Details -->
    @if($subscription)
    <div style="margin-top: 30px; padding: 15px; background-color: #e3f2fd; border: 1px solid #90caf9; border-radius: 4px;">
        <h3 style="margin-top: 0; color: #1976d2;">Subscription Details</h3>
        <table style="margin: 10px 0; background-color: transparent;">
            <tr>
                <td style="border: none; padding: 5px;"><strong>Tier:</strong></td>
                <td style="border: none; padding: 5px;">{{ ucfirst($subscription->tier ?? 'N/A') }}</td>
                <td style="border: none; padding: 5px;"><strong>User Count:</strong></td>
                <td style="border: none; padding: 5px;">{{ $invoice->user_count }}</td>
            </tr>
            <tr>
                <td style="border: none; padding: 5px;"><strong>Price per User:</strong></td>
                <td style="border: none; padding: 5px;">${{ number_format($invoice->price_per_user, 2) }}</td>
                <td style="border: none; padding: 5px;"><strong>Subscription Status:</strong></td>
                <td style="border: none; padding: 5px;">{{ ucfirst($subscription->status ?? 'N/A') }}</td>
            </tr>
        </table>
    </div>
    @endif

    <!-- Payment Details -->
    @php
        $completedPayments = $payments->where('status', 'completed');
    @endphp
    @if($completedPayments->count() > 0)
    <div style="margin-top: 30px; padding: 15px; background-color: #e8f5e9; border: 1px solid #a5d6a7; border-radius: 4px;">
        <h3 style="margin-top: 0; color: #2e7d32;">Payment Details</h3>
        @foreach($completedPayments as $payment)
        <div style="margin-bottom: 20px; padding: 15px; background-color: white; border: 1px solid #c8e6c9; border-radius: 4px;">
            <table style="margin: 0; background-color: transparent; width: 100%;">
                <tr>
                    <td style="border: none; padding: 8px; width: 30%;"><strong>Payment Method:</strong></td>
                    <td style="border: none; padding: 8px;">{{ ucfirst($payment->payment_method ?? 'Stripe') }}</td>
                    <td style="border: none; padding: 8px; width: 30%;"><strong>Amount:</strong></td>
                    <td style="border: none; padding: 8px;">${{ number_format($payment->amount, 2) }}</td>
                </tr>
                <tr>
                    <td style="border: none; padding: 8px;"><strong>Transaction ID:</strong></td>
                    <td style="border: none; padding: 8px; font-size: 11px; word-break: break-all;">{{ $payment->transaction_id ?? 'N/A' }}</td>
                    <td style="border: none; padding: 8px;"><strong>Payment Date:</strong></td>
                    <td style="border: none; padding: 8px;">{{ $payment->payment_date ? $payment->payment_date->format('M d, Y') : $payment->created_at->format('M d, Y') }}</td>
                </tr>
                @if($payment->paidBy)
                <tr>
                    <td style="border: none; padding: 8px;"><strong>Paid By:</strong></td>
                    <td style="border: none; padding: 8px;" colspan="3">{{ $payment->paidBy->name ?? $payment->paidBy->email }}</td>
                </tr>
                @endif
            </table>
            
            <!-- Card Details (if Stripe payment) -->
            @if($payment->payment_method === 'stripe' && (isset($payment->stripe_card_brand) || isset($payment->stripe_card_last4)))
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                <h4 style="margin: 0 0 10px 0; color: #555; font-size: 14px;">Card Details</h4>
                <table style="margin: 0; background-color: transparent; width: 100%;">
                    @if(isset($payment->stripe_card_brand))
                    <tr>
                        <td style="border: none; padding: 5px; width: 30%;"><strong>Card Brand:</strong></td>
                        <td style="border: none; padding: 5px;">{{ ucfirst($payment->stripe_card_brand) }}</td>
                    </tr>
                    @endif
                    @if(isset($payment->stripe_card_last4))
                    <tr>
                        <td style="border: none; padding: 5px;"><strong>Card Number:</strong></td>
                        <td style="border: none; padding: 5px;">**** **** **** {{ $payment->stripe_card_last4 }}</td>
                    </tr>
                    @endif
                    @if(isset($payment->stripe_card_exp_month) && isset($payment->stripe_card_exp_year))
                    <tr>
                        <td style="border: none; padding: 5px;"><strong>Expiry Date:</strong></td>
                        <td style="border: none; padding: 5px;">{{ str_pad($payment->stripe_card_exp_month, 2, '0', STR_PAD_LEFT) }}/{{ $payment->stripe_card_exp_year }}</td>
                    </tr>
                    @endif
                </table>
            </div>
            @endif
            
            @if($payment->payment_notes)
            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">
                <p style="margin: 0; font-size: 12px; color: #666;"><strong>Notes:</strong> {{ $payment->payment_notes }}</p>
            </div>
            @endif
        </div>
        @endforeach
    </div>
    @endif

    <!-- Payment Status -->
    @if($invoice->status === 'paid' && $invoice->paid_date)
        <div style="margin-top: 20px; padding: 10px; background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">
            <p style="margin: 0;"><strong>Payment Status:</strong> Paid on {{ $invoice->paid_date->format('M d, Y') }}</p>
        </div>
    @elseif($invoice->status === 'pending')
        <div style="margin-top: 20px; padding: 10px; background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">
            <p style="margin: 0;"><strong>Payment Status:</strong> Pending</p>
        </div>
    @endif

    <!-- Organisation Details -->
    @if($organisation)
    <div style="margin-top: 30px; padding: 15px; background-color: #f5f5f5; border: 1px solid #ddd; border-radius: 4px;">
        <h3 style="margin-top: 0; color: #333;">Organisation Details</h3>
        <table style="margin: 10px 0; background-color: transparent;">
            <tr>
                <td style="border: none; padding: 5px;"><strong>Organisation Name:</strong></td>
                <td style="border: none; padding: 5px;">{{ $organisation->name }}</td>
            </tr>
            @if($organisation->admin_email)
            <tr>
                <td style="border: none; padding: 5px;"><strong>Admin Email:</strong></td>
                <td style="border: none; padding: 5px;">{{ $organisation->admin_email }}</td>
            </tr>
            @endif
        </table>
    </div>
    @endif

    @if($invoice->notes)
        <div class="footer">
            <p><strong>Notes:</strong> {{ $invoice->notes }}</p>
        </div>
    @endif

    <div class="footer">
        <p>Thank you for your business!</p>
    </div>
</body>
</html>

