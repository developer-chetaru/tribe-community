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

    @if($invoice->status === 'paid' && $invoice->paid_date)
        <div style="margin-top: 20px; padding: 10px; background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">
            <p><strong>Payment Status:</strong> Paid on {{ $invoice->paid_date->format('M d, Y') }}</p>
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

