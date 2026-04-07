<!DOCTYPE html>
<html>
<head>
    <title>Test Stripe Redirect</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>
    <h1>Test Stripe Redirect</h1>
    
    <div style="margin: 20px 0;">
        <h2>Test 1: Direct Link</h2>
        <a href="https://checkout.stripe.com/test" target="_blank" style="padding: 10px; background: red; color: white; text-decoration: none;">
            Direct Link to Stripe (New Tab)
        </a>
    </div>
    
    <div style="margin: 20px 0;">
        <h2>Test 2: Form POST to Route</h2>
        <form method="POST" action="{{ route('basecamp.checkout.create') }}" id="test-form">
            @csrf
            <input type="hidden" name="invoice_id" value="{{ request('invoice_id', 1) }}">
            <input type="hidden" name="user_id" value="{{ request('user_id', auth()->id() ?? 1) }}">
            <button type="submit" style="padding: 10px; background: blue; color: white;">
                Form POST to Route
            </button>
        </form>
        <script>
            document.getElementById('test-form').addEventListener('submit', function(e) {
                console.log('Form submitting...', {
                    action: this.action,
                    method: this.method,
                });
            });
        </script>
    </div>
    
    <div style="margin: 20px 0;">
        <h2>Test 3: JavaScript Redirect</h2>
        <button onclick="testRedirect()" style="padding: 10px; background: green; color: white;">
            JavaScript Redirect
        </button>
        <script>
            function testRedirect() {
                window.location.href = 'https://checkout.stripe.com/test';
            }
        </script>
    </div>
    
    <div style="margin: 20px 0;">
        <h2>Test 4: Livewire Method</h2>
        <livewire:basecamp-billing :user_id="{{ request('user_id', auth()->id() ?? 1) }}" />
    </div>
    
    <div style="margin: 20px 0;">
        <h2>Test 5: Direct PHP Redirect (via route)</h2>
        <a href="{{ route('basecamp.checkout.redirect') }}?test=1" style="padding: 10px; background: orange; color: white; text-decoration: none;">
            Test Redirect Route
        </a>
    </div>
    
    <div style="margin: 20px 0;">
        <h2>Debug Info:</h2>
        <pre>
Invoice ID: {{ request('invoice_id', 'Not provided') }}
User ID: {{ request('user_id', auth()->id() ?? 'Not provided') }}
Route exists: {{ route('basecamp.checkout.create') }}
        </pre>
    </div>
</body>
</html>

