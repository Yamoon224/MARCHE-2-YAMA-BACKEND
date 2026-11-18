<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>API Marché de Yama</title>
    <meta name="description" content="Backend du supermarché en ligne Marché de Yama : API REST, modules métier et documentation.">
    <link rel="icon" type="image/png" href="/brand/icon.png">
    <link rel="stylesheet" href="/css/brand.css">
    <style>
        .hero { background: var(--brand-green); color: #fff; }
        .hero__inner { max-width: 1200px; margin: 0 auto; padding: 56px 16px 64px; display: grid; gap: 28px; align-items: center; }
        .hero h1 { font-size: clamp(2rem, 6vw, 3.4rem); max-width: 16ch; }
        .hero p { font-size: 1.1rem; max-width: 56ch; color: #e6f3ea; margin: 16px 0 28px; }
        .hero .btn { background: var(--brand-gold); border-color: var(--brand-gold); color: var(--foreground); }
        .hero .btn:hover { background: #ffd23d; border-color: #ffd23d; }
        .hero .btn--ghost { background: transparent; color: #fff; border-color: #fff; }
        .hero .btn--ghost:hover { background: #fff; color: var(--brand-green); }
        .hero__actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .hero__logo { display: none; }
        .status { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 24px; }
        .pill { display: inline-flex; align-items: center; gap: 8px; background: #ffffff1f; border-radius: 999px; padding: 6px 14px; font-size: .85rem; font-weight: 600; }
        .dot { width: 10px; height: 10px; border-radius: 50%; background: var(--brand-gold); }
        .dot--up { background: #5ee08b; }
        .dot--down { background: #ff6b7d; }
        main { max-width: 1200px; margin: 0 auto; padding: 48px 16px 32px; }
        section + section { margin-top: 56px; }
        h2 { font-size: clamp(1.5rem, 4vw, 2rem); padding-bottom: 10px; border-bottom: 4px solid var(--brand-red); display: inline-block; margin-bottom: 24px; }
        .grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); }
        .card { background: var(--card); border: 1px solid var(--line); border-bottom: 4px solid var(--brand-green); border-radius: 18px; padding: 20px; box-shadow: var(--shadow-soft); }
        .card h3 { font-size: 1.1rem; margin-bottom: 6px; }
        .card p { margin: 0; color: var(--muted); font-size: .95rem; }
        .card a.link { display: inline-block; margin-top: 12px; font-weight: 700; }
        .steps { counter-reset: s; list-style: none; padding: 0; margin: 0; display: grid; gap: 12px; }
        .steps li { counter-increment: s; background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 14px 16px 14px 56px; position: relative; }
        .steps li::before { content: counter(s); position: absolute; left: 14px; top: 12px; width: 30px; height: 30px; border-radius: 50%; background: var(--brand-red); color: #fff; font-weight: 800; display: grid; place-items: center; }
        code { background: #fff; border: 1px solid var(--line); border-radius: 6px; padding: 2px 6px; font-size: .88em; }
        footer { text-align: center; color: var(--muted); font-size: .85rem; padding: 32px 16px 48px; }
        @media (min-width: 900px) {
            .hero__inner { grid-template-columns: 1fr 280px; padding: 80px 16px; }
            .hero__logo { display: block; width: 100%; background: #fff; border-radius: 28px; padding: 24px; box-shadow: var(--shadow-soft); }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="site-header__inner">
            <a class="brand" href="/">
                <img src="/brand/logo.png" alt="" width="44" height="44">
                <span><strong>Marché de Yama</strong><small>Backend &amp; API</small></span>
            </a>
            <nav aria-label="Navigation principale">
                <a class="btn btn--ghost" href="/api/documentation">Documentation API</a>
            </nav>
        </div>
    </header>

    <div class="hero">
        <div class="hero__inner">
            <div>
                <h1>Le cœur du supermarché en ligne</h1>
                <p>Une API REST unique alimente la vitrine, le backoffice, la préparation, la livraison et la caisse : catalogue, commandes, paiements, stock, facturation et comptabilité.</p>
                <div class="hero__actions">
                    <a class="btn" href="/api/documentation">Explorer l'API (Swagger)</a>
                    <a class="btn btn--ghost" href="/docs?api-docs.json">Spécification OpenAPI</a>
                </div>
                <div class="status" role="status">
                    <span class="pill"><span class="dot dot--up"></span>API v{{ $version }}</span>
                    <span class="pill"><span class="dot {{ $database ? 'dot--up' : 'dot--down' }}"></span>Base de données {{ $database ? 'connectée' : 'indisponible' }}</span>
                    <span class="pill"><span class="dot"></span>Environnement : {{ $environment }}</span>
                </div>
            </div>
            <img class="hero__logo" src="/brand/logo.png" alt="Logo Marché de Yama" width="280" height="255">
        </div>
    </div>

    <main>
        <section aria-labelledby="modules">
            <h2 id="modules">Modules métier</h2>
            <div class="grid">
                @foreach ($modules as $module)
                    <article class="card">
                        <h3>{{ $module['name'] }}</h3>
                        <p>{{ $module['description'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="apps">
            <h2 id="apps">Applications connectées</h2>
            <div class="grid">
                <article class="card">
                    <h3>Vitrine</h3>
                    <p>Le site public où les clients commandent.</p>
                    <a class="link" href="{{ $storeUrl }}">Ouvrir la vitrine</a>
                </article>
                <article class="card">
                    <h3>Backoffice</h3>
                    <p>Gestion du personnel : commandes, catalogue, stock, livraison, rapports.</p>
                    <a class="link" href="{{ $backofficeUrl }}">Ouvrir le backoffice</a>
                </article>
                <article class="card">
                    <h3>Documentation</h3>
                    <p>Tous les points d'accès, essayables en ligne avec un jeton.</p>
                    <a class="link" href="/api/documentation">Ouvrir Swagger</a>
                </article>
            </div>
        </section>

        <section aria-labelledby="start">
            <h2 id="start">Utiliser l'API</h2>
            <ol class="steps">
                <li>Connectez-vous avec <code>POST /api/v1/auth/login</code> pour obtenir un jeton.</li>
                <li>Envoyez-le dans l'en-tête <code>Authorization: Bearer &lt;jeton&gt;</code> (bouton « Authorize » de la documentation).</li>
                <li>Les montants sont en centimes (<code>*_minor</code>) ; les réponses paginées contiennent <code>data</code> et <code>meta</code>.</li>
            </ol>
        </section>
    </main>

    <footer>Marché de Yama · API v{{ $version }}</footer>
</body>
</html>
