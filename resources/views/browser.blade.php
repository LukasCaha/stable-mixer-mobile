<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stable Mixer</title>
    <link rel="icon" href="{{ asset('icon.png') }}">
    <style>
        @font-face {
            font-family: "Bricolage Grotesque";
            font-weight: 400 700;
            font-display: swap;
            src: url("{{ asset('brand/bricolage.woff2') }}") format("woff2");
            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
        }
        @font-face {
            font-family: "DM Mono";
            font-weight: 500;
            font-display: swap;
            src: url("{{ asset('brand/dm-mono-500.woff2') }}") format("woff2");
            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
        }
        :root {
            --bg: #f6f3ec;
            --ink: #1c1b18;
            --muted: #5c574e;
            --green: #3e4c3c;
            --sage: #5c6b54;
            --line: #e6dfd2;
            --card: #fffcf7;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: var(--ink);
            font-family: "Bricolage Grotesque", "Segoe UI", sans-serif;
            display: grid;
            place-items: center;
            padding: 32px 20px;
        }
        main {
            width: min(420px, 100%);
        }
        .photo {
            width: 100%;
            height: 220px;
            object-fit: cover;
            object-position: center 58%;
            border-radius: 28px;
            display: block;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 22px;
        }
        .brand img {
            width: 64px;
            height: 64px;
            object-fit: contain;
        }
        .eyebrow {
            margin: 0;
            font-family: "DM Mono", monospace;
            font-size: 0.72rem;
            font-weight: 500;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--sage);
        }
        h1 {
            margin: 4px 0 0;
            font-size: 2.4rem;
            font-weight: 560;
            letter-spacing: -0.03em;
            line-height: 0.98;
        }
        p {
            margin: 16px 0 0;
            color: var(--muted);
            line-height: 1.5;
        }
        .card {
            margin-top: 22px;
            padding: 16px 18px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 18px;
            box-shadow: 0 14px 40px rgba(40, 36, 28, 0.06);
        }
        .card p { margin: 0; color: var(--ink); }
    </style>
</head>
<body>
    <main>
        <img class="photo" src="{{ asset('brand/yard.jpg') }}" alt="A grey horse in the yard">
        <div class="brand">
            <img src="{{ asset('brand/mark.png') }}" alt="">
            <div>
                <p class="eyebrow">Stable Mixer</p>
                <h1>On the phone</h1>
            </div>
        </div>
        <div class="card">
            <p>Stable Mixer companion runs on the phone. Build the Android app to record.</p>
        </div>
    </main>
</body>
</html>
