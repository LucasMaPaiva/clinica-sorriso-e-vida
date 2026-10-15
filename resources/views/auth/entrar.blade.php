<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar — {{ config('clinic.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    {{ \Illuminate\Support\Facades\Vite::fonts() }}
    <style>
        *{box-sizing:border-box}
        :root{--brand:#0f2e2c;--primary:#0f766e;--primary-dark:#115e59;--mint:#99f6e4;--ink:#0f172a;--muted:#64748b;--line:#e2e8f0}
        body{margin:0;min-height:100vh;display:grid;grid-template-columns:1fr;grid-template-rows:auto 1fr;font-family:'Instrument Sans',ui-sans-serif,system-ui,sans-serif;color:var(--ink);background:#f6f7f9}
        @media (min-width:960px){body{grid-template-columns:minmax(0,1.05fr) minmax(0,1fr);grid-template-rows:auto}}

        /* Lado da marca */
        .brand{display:flex;flex-direction:column;gap:2rem;padding:1.5rem;background:var(--brand);color:#fff}
        @media (min-width:960px){.brand{justify-content:space-between;padding:3rem 3.5rem}}
        .logo{display:flex;align-items:center;gap:.6rem;font-size:1.3rem;font-weight:700;letter-spacing:-.02em}
        .logo .tooth{font-size:1.8rem;line-height:1}
        .pitch{display:none}
        @media (min-width:960px){.pitch{display:block;max-width:30rem}}
        .pitch h2{margin:0 0 1rem;font-size:2.25rem;line-height:1.15;font-weight:600;letter-spacing:-.03em}
        .pitch h2 span{color:var(--mint)}
        .pitch>p{margin:0 0 2rem;color:rgba(255,255,255,.7);font-size:1.05rem;line-height:1.55}
        .features{display:grid;gap:.9rem;margin:0;padding:0;list-style:none}
        .features li{display:flex;align-items:center;gap:.8rem;color:rgba(255,255,255,.88)}
        .features i{display:grid;place-items:center;width:2.1rem;height:2.1rem;flex-shrink:0;border-radius:.65rem;background:rgba(153,246,228,.12);color:var(--mint)}
        .features svg{width:1.1rem;height:1.1rem}
        .preview{display:none}
        @media (min-width:960px){.preview{display:grid;gap:.5rem;max-width:24rem}}
        .preview div{display:flex;align-items:center;gap:.85rem;padding:.75rem .9rem;border-radius:.9rem;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);font-size:.88rem}
        .preview b{font-variant-numeric:tabular-nums;color:var(--mint)}
        .preview span{flex:1;color:rgba(255,255,255,.85)}
        .preview em{font-style:normal;font-size:.72rem;font-weight:600;padding:.15rem .55rem;border-radius:999px}
        .ok{background:rgba(16,185,129,.18);color:#6ee7b7}
        .wa{background:rgba(153,246,228,.15);color:var(--mint)}
        .wait{background:rgba(245,158,11,.18);color:#fcd34d}

        /* Formulário */
        main{display:grid;place-items:start center;padding:2.5rem 1.25rem}
        @media (min-width:960px){main{place-items:center}}
        .card{width:min(100%,24rem)}
        h1{margin:0 0 .4rem;font-size:1.75rem;font-weight:600;letter-spacing:-.02em}
        .sub{margin:0 0 2rem;color:var(--muted)}
        label{display:block;font-size:.875rem;font-weight:600;margin:0 0 .4rem}
        .field{position:relative;margin-bottom:1.1rem}
        .field>svg{position:absolute;left:.85rem;top:50%;width:1.1rem;height:1.1rem;transform:translateY(-50%);color:#94a3b8;pointer-events:none}
        .field input{width:100%;padding:.75rem .9rem .75rem 2.6rem;border:1px solid var(--line);border-radius:.75rem;background:#fff;font:inherit;font-size:1rem;color:var(--ink);transition:border-color .15s,box-shadow .15s}
        .field input:focus{outline:0;border-color:var(--primary);box-shadow:0 0 0 3px rgba(15,118,110,.15)}
        .field input[aria-invalid=true]{border-color:#f87171}
        .toggle{position:absolute;right:.4rem;top:50%;transform:translateY(-50%);display:grid;place-items:center;width:2.2rem;height:2.2rem;border:0;border-radius:.55rem;background:transparent;color:#94a3b8;cursor:pointer}
        .toggle:hover{color:var(--primary);background:#f1f5f9}
        .toggle svg{width:1.15rem;height:1.15rem}
        .row{display:flex;align-items:center;justify-content:space-between;margin:.25rem 0 1.5rem}
        .remember{display:flex;align-items:center;gap:.5rem;margin:0;font-size:.9rem;font-weight:500;color:#334155;cursor:pointer}
        .remember input{width:1rem;height:1rem;accent-color:var(--primary)}
        .submit{display:flex;align-items:center;justify-content:center;gap:.5rem;width:100%;padding:.85rem;border:0;border-radius:.75rem;background:var(--primary);color:#fff;font:inherit;font-weight:600;font-size:1rem;cursor:pointer;transition:background .15s,transform .1s}
        .submit:hover{background:var(--primary-dark)}
        .submit:active{transform:translateY(1px)}
        .submit[disabled]{opacity:.75;cursor:wait}
        .spinner{display:none;width:1rem;height:1rem;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite}
        .submit[disabled] .spinner{display:block}
        @keyframes spin{to{transform:rotate(360deg)}}
        .error{display:flex;gap:.6rem;align-items:flex-start;margin-bottom:1.25rem;padding:.75rem .9rem;border:1px solid #fecaca;border-radius:.75rem;background:#fef2f2;color:#991b1b;font-size:.9rem}
        .error svg{width:1.1rem;height:1.1rem;flex-shrink:0;margin-top:.1rem}
    </style>
</head>
<body>
<aside class="brand">
    <div class="logo"><span class="tooth">🦷</span> {{ config('clinic.name') }}</div>

    <div class="pitch">
        <h2>A agenda da clínica, <span>organizada e no WhatsApp.</span></h2>
        <p>Os pacientes marcam pelo WhatsApp e a equipe acompanha tudo num só painel.</p>
        <ul class="features">
            <li>
                <i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8.5 8.5 0 0 1-12.6 7.4L3 21l1.6-5.2A8.5 8.5 0 1 1 21 12Z"/></svg></i>
                Agendamento e remarcação pelo WhatsApp
            </li>
            <li>
                <i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/></svg></i>
                Lembretes automáticos antes da consulta
            </li>
            <li>
                <i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></i>
                Agenda, dentistas e procedimentos em um só lugar
            </li>
        </ul>
    </div>

    <div class="preview" aria-hidden="true">
        <div><b>09:00</b><span>Limpeza e profilaxia</span><em class="ok">Confirmada</em></div>
        <div><b>10:30</b><span>Avaliação ortodôntica</span><em class="wa">WhatsApp</em></div>
        <div><b>14:00</b><span>Extração</span><em class="wait">Aguardando</em></div>
    </div>
</aside>

<main>
    <div class="card">
        <h1>Bem-vindo de volta</h1>
        <p class="sub">Entre com seu e-mail e senha para acessar o painel.</p>

        @if ($errors->any())
            <div class="error" role="alert">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('entrar.store') }}" onsubmit="this.querySelector('.submit').disabled = true">
            @csrf
            <label for="email">E-mail</label>
            <div class="field">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="voce@exemplo.com" autocomplete="username" required autofocus @if ($errors->any()) aria-invalid="true" @endif>
            </div>

            <label for="password">Senha</label>
            <div class="field">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                <input type="password" id="password" name="password" placeholder="Sua senha" autocomplete="current-password" required>
                <button type="button" class="toggle" aria-label="Mostrar senha" onclick="const i=document.getElementById('password');const show=i.type==='password';i.type=show?'text':'password';this.setAttribute('aria-label',show?'Ocultar senha':'Mostrar senha')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>

            <div class="row">
                <label class="remember"><input type="checkbox" name="remember"> Lembrar de mim</label>
            </div>

            <button type="submit" class="submit"><span class="spinner"></span>Entrar</button>
        </form>

    </div>
</main>
</body>
</html>
