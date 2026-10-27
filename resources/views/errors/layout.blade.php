<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ config('lab.name', 'AccuCare Pathology Lab') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f8fafc; color: #1e293b;
               font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; padding: 24px; }
        .box { max-width: 440px; text-align: center; }
        .code { font-size: 13px; font-weight: 700; letter-spacing: .2em; color: #0d847b; text-transform: uppercase; }
        h1 { font-size: 26px; color: #0b2a4a; margin: 10px 0 8px; letter-spacing: -.01em; }
        p { color: #64748b; line-height: 1.6; margin: 0 0 24px; }
        a { display: inline-block; background: #0b2a4a; color: #fff; text-decoration: none; padding: 10px 18px; border-radius: 10px; font-weight: 600; font-size: 14px; }
        a:hover { background: #163256; }
        svg { width: 56px; height: 56px; margin-bottom: 20px; }
    </style>
</head>
<body>
<main class="box">
    <svg viewBox="0 0 64 64" aria-hidden="true">
        <rect width="64" height="64" rx="16" fill="#0B2A4A"/>
        <path d="M25 10H39V25H54V39H39V54H25V39H10V25H25Z" fill="#14B8A6"/>
        <path d="M28 20H36V29.5L43.2 42.2C44.3 44.2 42.9 46.6 40.6 46.6H23.4C21.1 46.6 19.7 44.2 20.8 42.2L28 29.5Z" fill="#fff"/>
        <path d="M24.3 37.6H39.7L42.4 42.4C42.9 43.4 42.2 44.6 41.1 44.6H22.9C21.8 44.6 21.1 43.4 21.6 42.4Z" fill="#0EA5A4"/>
    </svg>
    <div class="code">Error @yield('code')</div>
    <h1>@yield('title')</h1>
    <p>@yield('message')</p>
    <a href="{{ url('/dashboard') }}">Back to dashboard</a>
</main>
</body>
</html>
