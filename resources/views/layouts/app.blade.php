<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>@yield('title', 'LPG 3KG') — Rekap Gas</title>
    <script defer src="{{ asset('assets/js/cdn.min.js') }}"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="{{ asset('assets/js/css2.css') }}" rel="stylesheet">
    @include('layouts.partials.app-styles')
    @stack('styles')
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
            <a href="{{ route('distributions.compare') }}" class="desktop-nav-item {{ request()->routeIs('distributions.compare') ? 'active' : '' }}">
                <span class="desktop-nav-icon">📈</span> Analisis Periode
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
@include('layouts.partials.app-scripts')

@stack('scripts')
</body>
</html>
