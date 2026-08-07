{{--
    The shell the Terms and the Privacy Policy share.

    A plain Blade page rather than an Inertia one on purpose. These two URLs go
    into App Store Connect and into the app's sign-up screen, which means they
    have to answer for anyone — Apple's reviewer, a crawler, somebody on a bad
    connection — without a JavaScript bundle having to boot first. Nothing here
    needs the SPA, so nothing here should depend on it.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>@yield('title') · {{ config('app.name') }}</title>
    <meta name="description" content="@yield('description')" />
    <link rel="icon" href="/favicon.ico" sizes="any" />
    <style>
        :root {
            --forest: #0F3D2E;
            --primary: #2E7D56;
            --muted: #4F7263;
            --page: #F4F7F2;
            --card: #FFFFFF;
            --border: #E1E9DE;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--page);
            color: var(--forest);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            font-size: 16px;
            line-height: 1.65;
            -webkit-font-smoothing: antialiased;
        }

        .wrap { max-width: 720px; margin: 0 auto; padding: 40px 20px 80px; }

        header { text-align: center; margin-bottom: 32px; }

        .wordmark {
            font-family: Georgia, 'Times New Roman', serif;
            font-size: 26px;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: var(--forest);
            text-decoration: none;
        }

        main {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 40px 32px;
        }

        h1 { font-family: Georgia, 'Times New Roman', serif; font-size: 28px; line-height: 1.25; margin: 0 0 6px; }

        .updated { color: var(--muted); font-size: 14px; margin: 0 0 32px; }

        h2 {
            font-size: 18px;
            margin: 32px 0 8px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
        }
        h2:first-of-type { border-top: 0; padding-top: 0; margin-top: 0; }

        p, li { color: #21382E; }
        ul { padding-left: 22px; }
        li { margin-bottom: 6px; }

        a { color: var(--primary); }

        /* The clause Apple looks for on any app carrying user content. Marked
           out so a reviewer skimming the page finds it without reading it. */
        .callout {
            background: var(--page);
            border: 1px solid var(--border);
            border-left: 3px solid var(--primary);
            border-radius: 10px;
            padding: 16px 20px;
            margin: 20px 0;
        }
        .callout p:last-child { margin-bottom: 0; }
        .callout p:first-child { margin-top: 0; }

        footer { text-align: center; margin-top: 28px; color: var(--muted); font-size: 13px; }
        footer a { color: var(--muted); }

        @media (max-width: 560px) {
            .wrap { padding: 28px 16px 60px; }
            main { padding: 28px 20px; border-radius: 14px; }
            h1 { font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <header>
            <a class="wordmark" href="{{ url('/') }}">{{ config('app.name') }}</a>
        </header>

        <main>
            <h1>@yield('title')</h1>
            <p class="updated">Last updated {{ $updatedAt }}</p>

            @yield('body')
        </main>

        <footer>
            <a href="{{ route('legal.terms') }}">Terms of Service</a>
            &nbsp;·&nbsp;
            <a href="{{ route('legal.privacy') }}">Privacy Policy</a>
        </footer>
    </div>
</body>
</html>
