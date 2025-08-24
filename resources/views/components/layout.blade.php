<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        <meta name="user-authenticated" content="true">
    @endauth

    <title>{{ $title ?? 'FlexiConvert - File Conversion Made Easy' }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- CSRF Token Refresh Script -->
    <script>
        // Simple CSRF token refresh without external file
        document.addEventListener('DOMContentLoaded', function() {
            // Refresh CSRF token before form submissions
            document.addEventListener('submit', function(e) {
                const form = e.target;
                if (form.method.toLowerCase() === 'post') {
                    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                    const csrfInput = form.querySelector('input[name="_token"]');
                    if (csrfInput && token) {
                        csrfInput.value = token;
                    }
                }
            });
        });
    </script>
</head>
<body class="font-sans antialiased bg-gray-50">
    <div class="min-h-screen">
        {{ $slot }}
    </div>
</body>
</html>
