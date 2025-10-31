<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .status-badge {
            text-transform: uppercase;
            letter-spacing: .02em;
        }
    </style>
</head>

<body class="bg-light">
    <nav class="navbar navbar-expand-lg bg-white border-bottom mb-4">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="{{ route('uploads.index') }}">CSV Uploader</a>
        </div>
    </nav>

    <main class="container mb-5">
        @yield('content')
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @yield('scripts')
</body>

</html>
