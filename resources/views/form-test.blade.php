<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Form Unsaved Changes Alert</title>
</head>
<body>
    <h2>Form with Unsaved Changes Alert</h2>

    @if(session('status'))
        <p style="color:green;">{{ session('status') }}</p>
    @endif

    <form id="myForm" method="POST" action="{{ url('/form-test') }}">
        @csrf
        <div>
            <label>Name:</label>
            <input type="text" name="name">
        </div>
        <div>
            <label>Message:</label>
            <textarea name="message"></textarea>
        </div>
        <button type="submit">Save</button>
    </form>

    <script>
        let formChanged = false;

        // Track changes
        document.querySelectorAll("#myForm input, #myForm textarea, #myForm select").forEach(el => {
            el.addEventListener("input", () => formChanged = true);
            el.addEventListener("change", () => formChanged = true);
        });

        // Warn on refresh/close/back
        window.addEventListener("beforeunload", function (e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = ""; // Needed for Chrome
            }
        });

        // Prevent back navigation if unsaved
        window.addEventListener("popstate", function (e) {
            if (formChanged && !confirm("You have unsaved changes. Do you really want to leave?")) {
                history.pushState(null, null, window.location.href); // Stay on page
            }
        });

        // Push initial state so popstate works
        history.pushState(null, null, window.location.href);

        // Reset flag on submit
        document.getElementById("myForm").addEventListener("submit", () => {
            formChanged = false;
        });
    </script>
</body>
</html>