<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>@yield('title', 'LPG 3KG') — Rekap Gas</title>
    <script defer src="{{ asset('assets/js/cdn.min.js') }}"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="{{ asset('assets/js/css2.css') }}" rel="stylesheet">
    <style>
        /* ════════════════════════════════
           ROOT TOKENS
        ════════════════════════════════ */
        :root {
            --melon:       #43A047;
            --melon-light: #E8F5E9;
            --melon-mid:   #A5D6A7;
            --melon-dark:  #2E7D32;
            --melon-deep:  #1B5E20;
            --melon-50:    #F1F8F1;
            --melon-100:   #DCEDC8;

            --surface:  #ffffff;
            --surface2: #F4FAF4;

            --text1: #1a2e1a;
            --text2: #3d5c3d;
            --text3: #7a9a7a;

            --border:  #d4e8d4;
            --border2: #b8ddb8;

            --radius:    16px;
            --radius-sm: 12px;

            --nav-h:       60px;
            --safe-top:    env(safe-area-inset-top,    0px);
            --safe-bottom: env(safe-area-inset-bottom, 0px);
        }

        /* ════════════════════════════════
           RESET & BASE
        ════════════════════════════════ */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { height: 100%; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 14px;
            color: var(--text1);
            background: var(--surface2);
            min-height: 100%;
            -webkit-tap-highlight-color: transparent;
            overscroll-behavior-y: none;
        }
        a { text-decoration: none; color: inherit; }
        button { font-family: inherit; cursor: pointer; }
        input, select, textarea { font-family: inherit; }
        [x-cloak] { display: none !important; }

        /* ════════════════════════════════
           HEADER
        ════════════════════════════════ */
        .app-header {
            position: sticky;
            top: 0;
            z-index: 40;
            background: var(--melon);
            padding: calc(var(--safe-top) + 10px) 16px 14px;
        }
        .header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .brand-icon {
            width: 38px; height: 38px;
            background: rgba(255,255,255,0.20);
            border: 1px solid rgba(255,255,255,0.30);
            border-radius: 11px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .brand-name {
            font-size: 15px;
            font-weight: 600;
            color: #fff;
            line-height: 1.2;
            letter-spacing: -0.2px;
        }
        .brand-sub {
            font-size: 10px;
            color: rgba(255,255,255,0.72);
            font-weight: 400;
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        .avatar-btn {
            width: 34px; height: 34px;
            background: rgba(255,255,255,0.18);
            border: 1.5px solid rgba(255,255,255,0.38);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px;
            font-weight: 700;
            color: #fff;
            letter-spacing: 0.5px;
        }
        .logout-btn {
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.28);
            border-radius: 20px;
            padding: 6px 12px;
            font-size: 11px;
            font-weight: 600;
            color: rgba(255,255,255,0.92);
            line-height: 1;
        }
        .logout-btn:active { background: rgba(255,255,255,0.25); }

        /* ════════════════════════════════
           DESKTOP MODE TOGGLE BUTTON
        ════════════════════════════════ */
        .desktop-toggle-btn {
            display: flex;
            align-items: center;
            gap: 5px;
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.28);
            border-radius: 20px;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 600;
            color: rgba(255,255,255,0.92);
            line-height: 1;
            cursor: pointer;
            flex-shrink: 0;
        }
        .desktop-toggle-btn:active { background: rgba(255,255,255,0.25); }
        .toggle-icon { font-size: 13px; }
        /* Indicator dot */
        .toggle-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: rgba(255,255,255,0.45);
            transition: background 0.2s;
            flex-shrink: 0;
        }
        body.desktop-mode .toggle-dot { background: #A5D6A7; }

        /* ════════════════════════════════
           DESKTOP NAV (top, hidden by default)
        ════════════════════════════════ */
        .desktop-nav {
            display: none;
            align-items: center;
            gap: 4px;
            flex-wrap: nowrap;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        body.desktop-mode .desktop-nav { display: flex; }
        .desktop-nav-item {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 11px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            color: rgba(255,255,255,0.85);
            background: rgba(255,255,255,0.10);
            border: 1px solid rgba(255,255,255,0.18);
            white-space: nowrap;
            text-decoration: none;
            transition: background 0.12s;
        }
        .desktop-nav-item:active { background: rgba(255,255,255,0.25); }
        .desktop-nav-item.active {
            background: rgba(255,255,255,0.28);
            border-color: rgba(255,255,255,0.50);
            color: #fff;
        }
        .desktop-nav-icon { font-size: 14px; }

        /* Desktop nav row */
        .header-desktop-nav-row {
            display: none;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid rgba(255,255,255,0.15);
        }
        body.desktop-mode .header-desktop-nav-row { display: block; }

        /* ════════════════════════════════
           FLASH MESSAGES
        ════════════════════════════════ */
        .flash-zone { padding: 10px 14px 0; }

        .flash-ok {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            background: var(--melon-dark);
            border-radius: 10px;
            padding: 10px 14px;
            margin-bottom: 6px;
        }
        .flash-ok-left { display: flex; align-items: center; gap: 8px; }
        .flash-dot { width: 7px; height: 7px; background: var(--melon-mid); border-radius: 50%; flex-shrink: 0; }
        .flash-ok-text { font-size: 12px; font-weight: 500; color: #e8f5e9; line-height: 1.4; }
        .flash-close { background: none; border: none; color: rgba(255,255,255,0.55); font-size: 18px; line-height: 1; flex-shrink: 0; padding: 0 2px; }

        .flash-err {
            background: #fef2f2;
            border: 0.5px solid #fca5a5;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 12px;
            color: #991b1b;
            margin-bottom: 6px;
        }
        .flash-err-item { margin-top: 3px; }

        /* ════════════════════════════════
           MAIN CONTENT
        ════════════════════════════════ */
        main {
            padding: 14px 14px;
            padding-bottom: calc(var(--nav-h) + 14px + var(--safe-bottom));
        }
        /* Desktop mode: hapus padding bawah untuk bottom nav */
        body.desktop-mode main {
            padding-bottom: 20px;
        }

        /* ════════════════════════════════
           BOTTOM NAV (hidden di desktop mode)
        ════════════════════════════════ */
        .bottom-nav {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            z-index: 50;
            background: var(--surface);
            border-top: 0.5px solid var(--border);
            display: flex;
            align-items: stretch;
            padding-bottom: var(--safe-bottom);
            box-shadow: 0 -1px 12px rgba(46,125,50,0.08);
        }
        body.desktop-mode .bottom-nav { display: none; }

        .nav-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 3px;
            padding: 8px 4px 5px;
            border: none;
            background: none;
            color: inherit;
            -webkit-tap-highlight-color: transparent;
        }
        .nav-item:active .nav-pill { background: var(--melon-light); }
        .nav-pill {
            width: 46px; height: 28px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            transition: background 0.12s;
        }
        .nav-item.active .nav-pill { background: var(--melon-light); }
        .nav-label { font-size: 9px; font-weight: 600; color: var(--text3); letter-spacing: 0.1px; }
        .nav-item.active .nav-label { color: var(--melon-dark); }

        /* ════════════════════════════════
           MORE DRAWER (hidden di desktop mode)
        ════════════════════════════════ */
        body.desktop-mode .drawer-backdrop,
        body.desktop-mode .drawer { display: none !important; }

        .drawer-backdrop {
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.28);
            z-index: 55;
        }
        .drawer {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            z-index: 56;
            background: var(--surface);
            border-radius: 22px 22px 0 0;
            padding: 10px 16px calc(16px + var(--safe-bottom));
            box-shadow: 0 -4px 30px rgba(46,125,50,0.13);
        }
        .drawer-handle {
            width: 40px; height: 4px;
            background: var(--melon-mid);
            border-radius: 2px;
            margin: 0 auto 12px;
        }
        .drawer-section-label {
            font-size: 10px; font-weight: 700;
            color: var(--text3);
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 10px;
        }
        .drawer-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
        }
        .drawer-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .drawer-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            padding: 11px 6px;
            background: var(--melon-50);
            border: 0.5px solid var(--border);
            border-radius: var(--radius-sm);
            -webkit-tap-highlight-color: transparent;
        }
        .drawer-item:active { background: var(--melon-light); }
        .drawer-item-icon { font-size: 20px; line-height: 1; }
        .drawer-item-label { font-size: 9px; font-weight: 600; color: var(--text2); text-align: center; line-height: 1.3; }
        .drawer-divider { border: none; border-top: 0.5px solid var(--border); margin: 10px 0; }
        .drawer-logout {
            display: flex; align-items: center; justify-content: center; gap: 6px;
            width: 100%; padding: 11px;
            background: #fef2f2;
            border: 0.5px solid #fca5a5;
            border-radius: var(--radius-sm);
            font-size: 12px; font-weight: 600; color: #b91c1c;
        }
        .drawer-logout:active { background: #fee2e2; }

        /* ════════════════════════════════
           GLOBAL UTILITIES (child views)
        ════════════════════════════════ */

        /* cards */
        .card { background: var(--surface); border-radius: var(--radius-sm); border: 0.5px solid var(--border); }
        .s-card { background: var(--surface); border-radius: var(--radius-sm); border: 0.5px solid var(--border); margin-bottom: 10px; overflow: hidden; }
        .s-card-header { background: var(--melon-50); border-bottom: 0.5px solid var(--border); padding: 10px 14px; font-size: 12px; font-weight: 600; color: var(--text1); }

        /* section header */
        .section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
        .section-title  { font-size: 13px; font-weight: 600; color: var(--text1); }
        .section-link   { font-size: 11px; font-weight: 500; color: var(--melon-dark); }

        /* badges */
        .badge        { display: inline-flex; align-items: center; border-radius: 6px; padding: 2px 7px; font-size: 9px; font-weight: 600; }
        .badge-green  { background: #dcfce7; color: #166534; }
        .badge-red    { background: #fee2e2; color: #991b1b; }
        .badge-orange { background: #ffedd5; color: #9a3412; }
        .badge-blue   { background: #dbeafe; color: #1e40af; }

        /* form helpers */
        .field-label { font-size: 10px; font-weight: 600; color: var(--text3); margin-bottom: 4px; display: block; }
        .field-input {
            width: 100%; border: 0.5px solid var(--border2); border-radius: 8px;
            padding: 9px 12px; font-size: 13px; color: var(--text1); background: var(--surface);
        }
        .field-input:focus { outline: 2px solid var(--melon-mid); outline-offset: 0; border-color: transparent; }
        .field-select {
            width: 100%; border: 0.5px solid var(--border2); border-radius: 8px;
            padding: 9px 12px; font-size: 13px; color: var(--text1); background: var(--surface);
            appearance: none; -webkit-appearance: none;
        }
        .btn-primary { background: var(--melon); color: #fff; border: none; border-radius: 10px; padding: 11px 20px; font-size: 13px; font-weight: 600; width: 100%; }
        .btn-primary:active { background: var(--melon-dark); }
        .btn-secondary { background: var(--melon-50); color: var(--melon-dark); border: 0.5px solid var(--border); border-radius: 10px; padding: 11px 20px; font-size: 13px; font-weight: 600; width: 100%; }
        .btn-danger { background: #fee2e2; color: #b91c1c; border: 0.5px solid #fca5a5; border-radius: 10px; padding: 11px 20px; font-size: 13px; font-weight: 600; width: 100%; }
        .btn-sm { padding: 7px 14px; font-size: 11px; border-radius: 8px; width: auto; }

        /* tables */
        .mob-table { width: 100%; border-collapse: collapse; font-size: 11px; }
        .mob-table th { background: #f8faf8; color: var(--text3); font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; padding: 7px 10px; white-space: nowrap; text-align: left; }
        .mob-table th.r { text-align: right; }
        .mob-table td { padding: 8px 10px; border-bottom: 0.5px solid #eef5ee; white-space: nowrap; color: var(--text1); }
        .mob-table td.r { text-align: right; }
        .mob-table td.bold { font-weight: 600; }
        .mob-table tbody tr:last-child td { border-bottom: none; }
        .mob-table tr.total-row td { background: var(--melon); color: #fff; font-weight: 600; }
        .mob-table tr.total-row td.muted { color: rgba(255,255,255,0.72); font-size: 10px; }
        .mob-table tr.hi td { background: #fffbeb; }
        .scroll-x { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .scroll-y { max-height: 240px; overflow-y: auto; -webkit-overflow-scrolling: touch; }

        /* link buttons */
        .link-btn { background: none; border: none; font-size: 11px; color: #2563eb; text-decoration: underline; padding: 0; cursor: pointer; }
        .link-btn-sm { font-size: 10px; color: #2563eb; text-decoration: underline; cursor: pointer; font-weight: 500; }

        /* ════════════════════════════════
           DESKTOP CENTERING (mobile default)
        ════════════════════════════════ */
        @media (min-width: 520px) {
            body:not(.desktop-mode) { background: #c8dfc8; }
            body:not(.desktop-mode) .app-header,
            body:not(.desktop-mode) .flash-zone,
            body:not(.desktop-mode) main           { max-width: 480px; margin-left: auto; margin-right: auto; }
            body:not(.desktop-mode) .bottom-nav    { max-width: 480px; left: 50%; transform: translateX(-50%); border-radius: 20px 20px 0 0; }
            body:not(.desktop-mode) .drawer        { max-width: 480px; left: 50%; transform: translateX(-50%); }
        }

        /* ════════════════════════════════
           DESKTOP MODE — FULL WIDTH
        ════════════════════════════════ */
        body.desktop-mode {
            background: var(--surface2);
        }
        body.desktop-mode .app-header,
        body.desktop-mode .flash-zone,
        body.desktop-mode main {
            max-width: 100%;
            margin-left: 0;
            margin-right: 0;
        }
        body.desktop-mode .app-header {
            padding-left: 24px;
            padding-right: 24px;
        }
        body.desktop-mode .flash-zone {
            padding-left: 24px;
            padding-right: 24px;
        }
        body.desktop-mode main {
            padding-left: 24px;
            padding-right: 24px;
        }

        /* Wrapper konten inner */
        .content-inner {
            width: 100%;
        }

        /* Desktop mode: batasi lebar konten saja, bukan main */
        body.desktop-mode .content-inner {
            max-width: 1120px;
            margin-left: auto;
            margin-right: auto;
        }

        /* ════════════════════════════════
        AVATAR DROPDOWN
        ════════════════════════════════ */
        .avatar-dropdown-wrap {
        position: relative;
        flex-shrink: 0;
        }
        .avatar-btn {
        width: 34px; height: 34px;
        background: rgba(255,255,255,0.18);
        border: 1.5px solid rgba(255,255,255,0.38);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 11px;
        font-weight: 700;
        color: #fff;
        letter-spacing: 0.5px;
        cursor: pointer;
        position: relative;
        }
        .avatar-dot {
        position: absolute;
        bottom: -1px; right: -1px;
        width: 9px; height: 9px;
        background: rgba(255,255,255,0.45);
        border-radius: 50%;
        border: 1.5px solid var(--melon);
        transition: background 0.2s;
        }
        body.desktop-mode .avatar-dot { background: #A5D6A7; }

        .avatar-menu {
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        min-width: 210px;
        background: var(--surface);
        border: 0.5px solid var(--border2);
        border-radius: var(--radius);
        box-shadow: 0 6px 24px rgba(46,125,50,0.13);
        z-index: 100;
        overflow: hidden;
        transform-origin: top right;
        }
        .avatar-menu-header {
        padding: 11px 14px 10px;
        border-bottom: 0.5px solid var(--border);
        }
        .avatar-menu-name { font-size: 12px; font-weight: 600; color: var(--text1); }
        .avatar-menu-email { font-size: 10px; color: var(--text3); margin-top: 1px; }

        .avatar-menu-body { padding: 5px 6px; }
        .avatar-menu-footer {
        padding: 5px 6px 6px;
        border-top: 0.5px solid var(--border);
        }
        .avatar-menu-item {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 8px 10px;
        border-radius: 9px;
        border: none;
        background: transparent;
        font-family: inherit;
        font-size: 12px;
        font-weight: 500;
        color: var(--text1);
        cursor: pointer;
        text-decoration: none;
        text-align: left;
        }
        .avatar-menu-item:active { background: var(--melon-50); }
        .avatar-menu-item.danger { color: #b91c1c; }
        .avatar-menu-item.danger:active { background: #fef2f2; }
        .ami-icon { font-size: 15px; flex-shrink: 0; }
        .ami-label { flex: 1; }

        /* Toggle switch */
        .ami-toggle {
        width: 32px; height: 18px;
        border-radius: 9px;
        background: #ccc;
        position: relative;
        transition: background 0.2s;
        flex-shrink: 0;
        }
        body.desktop-mode .ami-toggle { background: var(--melon); }
        .ami-thumb {
        width: 14px; height: 14px;
        background: #fff;
        border-radius: 50%;
        position: absolute;
        top: 2px; left: 2px;
        transition: left 0.2s;
        }
        body.desktop-mode .ami-thumb { left: 16px; }

    </style>
</head>
<body>

{{-- ══ HEADER ══ --}}
@php
    $nav = match(true) {
        request()->routeIs('transfer.*') => 'tf',
        request()->routeIs('distributions.*', 'contract-dist.*')                 => 'dist',
        request()->routeIs('do.*')                                    => 'do',
        request()->routeIs('cashflow.*', 'transfer.*', 'external-debt.*', 'savings.*')   => 'fin',
        default                                                                           => 'more',
    };
@endphp

<header class="app-header">
    <div class="header-inner">
        <a href="{{ route('home') }}" class="brand">
            <div class="brand-icon">⛽</div>
            <div>
                <div class="brand-name">LPG 3KG</div>
                <div class="brand-sub">Rekap &amp; Manajemen</div>
            </div>
        </a>
        {{-- GANTI bagian header-actions --}}
<div class="header-actions">

    {{-- Avatar Dropdown --}}
    <div class="avatar-dropdown-wrap" x-data="{ open: false }">
        <button type="button" class="avatar-btn" @click="open = !open" @keydown.escape="open = false" title="Akun">
            {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
            <span class="avatar-dot" id="toggleDot"></span>
        </button>

        <div class="avatar-menu" x-show="open" @click.outside="open = false"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             x-cloak>

            <div class="avatar-menu-header">
                <div class="avatar-menu-name">{{ Auth::user()->name }}</div>
                <div class="avatar-menu-email">{{ Auth::user()->email }}</div>
            </div>

            <div class="avatar-menu-body">
                <button type="button" class="avatar-menu-item" onclick="toggleDesktopMode()">
                    <span class="ami-icon">🖥️</span>
                    <span class="ami-label">Mode Desktop</span>
                    <div class="ami-toggle" id="desktopToggle">
                        <div class="ami-thumb" id="desktopThumb"></div>
                    </div>
                </button>
                <a href="{{ route('password.form') }}" class="avatar-menu-item">
                    <span class="ami-icon">🔑</span>
                    <span class="ami-label">Ganti Password</span>
                </a>
            </div>

            <div class="avatar-menu-footer">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="avatar-menu-item danger">
                        <span class="ami-icon">🚪</span>
                        <span class="ami-label">Keluar dari Akun</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Hapus logout-btn lama, sudah masuk dropdown --}}
</div>
    </div>

    {{-- ══ DESKTOP TOP NAV (muncul saat desktop mode aktif) ══ --}}
    <div class="header-desktop-nav-row">
        <div class="desktop-nav">
            <a href="{{ route('do.index') }}" class="desktop-nav-item {{ $nav==='do' ? 'active' : '' }}">
                <span class="desktop-nav-icon">📦</span> DO Agen
            </a>
            <a href="{{ route('distributions.index') }}" class="desktop-nav-item {{ $nav==='dist' ? 'active' : '' }}">
                <span class="desktop-nav-icon">🚚</span> Distribusi
            </a>
            <a href="{{ route('cashflow.index') }}" class="desktop-nav-item {{ $nav==='fin' ? 'active' : '' }}">
                <span class="desktop-nav-icon">💸</span> Keuangan
            </a>
            <a href="{{ route('transfer.index') }}" class="desktop-nav-item {{ $nav==='tf' ? 'active' : '' }}">
                <span class="desktop-nav-icon">🏦</span> Transfer
            </a>
            <a href="{{ route('summary.index') }}" class="desktop-nav-item {{ request()->routeIs('summary.*') ? 'active' : '' }}">
                <span class="desktop-nav-icon">📊</span> Ringkasan
            </a>
            <a href="{{ route('contract-dist.index') }}" class="desktop-nav-item {{ request()->routeIs('contract-dist.*') ? 'active' : '' }}">
                <span class="desktop-nav-icon">⭐</span> Kontrak
            </a>
            <a href="{{ route('savings.index') }}" class="desktop-nav-item {{ request()->routeIs('savings.*') ? 'active' : '' }}">
                <span class="desktop-nav-icon">💰</span> Tabungan
            </a>
            <a href="{{ route('periods.index') }}" class="desktop-nav-item {{ request()->routeIs('periods.*') ? 'active' : '' }}">
                <span class="desktop-nav-icon">📅</span> Periode
            </a>
            <a href="{{ route('master.index') }}" class="desktop-nav-item {{ request()->routeIs('master.*') ? 'active' : '' }}">
                <span class="desktop-nav-icon">⚙️</span> Master Data
            </a>
            <a href="{{ route('password.form') }}" class="desktop-nav-item {{ request()->routeIs('password.*') ? 'active' : '' }}">
                <span class="desktop-nav-icon">🔑</span> Ganti Sandi
            </a>
        </div>
    </div>
</header>

{{-- ══ FLASH ══ --}}
<div class="flash-zone">
    @if(session('success'))
    <div x-data="{ show: true }"
         x-show="show"
         x-init="setTimeout(() => show = false, 4000)"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="flash-ok">
        <div class="flash-ok-left">
            <span class="flash-dot"></span>
            <span class="flash-ok-text">{{ session('success') }}</span>
        </div>
        <button @click="show = false" class="flash-close">×</button>
    </div>
    @endif
    @if(session('error') || $errors->any())
    <div class="flash-err">
        @if(session('error'))❌ {{ session('error') }}@endif
        @foreach($errors->all() as $e)
            <div class="flash-err-item">• {{ $e }}</div>
        @endforeach
    </div>
    @endif
</div>

{{-- ══ CONTENT ══ --}}
<main>
    <div class="content-inner">
        @yield('content')
    </div>
</main>

{{-- ══ BOTTOM NAV (tersembunyi di desktop mode) ══ --}}
<nav class="bottom-nav">
    <a href="{{ route('do.index') }}" class="nav-item {{ $nav==='do' ? 'active' : '' }}">
        <div class="nav-pill">📦</div>
        <span class="nav-label">DO Agen</span>
    </a>
    <a href="{{ route('distributions.index') }}" class="nav-item {{ $nav==='dist' ? 'active' : '' }}">
        <div class="nav-pill">🚚</div>
        <span class="nav-label">Distribusi</span>
    </a>
    <a href="{{ route('cashflow.index') }}" class="nav-item {{ $nav==='fin' ? 'active' : '' }}">
        <div class="nav-pill">💸</div>
        <span class="nav-label">Keuangan</span>
    </a>
    <a href="{{ route('transfer.index') }}" class="nav-item {{ $nav==='tf' ? 'active' : '' }}">
        <div class="nav-pill">🏦</div>
        <span class="nav-label">Transfer</span>
    </a>
    <button type="button" class="nav-item {{ $nav==='more' ? 'active' : '' }}"
            x-data @click="$dispatch('toggle-drawer')">
        <div class="nav-pill">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                <circle cx="4"  cy="10" r="1.8" fill="currentColor" opacity=".6"/>
                <circle cx="10" cy="10" r="1.8" fill="currentColor" opacity=".6"/>
                <circle cx="16" cy="10" r="1.8" fill="currentColor" opacity=".6"/>
            </svg>
        </div>
        <span class="nav-label">Lainnya</span>
    </button>
</nav>

{{-- ══ MORE DRAWER ══ --}}
<div x-data="{ open: false }" @toggle-drawer.window="open = !open" x-cloak>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="drawer-backdrop"
         @click="open = false">
    </div>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-220"
         x-transition:enter-start="transform translate-y-full opacity-0"
         x-transition:enter-end="transform translate-y-0 opacity-100"
         x-transition:leave="transition ease-in duration-180"
         x-transition:leave-start="transform translate-y-0 opacity-100"
         x-transition:leave-end="transform translate-y-full opacity-0"
         class="drawer">

        <div class="drawer-handle"></div>
        <div class="drawer-section-label">Menu Lainnya</div>

        <div class="drawer-grid">
            <a href="{{ route('summary.index') }}"            class="drawer-item"><span class="drawer-item-icon">📊</span><span class="drawer-item-label">Ringkasan</span></a>
            <a href="{{ route('contract-dist.index') }}" class="drawer-item"><span class="drawer-item-icon">⭐</span><span class="drawer-item-label">Kontrak</span></a>
            <a href="{{ route('savings.index') }}"       class="drawer-item"><span class="drawer-item-icon">💰</span><span class="drawer-item-label">Tabungan</span></a>
            <a href="{{ route('periods.index') }}"       class="drawer-item"><span class="drawer-item-icon">📅</span><span class="drawer-item-label">Periode</span></a>
        </div>

        <hr class="drawer-divider">

        <div class="drawer-grid-2">
            <a href="{{ route('master.index') }}"   class="drawer-item"><span class="drawer-item-icon">⚙️</span><span class="drawer-item-label">Master Data</span></a>
            <a href="{{ route('password.form') }}"  class="drawer-item"><span class="drawer-item-icon">🔑</span><span class="drawer-item-label">Ganti Sandi</span></a>
        </div>

        <hr class="drawer-divider">

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="drawer-logout">🚪 Keluar dari Akun</button>
        </form>
    </div>
</div>

{{-- ══ DESKTOP MODE SCRIPT ══ --}}
<script>
    var STORAGE_KEY = 'rekap_gas_desktop_mode';

    function applyDesktopMode(isDesktop) {
        if (isDesktop) {
            document.body.classList.add('desktop-mode');
        } else {
            document.body.classList.remove('desktop-mode');
        }
        // dot & toggle sync via CSS class body.desktop-mode — otomatis
    }

    function toggleDesktopMode() {
        var isCurrentlyDesktop = document.body.classList.contains('desktop-mode');
        var newState = !isCurrentlyDesktop;
        try {
            localStorage.setItem(STORAGE_KEY, newState ? '1' : '0');
        } catch(e) {}
        applyDesktopMode(newState);
    }

    // Baca preferensi dari localStorage saat halaman dimuat
    (function() {
        var saved;
        try { saved = localStorage.getItem(STORAGE_KEY); } catch(e) {}
        // Default desktop mode aktif jika layar >= 1024px dan belum pernah disimpan
        if (saved === null) {
            saved = window.innerWidth >= 1024 ? '1' : '0';
        }
        applyDesktopMode(saved === '1');
    })();
</script>

@stack('scripts')
</body>
</html>
