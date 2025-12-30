<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #333;
            background-color: #f5f5f5;
        }
        .top-header {
            background-color: #e5e5e5;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .balance-section {
            flex: 1;
            min-width: 200px;
        }
        .balance-amount {
            font-size: 36px;
            font-weight: bold;
            color: #333;
            margin: 0;
        }
        .balance-label {
            color: #ff6600;
            font-size: 14px;
            font-weight: 600;
            margin-top: 5px;
        }
        .invoice-meta {
            display: flex;
            gap: 30px;
            align-items: center;
            flex-wrap: wrap;
        }
        .meta-item {
            font-size: 14px;
        }
        .meta-label {
            color: #666;
            font-weight: 500;
        }
        .meta-value {
            color: #333;
            font-weight: 600;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-left: 20px;
        }
        .action-btn {
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            text-decoration: none;
        }
        .action-btn:hover {
            background: #f5f5f5;
            border-color: #bbb;
        }
        .action-btn.print, .action-btn.download {
            width: 40px;
            height: 40px;
        }
        .action-btn.pay {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
            padding: 10px 20px;
            font-weight: 600;
        }
        .action-btn.pay:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .email-payment-section {
            margin-top: 30px;
            padding: 25px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
        }
        .email-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .email-input-group {
            flex: 1;
            min-width: 250px;
        }
        .email-input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        .email-input-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
            transition: border-color 0.3s;
        }
        .email-input-group input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }
        .pay-button-wrapper {
            margin-top: 20px;
        }
        .pay-button-wrapper .action-btn.pay {
            width: 100%;
            justify-content: center;
            padding: 14px 30px;
            font-size: 16px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background-color: white;
            padding: 40px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
        @media print {
            .top-header, .action-buttons, .email-payment-section {
                display: none;
            }
        }
    </style>
    <script>
        function validateEmail(form) {
            const email = form.querySelector('#customer_email').value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (!email || !emailRegex.test(email)) {
                alert('Please enter a valid email address');
                form.querySelector('#customer_email').focus();
                return false;
            }
            return true;
        }
    </script>
</head>
<body>
    <!-- Top Header with Balance and Actions -->
    <div class="top-header">
        <div class="balance-section">
            <div class="balance-amount">${{ number_format($invoice->total_amount, 2) }}</div>
            <div class="balance-label">Balance Due</div>
        </div>
        <div class="invoice-meta">
            <div class="meta-item">
                <span class="meta-label">Invoice #:</span>
                <span class="meta-value">{{ $invoice->invoice_number }}</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Due Date:</span>
                <span class="meta-value">{{ $invoice->due_date->format('d/m/Y') }}</span>
            </div>
        </div>
        <div class="action-buttons">
            <button class="action-btn print" onclick="window.print()" title="Print">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                </svg>
            </button>
            <a href="{{ route('invoices.shared', ['token' => $token, 'download' => 1]) }}" class="action-btn download" title="Download">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
            </a>
            @if($invoice->status === 'pending')
            <button type="button" class="action-btn pay" onclick="document.getElementById('email-payment-form').scrollIntoView({behavior: 'smooth', block: 'center'}); setTimeout(() => document.getElementById('customer_email').focus(), 300);">
                Pay Now
            </button>
            @endif
        </div>
    </div>

    <div class="container">
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
            <p><strong>{{ $organisation->name }}</strong></p>
            @if($organisation->address)
                <p>{{ $organisation->address }}</p>
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
            </div>
            @endforeach
        </div>
        @endif

        <!-- Payment Status -->
        @if($invoice->status === 'paid' && $invoice->paid_date)
            <div style="margin-top: 20px; padding: 15px; background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">
                <p style="margin: 0; font-size: 16px; color: #155724;"><strong>✓ Payment Status:</strong> Paid on {{ $invoice->paid_date->format('M d, Y') }}</p>
            </div>
        @elseif($invoice->status === 'pending')
            <!-- Email Payment Section -->
            <div class="email-payment-section" id="email-payment-form">
                <h3 style="margin-top: 0; margin-bottom: 20px; color: #333; font-size: 18px;">Pay Invoice</h3>
                <form method="GET" action="{{ route('invoices.shared.pay', ['token' => $token]) }}" onsubmit="return validateEmail(this);">
                    <div class="email-form">
                        <div class="email-input-group">
                            <label for="customer_email">Email Address *</label>
                            <input 
                                type="email" 
                                id="customer_email" 
                                name="email" 
                                placeholder="Enter your email address"
                                value="{{ old('email', request('email')) }}"
                                required
                                autocomplete="email"
                            >
                            <small style="display: block; margin-top: 5px; color: #666; font-size: 12px;">
                                We'll use this email to send payment confirmation
                            </small>
                        </div>
                    </div>
                    <div class="pay-button-wrapper">
                        <button type="submit" class="action-btn pay">
                            Pay ${{ number_format($invoice->total_amount, 2) }}
                        </button>
                    </div>
                </form>
            </div>
        @endif

        <!-- Success/Error Messages -->
        @if(session('success'))
            <div style="margin-top: 20px; padding: 15px; background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">
                <p style="margin: 0; color: #155724; font-weight: bold;">{{ session('success') }}</p>
            </div>
        @endif

        @if(session('error'))
            <div style="margin-top: 20px; padding: 15px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">
                <p style="margin: 0; color: #721c24; font-weight: bold;">{{ session('error') }}</p>
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
    </div>
</body>
</html>

