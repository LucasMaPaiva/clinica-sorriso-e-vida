<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('clinic.name') }}</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:system-ui,sans-serif;color:#134e4a;background:linear-gradient(145deg,#f0fdfa,#ecfeff)}
        main{width:min(92%,680px);padding:3.5rem;border:1px solid #99f6e4;border-radius:1.5rem;background:rgba(255,255,255,.9);box-shadow:0 24px 70px rgba(13,148,136,.12);text-align:center}
        .icon{font-size:4rem}h1{margin:.5rem 0;font-size:clamp(2rem,5vw,3rem)}p{color:#475569;font-size:1.1rem;line-height:1.6}a{display:inline-block;margin-top:1rem;padding:.85rem 1.3rem;border-radius:.75rem;color:#fff;background:#0f766e;text-decoration:none;font-weight:700}a:hover{background:#115e59}
    </style>
</head>
<body><main><div class="icon">🦷</div><h1>{{ config('clinic.name') }}</h1><p>Agenda odontológica integrada ao WhatsApp. Agende, confirme, cancele e reagende automaticamente.</p><a href="{{ url('/admin') }}">Acessar painel administrativo</a></main></body>
</html>
