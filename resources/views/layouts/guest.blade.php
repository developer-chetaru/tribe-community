<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tribe365</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite('resources/css/app.css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
   <link rel="icon" type="image/png" href="{{ asset('images/favicon-icon.png') }}">

    @livewireStyles
</head>
<body class="bg-gray-100">
    <div class="">
        {{ $slot }}
    </div>
 
    @livewireScripts
    <script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
