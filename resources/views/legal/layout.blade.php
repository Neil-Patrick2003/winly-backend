<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ $appName }}</title>
    <meta name="robots" content="index, follow">
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #F4F7F2;
            color: #12372A;
            font: 16px/1.65 -apple-system, BlinkMacSystemFont, "Segoe UI", Inter, Roboto, sans-serif;
        }
        main { max-width: 720px; margin: 0 auto; padding: 32px 20px 64px; }
        .brand { color: #2E7D56; font-weight: 700; font-size: 22px; letter-spacing: -0.01em; }
        h1 { font-size: 30px; line-height: 1.2; margin: 18px 0 4px; }
        .updated { color: #5B7268; font-size: 14px; margin: 0 0 28px; }
        h2 { font-size: 19px; margin: 32px 0 8px; }
        p, li { color: #2B4A3E; }
        ul, ol { padding-left: 22px; }
        li { margin: 4px 0; }
        a { color: #2E7D56; }
        .card { background: #fff; border: 1px solid #E1E8DD; border-radius: 16px; padding: 4px 20px; margin-top: 8px; }
        footer { margin-top: 40px; color: #5B7268; font-size: 14px; }
    </style>
</head>
<body>
<main>
    <div class="brand">{{ $appName }}</div>
    <h1>@yield('title')</h1>
    <p class="updated">Last updated {{ $updated }}</p>

    @yield('content')

    <footer>
        <a href="{{ route('legal.privacy') }}">Privacy Policy</a>
        &nbsp;·&nbsp;
        <a href="{{ route('legal.terms') }}">Terms of Service</a>
        &nbsp;·&nbsp;
        <a href="{{ route('legal.delete-account') }}">Delete your account</a>
    </footer>
</main>
</body>
</html>
