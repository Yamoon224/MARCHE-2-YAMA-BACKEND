<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Documentation API · Marché de Yama</title>
    <link rel="stylesheet" type="text/css" href="{{ l5_swagger_asset($documentation, 'swagger-ui.css') }}">
    <link rel="icon" type="image/png" href="/brand/icon.png">
    <link rel="stylesheet" href="/css/brand.css">
    <style>
        body { background: var(--background); }

        .docs-intro { max-width: 1200px; margin: 0 auto; padding: 28px 16px 0; }
        .docs-intro h1 { font-size: clamp(1.6rem, 5vw, 2.4rem); padding-bottom: 10px; border-bottom: 4px solid var(--brand-red); display: inline-block; }
        .docs-intro p { color: var(--muted); max-width: 70ch; margin: 14px 0 0; }

        #swagger-ui { max-width: 1200px; margin: 0 auto; padding: 0 16px 48px; }
        .swagger-ui, .swagger-ui .info .title, .swagger-ui .opblock-tag, .swagger-ui .tab li, .swagger-ui table thead tr th,
        .swagger-ui .parameter__name, .swagger-ui label, .swagger-ui .response-col_status { font-family: var(--font-sans); color: var(--foreground); }
        .swagger-ui .info .title, .swagger-ui .opblock-tag { font-family: var(--font-display); letter-spacing: -0.02em; }
        .swagger-ui .info { margin: 24px 0; }
        .swagger-ui .info .title small.version-stamp { background: var(--brand-green); }
        .swagger-ui .info a, .swagger-ui a.nostyle { color: var(--brand-green); }

        /* Barre de serveur / autorisation en carte à liseré de la couleur principale */
        .swagger-ui .scheme-container { background: var(--card); border-bottom: 4px solid var(--brand-green); border-radius: 18px; box-shadow: var(--shadow-soft); margin: 0 0 24px; padding: 18px 20px; }

        .swagger-ui .opblock-tag { border-bottom: 1px solid var(--line); }
        .swagger-ui .opblock { border-radius: 14px; box-shadow: none; }
        .swagger-ui .opblock .opblock-summary { border-radius: 12px; }
        .swagger-ui .opblock.opblock-get { background: #087b350d; border-color: var(--brand-green); }
        .swagger-ui .opblock.opblock-get .opblock-summary-method { background: var(--brand-green); }
        .swagger-ui .opblock.opblock-get .opblock-summary { border-color: var(--brand-green); }
        .swagger-ui .opblock.opblock-post { background: #f2b70514; border-color: var(--brand-gold); }
        .swagger-ui .opblock.opblock-post .opblock-summary-method { background: #a67c00; }
        .swagger-ui .opblock.opblock-post .opblock-summary { border-color: var(--brand-gold); }
        .swagger-ui .opblock.opblock-put, .swagger-ui .opblock.opblock-patch { background: #20251f0a; border-color: var(--foreground); }
        .swagger-ui .opblock.opblock-put .opblock-summary-method, .swagger-ui .opblock.opblock-patch .opblock-summary-method { background: var(--foreground); }
        .swagger-ui .opblock.opblock-put .opblock-summary, .swagger-ui .opblock.opblock-patch .opblock-summary { border-color: var(--foreground); }
        .swagger-ui .opblock.opblock-delete { background: #c9001b0d; border-color: var(--brand-red); }
        .swagger-ui .opblock.opblock-delete .opblock-summary-method { background: var(--brand-red); }
        .swagger-ui .opblock.opblock-delete .opblock-summary { border-color: var(--brand-red); }

        .swagger-ui .btn { border-radius: 999px; font-weight: 700; box-shadow: none; }
        .swagger-ui .btn.execute { background: var(--brand-green); border-color: var(--brand-green); color: #fff; }
        .swagger-ui .btn.execute:hover { background: var(--brand-green-dark); }
        .swagger-ui .btn.authorize { color: var(--brand-green); border-color: var(--brand-green); }
        .swagger-ui .btn.authorize svg { fill: var(--brand-green); }
        .swagger-ui .btn.cancel { color: var(--brand-red); border-color: var(--brand-red); }
        .swagger-ui .btn-clear { color: var(--brand-red); }
        .swagger-ui input[type=text], .swagger-ui input[type=password], .swagger-ui input[type=search], .swagger-ui textarea, .swagger-ui select { border-radius: 10px; border-color: var(--line); }
        .swagger-ui .filter .operation-filter-input { border: 2px solid var(--line); border-radius: 999px; padding: 8px 16px; }
        .swagger-ui section.models { border-color: var(--line); border-radius: 18px; background: var(--card); }
        .swagger-ui section.models h4 { font-family: var(--font-display); }
        .swagger-ui .responses-inner h4, .swagger-ui .responses-inner h5 { font-family: var(--font-sans); }
        .swagger-ui .loading-container .loading:after { font-family: var(--font-sans); }

        @media (max-width: 640px) {
            .swagger-ui .opblock .opblock-summary-description { display: none; }
            .swagger-ui .wrapper { padding: 0; }
        }
    </style>
</head>

<body>
<header class="site-header">
    <div class="site-header__inner">
        <a class="brand" href="/">
            <img src="/brand/logo.png" alt="" width="44" height="44">
            <span><strong>Marché de Yama</strong><small>Documentation de l'API</small></span>
        </a>
        <nav aria-label="Navigation">
            <a class="btn btn--ghost" href="/">Accueil</a>
            <a class="btn" href="{{ $urlsToDocs[array_key_first($urlsToDocs)] }}">openapi.json</a>
        </nav>
    </div>
</header>

<div class="docs-intro">
    <h1>Documentation de l'API</h1>
    <p>Tous les points d'accès du supermarché en ligne. Connectez-vous avec <strong>POST /api/v1/auth/login</strong>, puis collez le jeton dans « Authorize » pour essayer les routes protégées. Les montants sont en centimes.</p>
</div>

<div id="swagger-ui"></div>

<script src="{{ l5_swagger_asset($documentation, 'swagger-ui-bundle.js') }}"></script>
<script>
    window.onload = function() {
        const urls = [];

        @foreach($urlsToDocs as $title => $url)
            urls.push({name: @json($title), url: @json($url)});
        @endforeach

        const ui = SwaggerUIBundle({
            dom_id: '#swagger-ui',
            url: urls[0].url,
            operationsSorter: @json($operationsSorter ?? null),
            configUrl: @json($configUrl ?? null),
            validatorUrl: @json($validatorUrl ?? null),
            oauth2RedirectUrl: "{{ route('l5-swagger.'.$documentation.'.oauth2_callback', [], $useAbsolutePath) }}",

            requestInterceptor: function(request) {
                request.headers['X-CSRF-TOKEN'] = '{{ csrf_token() }}';
                return request;
            },

            presets: [
                SwaggerUIBundle.presets.apis
            ],

            layout: "BaseLayout",
            docExpansion : @json(config('l5-swagger.defaults.ui.display.doc_expansion', 'none')),
            deepLinking: true,
            filter: {!! config('l5-swagger.defaults.ui.display.filter') ? 'true' : 'false' !!},
            persistAuthorization: @json((bool) config('l5-swagger.defaults.ui.authorization.persist_authorization')),
        })

        window.ui = ui
    }
</script>
</body>
</html>
