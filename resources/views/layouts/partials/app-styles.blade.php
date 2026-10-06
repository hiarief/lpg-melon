<style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

        /* ════════════════════════════════
        ROOT TOKENS
        ════════════════════════════════ */
        :root {
            /* Melon — lebih muted, tetap jadi aksen utama */
            --melon-50:    #F3F8F4;
            --melon-light: #E6F1E9;
            --melon-100:   #D5E8DA;
            --melon-mid:   #A9CDB2;
            --melon:       #357F4B;
            --melon-dark:  #29663C;
            --melon-deep:  #1D4A2B;

            /* Neutral gray — sedikit condong hijau supaya selaras dengan melon */
            --gray-50:  #F7F9F7;
            --gray-100: #EFF2EF;
            --gray-200: #E4E9E5;
            --gray-300: #D2D9D3;
            --gray-400: #AEB7B0;
            --gray-500: #6F7A71;
            --gray-600: #5A655C;
            --gray-700: #434D45;
            --gray-800: #2B332D;
            --gray-900: #1B211C;

            --surface:  #ffffff;
            --surface2: #F4F6F4;
            --zebra:    #F9FBF9;

            --text1: var(--gray-900);
            --text2: var(--gray-700);
            --text3: var(--gray-500);

            --border:      var(--gray-200);
            --border2:     var(--gray-300);
            --border-soft: rgba(27,40,30,0.07);

            /* Semantic pastel */
            --ok-bg:   #E4F2E8;  --ok-fg:   #25613A;  --ok-line:   #C9E3D1;
            --err-bg:  #FBEBE9;  --err-fg:  #9B3A33;  --err-line:  #F2CBC7;
            --warn-bg: #FCF0DD;  --warn-fg: #8A5514;  --warn-line: #F0D9B3;
            --info-bg: #E8F0FA;  --info-fg: #2D5A8F;  --info-line: #C9DAF0;
            --violet-bg: #EFEDFB; --violet-fg: #4B42A0; --violet-line: #D9D5F3;

            /* Radius */
            --radius:      20px;
            --radius-sm:   14px;
            --radius-xs:   10px;
            --radius-pill: 999px;

            /* Shadow — floating halus */
            --shadow-xs: 0 1px 2px rgba(27,40,30,0.04);
            --shadow-sm: 0 1px 2px rgba(27,40,30,0.04), 0 4px 12px rgba(27,40,30,0.05);
            --shadow-md: 0 2px 4px rgba(27,40,30,0.04), 0 10px 24px rgba(27,40,30,0.08);
            --shadow-lg: 0 4px 8px rgba(27,40,30,0.05), 0 20px 44px rgba(27,40,30,0.14);
            --ring:      0 0 0 4px rgba(53,127,75,0.16);

            /* Motion */
            --ease: cubic-bezier(0.22, 0.61, 0.36, 1);
            --dur:  0.18s;

            --nav-h:       64px;
            --safe-top:    env(safe-area-inset-top,    0px);
            --safe-bottom: env(safe-area-inset-bottom, 0px);

            accent-color: var(--melon);
        }

        /* ════════════════════════════════
        RESET & BASE
        ════════════════════════════════ */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { height: 100%; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', sans-serif;
            font-size: 14px;
            line-height: 1.55;
            color: var(--text1);
            background: var(--surface2);
            min-height: 100%;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
            -webkit-tap-highlight-color: transparent;
            overscroll-behavior-y: none;
        }
        h1, h2, h3, h4 { line-height: 1.25; letter-spacing: -0.015em; }
        a { text-decoration: none; color: inherit; }
        button { font-family: inherit; cursor: pointer; }
        input, select, textarea { font-family: inherit; }
        [x-cloak] { display: none !important; }
        ::selection { background: var(--melon-100); color: var(--melon-deep); }

        :focus-visible { outline: 2px solid var(--melon); outline-offset: 2px; }
        button:disabled, .btn-primary:disabled { opacity: 0.55; cursor: not-allowed; }

        @keyframes page-in  { from { opacity: 0; } to { opacity: 1; } }
        @keyframes flash-in { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: none; } }

        /* ════════════════════════════════
        HEADER
        ════════════════════════════════ */
        .app-header {
            position: sticky;
            top: 0;
            z-index: 40;
            background: rgba(255,255,255,0.88);
            -webkit-backdrop-filter: saturate(1.5) blur(16px);
            backdrop-filter: saturate(1.5) blur(16px);
            border-bottom: 1px solid var(--border-soft);
            padding: calc(var(--safe-top) + 12px) 18px 12px;
        }
        .header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .brand-icon {
            width: 38px; height: 38px;
            background: var(--melon-light);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .brand-name {
            font-size: 15px;
            font-weight: 700;
            color: var(--text1);
            line-height: 1.25;
            letter-spacing: -0.02em;
        }
        .brand-sub {
            font-size: 11px;
            color: var(--text3);
            font-weight: 500;
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        .avatar-btn {
            width: 36px; height: 36px;
            background: var(--melon-light);
            border: 1px solid var(--melon-100);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px;
            font-weight: 700;
            color: var(--melon-dark);
            letter-spacing: 0.3px;
            transition: background var(--dur) var(--ease), box-shadow var(--dur) var(--ease);
        }
        .avatar-btn:hover  { background: var(--melon-100); }
        .avatar-btn:active { box-shadow: var(--ring); }
        .logout-btn {
            background: transparent;
            border: 1px solid var(--border2);
            border-radius: var(--radius-pill);
            padding: 7px 14px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text2);
            line-height: 1;
            transition: background var(--dur) var(--ease), color var(--dur) var(--ease);
        }
        .logout-btn:hover  { background: var(--gray-100); color: var(--text1); }
        .logout-btn:active { background: var(--gray-200); }

        /* ════════════════════════════════
        DESKTOP MODE TOGGLE BUTTON
        ════════════════════════════════ */
        .desktop-toggle-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            background: transparent;
            border: 1px solid var(--border2);
            border-radius: var(--radius-pill);
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text2);
            line-height: 1;
            cursor: pointer;
            flex-shrink: 0;
            transition: background var(--dur) var(--ease), color var(--dur) var(--ease);
        }
        .desktop-toggle-btn:hover  { background: var(--gray-100); color: var(--text1); }
        .desktop-toggle-btn:active { background: var(--gray-200); }
        .toggle-icon { font-size: 13px; }
        .toggle-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--gray-300);
            transition: background var(--dur) var(--ease);
            flex-shrink: 0;
        }
        body.desktop-mode .toggle-dot { background: var(--melon); }

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
            scrollbar-width: none;
        }
        .desktop-nav::-webkit-scrollbar { display: none; }
        body.desktop-mode .desktop-nav { display: flex; }
        .desktop-nav-item {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: var(--radius-pill);
            font-size: 12px;
            font-weight: 600;
            color: var(--text3);
            background: transparent;
            border: 1px solid transparent;
            white-space: nowrap;
            text-decoration: none;
            transition: background var(--dur) var(--ease), color var(--dur) var(--ease);
        }
        .desktop-nav-item:hover  { background: var(--gray-100); color: var(--text1); }
        .desktop-nav-item:active { background: var(--gray-200); }
        .desktop-nav-item.active {
            background: var(--melon-light);
            color: var(--melon-dark);
        }
        .desktop-nav-icon { font-size: 14px; }

        .header-desktop-nav-row {
            display: none;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--border-soft);
        }
        body.desktop-mode .header-desktop-nav-row { display: block; }

        /* ════════════════════════════════
        FLASH MESSAGES
        ════════════════════════════════ */
        .flash-zone { padding: 14px 16px 0; }

        .flash-ok {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            background: var(--ok-bg);
            border: 1px solid var(--ok-line);
            border-radius: var(--radius-sm);
            padding: 12px 14px;
            margin-bottom: 8px;
            animation: flash-in 0.32s var(--ease) backwards;
        }
        .flash-ok-left { display: flex; align-items: center; gap: 10px; }
        .flash-dot { width: 8px; height: 8px; background: var(--melon); border-radius: 50%; flex-shrink: 0; }
        .flash-ok-text { font-size: 13px; font-weight: 500; color: var(--ok-fg); line-height: 1.45; }
        .flash-close { background: none; border: none; color: var(--ok-fg); opacity: 0.5; font-size: 18px; line-height: 1; flex-shrink: 0; padding: 0 2px; transition: opacity var(--dur) var(--ease); }
        .flash-close:hover { opacity: 0.9; }

        .flash-err {
            background: var(--err-bg);
            border: 1px solid var(--err-line);
            border-radius: var(--radius-sm);
            padding: 12px 14px;
            font-size: 13px;
            line-height: 1.45;
            color: var(--err-fg);
            margin-bottom: 8px;
            animation: flash-in 0.32s var(--ease) backwards;
        }
        .flash-err-item { margin-top: 4px; }

        /* ════════════════════════════════
        MAIN CONTENT
        ════════════════════════════════ */
        main {
            padding: 18px 16px;
            padding-bottom: calc(var(--nav-h) + 20px + var(--safe-bottom));
            animation: page-in 0.35s var(--ease) backwards;
        }
        body.desktop-mode main {
            padding-bottom: 28px;
        }

        /* ════════════════════════════════
        BOTTOM NAV (hidden di desktop mode)
        ════════════════════════════════ */
        .bottom-nav {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            z-index: 50;
            background: rgba(255,255,255,0.92);
            -webkit-backdrop-filter: saturate(1.5) blur(16px);
            backdrop-filter: saturate(1.5) blur(16px);
            border-top: 1px solid var(--border-soft);
            display: flex;
            align-items: stretch;
            padding-bottom: var(--safe-bottom);
            box-shadow: 0 -6px 24px rgba(27,40,30,0.06);
        }
        body.desktop-mode .bottom-nav { display: none; }

        .nav-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 9px 4px 7px;
            border: none;
            background: none;
            color: inherit;
            -webkit-tap-highlight-color: transparent;
        }
        .nav-pill {
            width: 52px; height: 30px;
            border-radius: var(--radius-pill);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            transition: background var(--dur) var(--ease), transform var(--dur) var(--ease);
        }
        .nav-item:active .nav-pill { background: var(--melon-light); transform: scale(0.94); }
        .nav-item.active .nav-pill { background: var(--melon-light); }
        .nav-label { font-size: 10px; font-weight: 600; color: var(--text3); letter-spacing: 0.1px; transition: color var(--dur) var(--ease); }
        .nav-item.active .nav-label { color: var(--melon-dark); }

        /* ════════════════════════════════
        MORE DRAWER (hidden di desktop mode)
        ════════════════════════════════ */
        body.desktop-mode .drawer-backdrop,
        body.desktop-mode .drawer { display: none !important; }

        .drawer-backdrop {
            position: fixed; inset: 0;
            background: rgba(23,33,26,0.32);
            -webkit-backdrop-filter: blur(3px);
            backdrop-filter: blur(3px);
            z-index: 55;
        }
        .drawer {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            z-index: 56;
            background: var(--surface);
            border-radius: 28px 28px 0 0;
            padding: 12px 20px calc(20px + var(--safe-bottom));
            box-shadow: var(--shadow-lg);
        }
        .drawer-handle {
            width: 40px; height: 4px;
            background: var(--gray-300);
            border-radius: 2px;
            margin: 0 auto 16px;
        }
        .drawer-section-label {
            font-size: 12px; font-weight: 700;
            color: var(--text3);
            letter-spacing: 0;
            margin-bottom: 12px;
        }
        .drawer-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }
        .drawer-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .drawer-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 14px 6px;
            background: var(--gray-50);
            border: 1px solid var(--border-soft);
            border-radius: var(--radius-sm);
            transition: background var(--dur) var(--ease), transform var(--dur) var(--ease), box-shadow var(--dur) var(--ease);
            -webkit-tap-highlight-color: transparent;
        }
        .drawer-item:active { background: var(--melon-light); transform: scale(0.97); }
        .drawer-item-icon { font-size: 22px; line-height: 1; }
        .drawer-item-label { font-size: 10px; font-weight: 600; color: var(--text2); text-align: center; line-height: 1.35; }
        .drawer-divider { border: none; border-top: 1px solid var(--border-soft); margin: 14px 0; }
        .drawer-logout {
            display: flex; align-items: center; justify-content: center; gap: 6px;
            width: 100%; padding: 13px;
            background: var(--err-bg);
            border: 1px solid var(--err-line);
            border-radius: var(--radius-sm);
            font-size: 13px; font-weight: 600; color: var(--err-fg);
            transition: background var(--dur) var(--ease);
        }
        .drawer-logout:active { background: #F8DDDA; }

        /* ════════════════════════════════
        GLOBAL UTILITIES (child views)
        ════════════════════════════════ */
        .card {
            background: var(--surface);
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-soft);
            box-shadow: var(--shadow-sm);
            transition: box-shadow var(--dur) var(--ease), transform var(--dur) var(--ease);
        }
        .s-card {
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px solid var(--border-soft);
            box-shadow: var(--shadow-sm);
            margin-bottom: 14px;
            overflow: hidden;
        }
        .s-card-header {
            background: var(--surface);
            border-bottom: 1px solid var(--border-soft);
            padding: 14px 18px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: -0.01em;
            color: var(--text1);
        }

        .section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .section-title  { font-size: 15px; font-weight: 700; letter-spacing: -0.015em; color: var(--text1); }
        .section-link   { font-size: 12px; font-weight: 600; color: var(--melon-dark); transition: color var(--dur) var(--ease); }
        .section-link:hover { color: var(--melon-deep); }

        .badge        { display: inline-flex; align-items: center; gap: 4px; border-radius: var(--radius-pill); padding: 3px 10px; font-size: 11px; font-weight: 600; line-height: 1.4; letter-spacing: 0.01em; }
        .badge-green  { background: var(--ok-bg);   color: var(--ok-fg); }
        .badge-red    { background: var(--err-bg);  color: var(--err-fg); }
        .badge-orange { background: var(--warn-bg); color: var(--warn-fg); }
        .badge-blue   { background: var(--info-bg); color: var(--info-fg); }

        .field-label { font-size: 11px; font-weight: 600; color: var(--text2); margin-bottom: 6px; display: block; }
        .field-input,
        .field-select {
            width: 100%;
            border: 1px solid var(--border2);
            border-radius: 12px;
            padding: 10px 13px;
            font-size: 14px;
            color: var(--text1);
            background: var(--surface);
            box-shadow: var(--shadow-xs);
            transition: border-color var(--dur) var(--ease), box-shadow var(--dur) var(--ease), background var(--dur) var(--ease);
        }
        .field-input::placeholder { color: var(--gray-400); }
        .field-input:hover, .field-select:hover { border-color: var(--gray-400); }
        .field-input:focus, .field-select:focus { outline: none; border-color: var(--melon); box-shadow: var(--ring); }
        .field-select { appearance: none; -webkit-appearance: none; }

        .btn-primary,
        .btn-secondary,
        .btn-danger {
            border-radius: 12px;
            padding: 12px 20px;
            font-size: 13px;
            font-weight: 600;
            width: 100%;
            transition: background var(--dur) var(--ease), box-shadow var(--dur) var(--ease), transform var(--dur) var(--ease), color var(--dur) var(--ease);
        }
        .btn-primary   { background: var(--melon); color: #fff; border: none; box-shadow: 0 1px 2px rgba(29,74,43,0.25), inset 0 1px 0 rgba(255,255,255,0.12); }
        .btn-primary:hover  { background: var(--melon-dark); box-shadow: 0 4px 12px rgba(29,74,43,0.22), inset 0 1px 0 rgba(255,255,255,0.12); }
        .btn-primary:active { background: var(--melon-dark); transform: scale(0.985); }
        .btn-secondary { background: var(--melon-50); color: var(--melon-dark); border: 1px solid var(--melon-100); }
        .btn-secondary:hover  { background: var(--melon-light); }
        .btn-secondary:active { background: var(--melon-light); transform: scale(0.985); }
        .btn-danger    { background: var(--err-bg); color: var(--err-fg); border: 1px solid var(--err-line); }
        .btn-danger:hover  { background: #F8DDDA; }
        .btn-danger:active { background: #F8DDDA; transform: scale(0.985); }
        .btn-sm { padding: 7px 14px; font-size: 12px; border-radius: 10px; width: auto; }

        .mob-table { width: 100%; border-collapse: collapse; font-size: 12px; font-variant-numeric: tabular-nums; }
        .mob-table :where(th) { background: var(--gray-50); color: var(--text3); font-size: 11px; font-weight: 600; letter-spacing: 0.01em; padding: 11px 14px; white-space: nowrap; text-align: left; border-bottom: 1px solid var(--border); }
        .mob-table th.r { text-align: right; }
        .mob-table :where(td) { padding: 11px 14px; border-bottom: 1px solid var(--gray-100); white-space: nowrap; color: var(--text1); }
        .mob-table td.r { text-align: right; }
        .mob-table :where(td.bold) { font-weight: 600; }
        .mob-table tbody :where(tr:last-child td) { border-bottom: none; }
        .mob-table tr.total-row td { background: var(--melon-light); color: var(--melon-deep); font-weight: 700; border-top: 1px solid var(--melon-mid); border-bottom: none; }
        .mob-table tr.total-row td.muted { color: var(--melon-dark); opacity: 0.75; font-size: 11px; font-weight: 600; }
        .mob-table tr.hi td { background: #FFF8E6; }

        /* Alternating row halus — warna di <tr> supaya kolom sticky (background: inherit) ikut sama */
        .mob-table tbody tr,
        .do-table tbody tr,
        .rank-table tbody tr { background: var(--surface); }
        .mob-table tbody tr:nth-child(even),
        .do-table tbody tr:nth-child(even),
        .rank-table tbody tr:nth-child(even) { background: var(--zebra); }
        @media (hover: hover) {
            .mob-table tbody tr:hover,
            .do-table tbody tr:hover,
            .rank-table tbody tr:hover { background: var(--melon-50); }
        }

        .scroll-x { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .scroll-y { max-height: 240px; overflow-y: auto; -webkit-overflow-scrolling: touch; }

        .link-btn { background: none; border: none; font-size: 12px; font-weight: 500; color: var(--info-fg); text-decoration: underline; text-decoration-thickness: 1px; text-underline-offset: 3px; padding: 0; cursor: pointer; transition: color var(--dur) var(--ease); }
        .link-btn:hover { color: #1F4470; }
        .link-btn-sm { font-size: 11px; color: var(--info-fg); text-decoration: underline; text-decoration-thickness: 1px; text-underline-offset: 3px; cursor: pointer; font-weight: 500; }

        /* Hover lift hanya untuk perangkat dengan pointer (bukan touch) */
        @media (hover: hover) {
            .card:hover { box-shadow: var(--shadow-md); }
            .drawer-item:hover { background: var(--melon-50); box-shadow: var(--shadow-sm); transform: translateY(-1px); }
        }

        /* ════════════════════════════════
        DESKTOP CENTERING (mobile default)
        ════════════════════════════════ */
        @media (min-width: 520px) {
            body:not(.desktop-mode) { background: #E6EBE7; }
            body:not(.desktop-mode) .app-header,
            body:not(.desktop-mode) .flash-zone,
            body:not(.desktop-mode) main           { max-width: 480px; margin-left: auto; margin-right: auto; }
            body:not(.desktop-mode) .bottom-nav    { max-width: 480px; left: 50%; transform: translateX(-50%); border-radius: 24px 24px 0 0; }
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
            padding-left: 28px;
            padding-right: 28px;
        }
        body.desktop-mode .flash-zone {
            padding-left: 28px;
            padding-right: 28px;
        }
        body.desktop-mode main {
            padding-left: 28px;
            padding-right: 28px;
        }

        .content-inner {
            width: 100%;
        }
        body.desktop-mode .content-inner {
            max-width: 1220px;
            margin-left: auto;
            margin-right: auto;
        }

        tfoot td {
            font-weight: 600;
            border-top: 1px solid var(--border2);
            background: var(--gray-50);
        }

        /* ════════════════════════════════
        AVATAR DROPDOWN
        ════════════════════════════════ */
        .avatar-dropdown-wrap {
            position: relative;
            flex-shrink: 0;
        }
        .avatar-btn {
            cursor: pointer;
            position: relative;
        }
        .avatar-dot {
            position: absolute;
            bottom: -1px; right: -1px;
            width: 10px; height: 10px;
            background: var(--gray-300);
            border-radius: 50%;
            border: 2px solid var(--surface);
            transition: background var(--dur) var(--ease);
        }
        body.desktop-mode .avatar-dot { background: var(--melon); }

        .avatar-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            min-width: 224px;
            background: var(--surface);
            border: 1px solid var(--border-soft);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            z-index: 100;
            overflow: hidden;
            transform-origin: top right;
        }
        .avatar-menu-header {
            padding: 14px 16px 12px;
            border-bottom: 1px solid var(--border-soft);
        }
        .avatar-menu-name { font-size: 13px; font-weight: 700; color: var(--text1); letter-spacing: -0.01em; }
        .avatar-menu-email { font-size: 11px; color: var(--text3); margin-top: 2px; }

        .avatar-menu-body { padding: 6px; }
        .avatar-menu-footer {
            padding: 6px;
            border-top: 1px solid var(--border-soft);
        }
        .avatar-menu-item {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 12px;
            border: none;
            background: transparent;
            font-family: inherit;
            font-size: 13px;
            font-weight: 500;
            color: var(--text1);
            cursor: pointer;
            text-decoration: none;
            text-align: left;
            transition: background var(--dur) var(--ease);
        }
        .avatar-menu-item:active { background: var(--gray-100); }
        @media (hover: hover) { .avatar-menu-item:hover { background: var(--gray-100); } }
        .avatar-menu-item.danger { color: var(--err-fg); }
        .avatar-menu-item.danger:active { background: var(--err-bg); }
        @media (hover: hover) { .avatar-menu-item.danger:hover { background: var(--err-bg); } }
        .ami-icon { font-size: 15px; flex-shrink: 0; }
        .ami-label { flex: 1; }

        .ami-toggle {
            width: 36px; height: 20px;
            border-radius: var(--radius-pill);
            background: var(--gray-300);
            position: relative;
            transition: background var(--dur) var(--ease);
            flex-shrink: 0;
        }
        body.desktop-mode .ami-toggle { background: var(--melon); }
        .ami-thumb {
            width: 16px; height: 16px;
            background: #fff;
            border-radius: 50%;
            box-shadow: 0 1px 3px rgba(27,40,30,0.25);
            position: absolute;
            top: 2px; left: 2px;
            transition: left 0.22s var(--ease);
        }
        body.desktop-mode .ami-thumb { left: 18px; }

        /* ════════════════════════════════
        PAGE HEADER
        ════════════════════════════════ */
        .page-header-row { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-top:4px; margin-bottom:18px; flex-wrap:wrap; }
        .page-header-left { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .page-title { font-size:20px; font-weight:700; letter-spacing:-0.02em; line-height:1.25; color:var(--text1); }
        .field-select-inline { padding:7px 12px; font-size:12px; width:auto; }
        .badge-muted { background:var(--gray-100); color:var(--gray-600); }

        /* ════════════════════════════════
        GENERIC UTILITIES
        ════════════════════════════════ */
        .bold { font-weight:600; }
        .mb-10 { margin-bottom:10px; }
        .pad-sm { padding:12px 14px; }
        .opacity-muted { opacity:.7; }
        .inline-form { display:inline; }

        .text-orange     { color:#A9581A; }
        .text-red        { color:var(--err-fg); }
        .text-blue       { color:var(--info-fg); }
        .text-blue-deep  { color:#23496F; }
        .text-sky        { color:#5B8DC9; }
        .text-indigo     { color:#8C7FD1; }
        .text-melon      { color:var(--melon-dark); }
        .text-muted      { color:var(--text3); }
        .text-secondary  { color:var(--text2); }

        .link-edit { font-size:12px; font-weight:500; color:var(--info-fg); }
        .link-btn.danger { color:#B94A42; }
        .action-links { display:flex; gap:10px; }
        .empty-row-cell { text-align:center; padding:28px 20px; color:var(--text3); }

        /* ════════════════════════════════
        STICKY TABLE COLUMN (tabel rekap harian)
        ════════════════════════════════ */
        .sticky-col { position:sticky; left:0; z-index:1; }
        .sticky-col-header { position:sticky; left:0; z-index:2; background:var(--gray-50); }
        .sticky-col-body { background:inherit; }
        .sticky-col-total { background:var(--melon-light); }

        .day-cell { text-align:center; padding:8px 2px; }
        .day-cell-th { text-align:center; width:30px; }
        .day-cell-active { background:var(--melon-light); font-weight:600; color:var(--melon-dark); }
        .day-cell-empty { color:var(--gray-300); }

        /* ════════════════════════════════
        KPI CARDS
        ════════════════════════════════ */
        .kpi-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:10px; margin-bottom:14px; }
        .kpi-card {
            background: var(--do-surface);
            border: 1px solid var(--border-soft);
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-sm);
            padding: 14px 16px;
            position: relative;
            overflow: hidden;
            transition: box-shadow var(--dur) var(--ease), transform var(--dur) var(--ease);
        }
        @media (hover: hover) { .kpi-card:hover { box-shadow: var(--shadow-md); transform: translateY(-1px); } }
        .kpi-card::before {
            content: '';
            position: absolute;
            top: 16px; bottom: 16px; left: 0;
            width: 3px;
            border-radius: 0 3px 3px 0;
        }
        .kpi-card[data-accent="blue"]::before   { background: var(--do-blue); }
        .kpi-card[data-accent="green"]::before  { background: var(--do-success); }
        .kpi-card[data-accent="orange"]::before { background: var(--do-orange); }
        .kpi-card[data-accent="red"]::before    { background: var(--do-danger); }
        .kpi-card[data-accent="purple"]::before { background: var(--do-purple); }
        .kpi-card[data-accent="yellow"]::before { background: var(--do-warning); }
        .kpi-label { font-size:11px; font-weight:500; color:var(--do-text-muted); margin-bottom:4px; }
        .kpi-value { font-size:clamp(17px, 4.8vw, 21px); font-weight:700; letter-spacing:-0.02em; line-height:1.2; color:var(--do-text); font-variant-numeric:tabular-nums; }
        .kpi-sub   { font-size:11px; color:var(--do-text-subtle); margin-top:4px; }

        /* Compact KPI for dense rows (7 cards) */
        .kpi-card.compact { padding:10px 12px; }
        .kpi-card.compact .kpi-value { font-size:clamp(14px, 3vw, 18px); }
        .kpi-card.compact .kpi-label { font-size:10px; }

        /* ════════════════════════════════
        PROYEKSI / FORECAST BLOCK
        ════════════════════════════════ */
        .s-card-header-purple { background:var(--violet-bg); border-color:var(--violet-line); color:var(--violet-fg); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:4px; }
        .s-card-header-purple .sub { font-size:11px; color:var(--violet-fg); opacity:.8; font-weight:500; }
        .proj-body { padding:16px 18px; }

        .scenario-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-bottom:14px; }
        .scenario-box { border-radius:14px; padding:12px; text-align:center; transition: transform var(--dur) var(--ease), box-shadow var(--dur) var(--ease); }
        @media (hover: hover) { .scenario-box:hover { transform: translateY(-1px); box-shadow: var(--shadow-sm); } }
        .scenario-box .scenario-label { font-size:11px; font-weight:500; margin-bottom:4px; }
        .scenario-box .scenario-value { font-size:21px; font-weight:700; letter-spacing:-0.02em; }
        .scenario-box .scenario-unit { font-size:10px; }
        .scenario-optimis { background:var(--info-bg); }
        .scenario-optimis .scenario-label, .scenario-optimis .scenario-unit { color:var(--info-fg); }
        .scenario-optimis .scenario-value { color:#1F4470; }
        .scenario-realistis { background:var(--violet-bg); border:1.5px solid var(--violet-line); }
        .scenario-realistis .scenario-label, .scenario-realistis .scenario-unit { color:var(--violet-fg); }
        .scenario-realistis .scenario-value { color:#352D80; }
        .scenario-konservatif { background:var(--warn-bg); }
        .scenario-konservatif .scenario-label, .scenario-konservatif .scenario-unit { color:var(--warn-fg); }
        .scenario-konservatif .scenario-value { color:#6B400C; }

        .progress-block { margin-bottom:14px; }
        .progress-label-row { display:flex; justify-content:space-between; font-size:11px; color:var(--text3); margin-bottom:6px; }
        .progress-label-row .accent { font-weight:600; color:var(--violet-fg); }
        .progress-track { height:8px; background:var(--gray-100); border-radius:var(--radius-pill); overflow:hidden; position:relative; }
        .progress-fill { height:100%; background:#8C7FD1; border-radius:var(--radius-pill); transition: width 0.6s var(--ease); }
        .progress-marker { position:absolute; top:0; width:2px; height:100%; background:#D9645B; opacity:.7; }
        .progress-footer-row { display:flex; justify-content:space-between; font-size:10px; color:var(--text3); margin-top:4px; }
        .progress-footer-row .accent { color:#C4554D; }

        .vs-box { border-radius:14px; padding:12px 14px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; }
        .vs-box.positive { background:var(--ok-bg); }
        .vs-box.negative { background:var(--err-bg); }
        .vs-box.positive .vs-title, .vs-box.positive .vs-value, .vs-box.positive .vs-pct { color:var(--ok-fg); }
        .vs-box.negative .vs-title, .vs-box.negative .vs-value, .vs-box.negative .vs-pct { color:var(--err-fg); }
        .vs-title { font-size:11px; font-weight:600; }
        .vs-sub { font-size:11px; color:var(--text3); margin-top:2px; }
        .vs-value { font-size:17px; font-weight:700; letter-spacing:-0.01em; text-align:right; }
        .vs-pct { font-size:11px; text-align:right; }

        .subsection-label { font-size:12px; font-weight:600; color:var(--text2); margin-bottom:8px; }
        .table-sm { font-size:12px; }

        .pace-badge { font-size:10px; font-weight:600; background:var(--pace-bg); color:var(--pace-text); padding:2px 8px; border-radius:var(--radius-pill); display:inline-block; margin-bottom:4px; }
        .pace-track { height:5px; background:var(--gray-100); border-radius:var(--radius-pill); overflow:hidden; }
        .pace-fill { height:100%; border-radius:var(--radius-pill); background:var(--pace-text); }

        .notice { border-radius:10px; padding:9px 12px; font-size:11px; line-height:1.5; }
        .notice-danger  { background:var(--err-bg);  color:var(--err-fg); }
        .notice-warning { background:var(--warn-bg); color:var(--warn-fg); }
        .notice-success { background:var(--ok-bg);   color:var(--ok-fg); }
        .notice-stack { margin-top:12px; display:flex; flex-direction:column; gap:8px; }

        .empty-state { border:1px dashed var(--melon-mid); border-radius:14px; margin-bottom:12px; }

        .scenario-note-box { background:var(--violet-bg); border-radius:12px; padding:10px 12px; margin-top:10px; }

        /* ════════════════════════════════
        COMPARE VIEW LAYOUT
        ════════════════════════════════ */
        .cmp-section { margin-bottom:20px; }
        .cmp-section-title { font-size:13px; font-weight:700; color:var(--text2); margin-bottom:10px; text-transform:uppercase; letter-spacing:.5px; }
        .scenario-note-title { font-size:11px; font-weight:600; color:var(--violet-fg); margin-bottom:6px; }
        .scenario-note-row { display:flex; justify-content:space-between; font-size:12px; }
        .scenario-note-value { font-weight:600; color:#352D80; }

        /* ════════════════════════════════
        CHART CARDS
        ════════════════════════════════ */
        .chart-legend-row { display:flex; gap:14px; flex-wrap:wrap; margin-bottom:10px; }
        .legend-item { font-size:11px; color:var(--text3); display:flex; align-items:center; gap:6px; }
        .legend-dot { width:10px; height:10px; border-radius:3px; display:inline-block; background:var(--legend-color); }
        .legend-line { width:14px; border-top:2px solid var(--legend-color); display:inline-block; }
        .legend-line-dashed { border-top-style:dashed; }
        .chart-wrap { position:relative; width:100%; }
        .chart-wrap-lg { height:200px; }
        .chart-wrap-md { height:180px; }
        .chart-wrap-sm { height:120px; }
        .donut-legend { margin-top:10px; display:flex; flex-direction:column; gap:6px; }
        .donut-legend-row { display:flex; justify-content:space-between; align-items:center; }

        /* ════════════════════════════════
        RANKING TABLE
        ════════════════════════════════ */
        .rank-badge { width:22px; height:22px; border-radius:50%; background:var(--rank-color); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:10px; font-weight:600; }
        .co-tag { font-size:10px; color:#A9581A; }
        .mini-progress-label { font-size:10px; color:var(--text3); margin-bottom:4px; }
        .mini-progress-track { height:5px; background:var(--gray-100); border-radius:var(--radius-pill); overflow:hidden; }
        .mini-progress-fill { height:100%; border-radius:var(--radius-pill); background:var(--mini-color); }

        /* ════════════════════════════════
        HEALTH INDICATORS
        ════════════════════════════════ */
        .health-list { display:flex; flex-direction:column; gap:14px; }
        .health-row-label { display:flex; justify-content:space-between; margin-bottom:5px; }
        .health-row-label .lbl { font-size:11px; color:var(--text3); }
        .health-row-label .val { font-size:12px; font-weight:600; color:var(--health-color); }
        .health-track { height:5px; background:var(--gray-100); border-radius:var(--radius-pill); overflow:hidden; }
        .health-fill { height:100%; border-radius:var(--radius-pill); background:var(--health-color); }

        /* ════════════════════════════════
        MISC / SHARED (dipakai lintas modul: DO, Distribusi, dll)
        ════════════════════════════════ */
        .card-accent-orange { border-left:3px solid #E08A4B; }
        .s-card-header-warning { background:var(--warn-bg); border-color:var(--warn-line); color:var(--warn-fg); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:4px; }
        .warning-note { background:var(--warn-bg); padding:10px 18px; font-size:12px; color:var(--warn-fg); border-bottom:1px solid var(--warn-line); }

        /* ════════════════════════════════
        GENERIC UTILITIES (tambahan — Distribusi)
        ════════════════════════════════ */
        .hidden { display:none; }
        .text-center { text-align:center; }
        .mt-10 { margin-top:10px; }
        .row-between { display:flex; align-items:center; justify-content:space-between; gap:10px; }
        .form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px; }
        .field-sm { padding:7px 10px; font-size:12px; }
        .field-disabled-bg { background:var(--gray-50); }
        .text-note { font-size:11px; font-weight:400; color:var(--text3); }
        .text-green { color:#2E7D50; }
        .text-blue-dark { color:#23496F; }
        .text-melon-base { color:var(--melon); }
        .bg-melon-50 { background:var(--melon-50); }
        .bg-purple-50 { background:#F4F2FC; }
        .bg-mint-50 { background:var(--ok-bg); }
        .bg-red-50 { background:#FDF1F0; }
        .bg-blue-50 { background:var(--info-bg); }
        .bg-surface2 { background:var(--surface2); }
        .row-contract { background:#FFF8E6; }
        .min-w-110 { min-width:110px; }
        .card-pad-mt { padding:16px; margin-top:12px; }
        .border-warn-red { border-color:var(--err-line); }
        .border-mint { border-color:var(--ok-line); }

        /* ════════════════════════════════
        PAGE HEADER (tambahan)
        ════════════════════════════════ */
        .header-acts { display:flex; gap:8px; flex-wrap:wrap; }
        .btn-warning-outline { background:var(--warn-bg); color:var(--warn-fg); border-color:var(--warn-line); }
        .header-legend-note { font-size:10px; font-weight:500; color:var(--text3); }

        /* ════════════════════════════════
        BULK INPUT FORM
        ════════════════════════════════ */
        .bulk-form  { background:#F3F7FC; border:1px solid var(--info-line); border-radius:var(--radius-sm); padding:18px; margin-bottom:16px; }
        .bulk-form-title { font-size:14px; font-weight:700; letter-spacing:-0.01em; color:var(--info-fg); margin-bottom:14px; }
        .bulk-table { width:100%; border-collapse:collapse; font-size:12px; }
        .bulk-table th { background:#E3ECF8; color:#23496F; font-size:11px; font-weight:600; padding:9px 10px; text-align:left; }
        .bulk-table td { padding:7px 5px; border-bottom:1px solid #DCE7F5; vertical-align:middle; }
        .bulk-table tbody tr:last-child td { border-bottom:none; }
        .icon-remove-btn { background:none; border:none; color:#D9645B; font-size:18px; line-height:1; cursor:pointer; padding:0; transition:color var(--dur) var(--ease); }
        .icon-remove-btn:hover { color:#B94A42; }
        .bulk-summary-box { background:#fff; border:1px solid var(--info-line); border-radius:12px; padding:12px 14px; margin-bottom:12px; display:grid; grid-template-columns:repeat(2,1fr); gap:10px; }
        .bulk-summary-label { font-size:11px; color:var(--gray-500); }
        .bulk-summary-value { font-size:16px; font-weight:700; letter-spacing:-0.01em; }
        .link-add-row { background:none; border:none; font-size:12px; color:var(--info-fg); cursor:pointer; font-family:inherit; font-weight:600; }
        .link-add-row:hover { text-decoration:underline; text-underline-offset:3px; }

        /* ════════════════════════════════
        CHART TITLE (dipakai lintas modul)
        ════════════════════════════════ */
        .chart-title { font-size:12px; font-weight:700; color:var(--text2); letter-spacing:0; margin-bottom:12px; display:flex; align-items:center; gap:8px; }
        .chart-dot   { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
        .chart-wrap-xl { height:220px; }

        /* ════════════════════════════════
        RANKING TABLE (varian ringan — Distribusi)
        ════════════════════════════════ */
        .rank-table { width:100%; border-collapse:collapse; font-size:12px; font-variant-numeric:tabular-nums; }
        .rank-table th { background:var(--gray-50); color:var(--text3); font-size:11px; font-weight:600; letter-spacing:0.01em; padding:11px 14px; text-align:left; border-bottom:1px solid var(--border); }
        .rank-table th.r { text-align:right; }
        .rank-table td { padding:12px 14px; border-bottom:1px solid var(--gray-100); font-size:12px; }
        .rank-table td.r { text-align:right; }
        .rank-table tbody tr:last-child td { border-bottom:none; }
        .rank-num { width:24px; height:24px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:11px; font-weight:600; color:#fff; }
        .progress-bar  { width:100%; height:5px; background:var(--melon-light); border-radius:var(--radius-pill); overflow:hidden; margin-top:5px; }

        /* ════════════════════════════════
        INDIKATOR (varian baris kiri-kanan — Distribusi)
        ════════════════════════════════ */
        .ind-row   { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px; }
        .ind-meta  { flex:1; }
        .ind-title { font-size:12px; color:var(--text2); }
        .ind-track { width:100%; height:5px; background:var(--melon-light); border-radius:var(--radius-pill); margin-top:6px; overflow:hidden; }
        .ind-fill  { height:100%; border-radius:var(--radius-pill); }
        .ind-right { text-align:right; flex-shrink:0; }
        .ind-val   { font-size:14px; font-weight:700; letter-spacing:-0.01em; }
        .ind-note  { font-size:11px; color:var(--text3); }

        /* ════════════════════════════════
        PILL / REKOMENDASI BOX
        ════════════════════════════════ */
        .pill-box  { display:flex; flex-direction:column; gap:8px; }
        .pill      { display:flex; align-items:flex-start; gap:10px; padding:10px 14px; border-radius:14px; font-size:12px; line-height:1.5; border:1px solid transparent; }
        .pill-icon { font-size:14px; flex-shrink:0; margin-top:1px; }
        .pill-green  { background:var(--melon-50);  color:var(--melon-deep); border-color:var(--melon-100); }
        .pill-red    { background:var(--err-bg);    color:var(--err-fg);     border-color:var(--err-line); }
        .pill-orange { background:var(--warn-bg);   color:var(--warn-fg);    border-color:var(--warn-line); }
        .pill-blue   { background:var(--info-bg);   color:var(--info-fg);    border-color:var(--info-line); }

        /* ════════════════════════════════
        PAY FORM (Detail Distribusi)
        ════════════════════════════════ */
        .pay-form  { display:flex; gap:6px; margin-top:6px; align-items:center; }
        .pay-input { border:1px solid var(--border2); border-radius:10px; padding:6px 10px; font-size:12px; width:104px; font-family:inherit; transition:border-color var(--dur) var(--ease), box-shadow var(--dur) var(--ease); }
        .pay-input:focus { outline:none; border-color:var(--melon); box-shadow:var(--ring); }
        .pay-btn   { background:var(--melon); color:#fff; border:none; border-radius:10px; padding:6px 12px; font-size:12px; font-weight:600; cursor:pointer; white-space:nowrap; transition:background var(--dur) var(--ease), transform var(--dur) var(--ease); }
        .pay-btn:hover  { background:var(--melon-dark); }
        .pay-btn:active { transform:scale(0.97); }
        .action-links-wrap { flex-wrap:wrap; }

        /* ════════════════════════════════
        GRID TABLE (Rekap per Customer per Tanggal)
        ════════════════════════════════ */
        .grid-hdr { font-size:11px; font-weight:700; color:var(--text2); }
        .grid-sub { font-size:10px; color:var(--text3); font-weight:400; }
        .sticky-col-header-lg { position:sticky; left:0; z-index:10; background:var(--gray-50); }
        .sticky-col-body-lg   { position:sticky; left:0; z-index:5; }
        :where(.sticky-col-body-lg) { background:inherit; }
        .bg-contract { background:#FFF8E6; }
        .total-row-light { background:var(--gray-50); font-weight:700; border-top:1px solid var(--border2); }
        .table-footnote { padding:10px 14px; font-size:11px; color:var(--text3); display:flex; flex-wrap:wrap; gap:10px; }

        /* ════════════════════════════════
        SKENARIO CARD (Proyeksi Distribusi Bulanan)
        ════════════════════════════════ */
        .scenario-card { border:1px solid var(--border-soft); border-radius:var(--radius-sm); padding:14px 16px; background:var(--surface); box-shadow:var(--shadow-xs); transition:box-shadow var(--dur) var(--ease), transform var(--dur) var(--ease); }
        @media (hover: hover) { .scenario-card:hover { box-shadow:var(--shadow-sm); transform:translateY(-1px); } }
        .scenario-card-label { font-size:11px; font-weight:600; color:var(--text2); margin-bottom:4px; }
        .scenario-card-value { font-size:18px; font-weight:700; letter-spacing:-0.02em; }
        .scenario-card-desc  { font-size:11px; color:var(--text3); }

        /* ════════════════════════════════
        SIMULATOR "BAGAIMANA JIKA"
        ════════════════════════════════ */
        .simulator-box { background:var(--gray-50); border:1px solid var(--border-soft); border-radius:var(--radius-sm); padding:16px; }
        .simulator-title { font-size:12px; font-weight:600; color:var(--text2); margin-bottom:12px; }
        .range-row { display:flex; align-items:center; gap:12px; margin-bottom:10px; font-size:12px; }
        .range-label { min-width:130px; color:var(--text2); }
        .range-value { min-width:56px; text-align:right; font-weight:600; color:var(--text1); font-size:12px; font-variant-numeric:tabular-nums; }
        .simulator-result-grid { border-top:1px solid var(--border); padding-top:14px; display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .simulator-result-label { font-size:11px; color:var(--text3); }
        .simulator-result-value { font-size:21px; font-weight:700; letter-spacing:-0.02em; }

        /* ════════════════════════════════
        PERFORMANCE: skip layout/paint untuk section yang belum terlihat
        ════════════════════════════════ */
        .s-card { content-visibility: auto; contain-intrinsic-size: 0 300px; }
        .chart-wrap-xl { content-visibility: auto; contain-intrinsic-size: 0 220px; }

        /* ════════════════════════════════
        GOOGLE FONTS (dipakai oleh modul Analysis & DO)
        ════════════════════════════════ */
        @import url('https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&display=swap');

        /* ════════════════════════════════
        DO MODULE — GLOBAL TOKENS (lanjutan :root)
        Dipetakan ke sistem melon + neutral di atas
        ════════════════════════════════ */
        :root {
            --do-bg: var(--gray-50);
            --do-surface: var(--surface);
            --do-border: var(--border);
            --do-border-light: var(--gray-100);
            --do-text: var(--text1);
            --do-text-muted: var(--gray-600);
            --do-text-subtle: var(--gray-500);
            --do-primary: var(--melon);
            --do-primary-light: var(--melon-50);
            --do-primary-dark: var(--melon-dark);
            --do-success: #3E9B6A;
            --do-warning: #D9A441;
            --do-danger: #D9645B;
            --do-info: #5B8DC9;
            --do-blue: #5B8DC9;
            --do-purple: #8C7FD1;
            --do-orange: #E08A4B;
            --do-orange-light: #FDF1E4;
            --do-shadow-sm: var(--shadow-xs);
            --do-radius: var(--radius-sm);
            --do-radius-sm: 12px;
            /* durasi + easing saja, supaya valid dipakai sebagai "transition: <prop> var(--do-transition)" */
            --do-transition: 0.18s cubic-bezier(0.22, 0.61, 0.36, 1);
        }

        /* ════════════════════════════════
        DO — FORM CARD
        ════════════════════════════════ */
        .form-card {
            background: var(--do-surface);
            border: 1px solid var(--border-soft);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            max-width: 540px;
            margin-bottom: 20px;
        }
        .form-card-header {
            background: var(--do-surface);
            border-bottom: 1px solid var(--border-soft);
            padding: 16px 20px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: -0.01em;
            color: var(--do-text);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-card-body { padding: 20px; }
        .field-group { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .field-group .full-width { grid-column: 1 / -1; }
        .field-label-dox {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: var(--text2);
            margin-bottom: 6px;
        }
        .field-input-dox, .field-select-dox {
            width: 100%;
            padding: 10px 13px;
            font-size: 14px;
            border: 1px solid var(--border2);
            border-radius: var(--do-radius-sm);
            background: var(--do-surface);
            color: var(--do-text);
            box-shadow: var(--shadow-xs);
            transition: border-color var(--do-transition), box-shadow var(--do-transition);
            font-family: inherit;
        }
        .field-input-dox:hover, .field-select-dox:hover { border-color: var(--gray-400); }
        .field-input-dox:focus, .field-select-dox:focus {
            outline: none;
            border-color: var(--do-primary);
            box-shadow: var(--ring);
        }
        .field-input-dox::placeholder { color: var(--gray-400); }
        .field-input-dox.text-right { text-align: right; }
        .field-input-dox.text-center { text-align: center; }

        .btn-dox-primary {
            background: var(--do-primary);
            color: #fff;
            border: none;
            border-radius: var(--do-radius-sm);
            padding: 11px 18px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            width: 100%;
            box-shadow: 0 1px 2px rgba(29,74,43,0.25), inset 0 1px 0 rgba(255,255,255,0.12);
            transition: background var(--do-transition), box-shadow var(--do-transition), transform var(--do-transition);
        }
        .btn-dox-primary:hover  { background: var(--do-primary-dark); box-shadow: 0 4px 12px rgba(29,74,43,0.22), inset 0 1px 0 rgba(255,255,255,0.12); }
        .btn-dox-primary:active { transform: scale(0.985); }
        .btn-dox-secondary {
            background: var(--surface);
            color: var(--do-text);
            border: 1px solid var(--border2);
            border-radius: var(--do-radius-sm);
            padding: 11px 18px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            width: 100%;
            transition: background var(--do-transition), transform var(--do-transition);
        }
        .btn-dox-secondary:hover  { background: var(--gray-100); }
        .btn-dox-secondary:active { transform: scale(0.985); }

        .form-card-footer {
            padding: 16px 20px;
            background: var(--do-bg);
            border-top: 1px solid var(--border-soft);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .back-link-dox {
            font-size: 12px;
            font-weight: 500;
            color: var(--do-text-muted);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 10px;
            transition: color var(--do-transition);
        }
        .back-link-dox:hover { color: var(--melon-dark); }
        .title-row-dox {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 6px;
        }
        .title-row-dox .page-title-dox {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--do-text);
        }
        .form-info-dox {
            font-size: 12px;
            color: var(--do-text-subtle);
            padding: 4px 2px;
        }

        /* ════════════════════════════════
        DO — KPI GRID
        (definisi utama .kpi-grid / .kpi-card / .kpi-* ada di blok "KPI CARDS" di atas)
        ════════════════════════════════ */

        /* ════════════════════════════════
        DO — TABLE ENHANCEMENTS
        ════════════════════════════════ */
        .do-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            font-variant-numeric: tabular-nums;
        }
        .do-table th {
            background: var(--do-bg);
            color: var(--do-text-muted);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.01em;
            padding: 11px 14px;
            text-align: left;
            border-bottom: 1px solid var(--do-border);
        }
        .do-table th.r { text-align: right; }
        .do-table td {
            padding: 11px 14px;
            border-bottom: 1px solid var(--do-border-light);
            color: var(--do-text);
        }
        .do-table td.r { text-align: right; }
        .do-table tr.total-row td {
            background: var(--do-primary-light);
            font-weight: 700;
            border-top: 1px solid var(--melon-mid);
            color: var(--melon-deep);
        }
        .do-table tr.subtotal-row td {
            background: var(--do-bg);
            font-weight: 600;
            border-top: 1px solid var(--do-border);
        }

        /* ════════════════════════════════
        DO — NOTICE / ALERT BOXES
        ════════════════════════════════ */
        .notice-dox {
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 12px;
            border-left: 3px solid;
        }
        .notice-dox.success { background: var(--ok-bg);   color: var(--ok-fg);   border-color: #3E9B6A; }
        .notice-dox.warning { background: var(--warn-bg); color: var(--warn-fg); border-color: #D9A441; }
        .notice-dox.danger  { background: var(--err-bg);  color: var(--err-fg);  border-color: #D9645B; }
        .notice-dox.info    { background: var(--info-bg); color: var(--info-fg); border-color: #5B8DC9; }

        /* ════════════════════════════════
        DO — VS BOX / HEALTH / SCENARIO
        (sudah didefinisikan di blok "PROYEKSI / FORECAST" dan "HEALTH INDICATORS" di atas)
        ════════════════════════════════ */

        /* ════════════════════════════════
        DO — CHART LEGEND
        ════════════════════════════════ */
        .chart-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-bottom: 12px;
        }
        .leg-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: var(--text3);
        }
        .leg-sq {
            width: 10px;
            height: 10px;
            border-radius: 3px;
            flex-shrink: 0;
        }
        .leg-dash {
            width: 18px;
            border-top: 2.5px dashed;
            flex-shrink: 0;
        }

        /* ════════════════════════════════
        DO — SECTION HEADERS
        ════════════════════════════════ */
        .section-header-dox {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .section-title-dox {
            font-size: 15px;
            font-weight: 700;
            letter-spacing: -0.015em;
            color: var(--do-text);
        }
        .section-link-dox {
            font-size: 12px;
            font-weight: 600;
            color: var(--melon-dark);
            transition: color var(--do-transition);
        }
        .section-link-dox:hover { color: var(--melon-deep); }

        /* ════════════════════════════════
        DO — SCROLLBAR (custom)
        ════════════════════════════════ */
        .scroll-x::-webkit-scrollbar { height: 6px; }
        .scroll-x::-webkit-scrollbar-track { background: transparent; }
        .scroll-x::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: var(--radius-pill);
        }
        .scroll-x::-webkit-scrollbar-thumb:hover { background: var(--gray-400); }

        /* ════════════════════════════════
        DO — RESPONSIVE
        ════════════════════════════════ */
        @media (max-width: 768px) {
            .kpi-grid { grid-template-columns: repeat(2, 1fr); }
            .scenario-grid { grid-template-columns: 1fr; }
            .page-header-row {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        /* ════════════════════════════════
        REDUCED MOTION
        ════════════════════════════════ */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
                scroll-behavior: auto !important;
            }
        }

        /* ════════════════════════════════════════════════════════════
        POLISH LAYER — taruh paling akhir supaya meng-override di atas
        Fokus: kedalaman (depth), floating nav, input "inset", KPI hidup
        ════════════════════════════════════════════════════════════ */
        :root {
            --nav-h: 78px;
            --lift-1: 0 0 0 1px rgba(27,40,30,0.045), 0 1px 2px rgba(27,40,30,0.04), 0 8px 24px -8px rgba(27,40,30,0.10);
            --lift-2: 0 0 0 1px rgba(27,40,30,0.05),  0 2px 4px rgba(27,40,30,0.05), 0 16px 36px -10px rgba(27,40,30,0.18);
            --btn-grad: linear-gradient(180deg, #3F8E57 0%, #327A47 100%);
            --btn-grad-hover: linear-gradient(180deg, #388650 0%, #2B6D3E 100%);
        }

        /* ── Latar halaman: tint hijau lembut di pojok, kartu putih jadi "melayang" ── */
        body,
        body.desktop-mode {
            background-color: #F3F6F3;
            background-image:
                radial-gradient(760px 300px at 100% -60px, rgba(169,205,178,0.42), transparent 70%),
                radial-gradient(520px 260px at 0% 0%, rgba(213,232,218,0.55), transparent 70%);
            background-repeat: no-repeat;
        }

        /* ── Tipografi: hierarki lebih tegas ── */
        .page-title,
        .title-row-dox .page-title-dox { font-size: 22px; font-weight: 800; letter-spacing: -0.03em; }
        .section-title,
        .section-title-dox            { font-size: 16px; font-weight: 700; letter-spacing: -0.02em; }
        .s-card-header,
        .form-card-header             { font-size: 14px; font-weight: 700; }
        .brand-name                   { font-size: 16px; font-weight: 800; letter-spacing: -0.03em; }

        /* ── Header: bersih, ikon brand jadi satu-satunya titik warna ── */
        .app-header {
            background: rgba(255,255,255,0.82);
            border-bottom: none;
            box-shadow: 0 1px 0 rgba(27,40,30,0.05), 0 10px 28px -18px rgba(27,40,30,0.25);
        }
        .brand-icon {
            background: linear-gradient(145deg, #55A672 0%, #2F7A45 100%);
            box-shadow: 0 8px 16px -6px rgba(47,122,69,0.55), inset 0 1px 0 rgba(255,255,255,0.35);
            border-radius: 13px;
        }
        .avatar-btn { box-shadow: 0 0 0 3px rgba(255,255,255,0.9), 0 2px 8px rgba(27,40,30,0.12); border-color: transparent; }

        /* ── Kartu: tanpa border keras, shadow berlapis ── */
        .s-card,
        .card,
        .form-card,
        .scenario-card {
            border: none;
            box-shadow: var(--lift-1);
        }
        @media (hover: hover) {
            .card:hover, .scenario-card:hover { box-shadow: var(--lift-2); transform: translateY(-2px); }
        }
        .s-card-header {
            background: linear-gradient(180deg, #FFFFFF 0%, #FAFCFA 100%);
            padding: 15px 18px;
        }

        /* ── KPI: label kecil, angka besar, blob warna lembut di pojok ── */
        .kpi-card {
            border: none;
            box-shadow: var(--lift-1);
            padding: 16px 16px 15px;
            border-radius: 18px;
        }
        .kpi-card::before {
            top: -34px; right: -34px; left: auto; bottom: auto;
            width: 104px; height: 104px;
            border-radius: 50%;
            background: var(--melon);
            opacity: 0.11;
        }
        .kpi-label { font-size: 11.5px; font-weight: 600; color: var(--gray-500); margin-bottom: 6px; }
        .kpi-value { font-size: clamp(20px, 5.6vw, 26px); font-weight: 800; letter-spacing: -0.035em; }
        .kpi-sub   { font-size: 11px; margin-top: 6px; }
        @media (hover: hover) { .kpi-card:hover { box-shadow: var(--lift-2); transform: translateY(-2px); } }

        /* ── Input: latar abu tipis, berubah putih + ring saat fokus ── */
        .field-input,
        .field-select,
        .field-input-dox,
        .field-select-dox {
            min-height: 44px;
            background: var(--gray-50);
            border: 1px solid transparent;
            border-radius: 14px;
            box-shadow: inset 0 0 0 1px var(--gray-200);
            transition: background var(--dur) var(--ease), box-shadow var(--dur) var(--ease);
        }
        .field-input:hover, .field-select:hover,
        .field-input-dox:hover, .field-select-dox:hover {
            border-color: transparent;
            box-shadow: inset 0 0 0 1px var(--gray-300);
        }
        .field-input:focus, .field-select:focus,
        .field-input-dox:focus, .field-select-dox:focus {
            background: #fff;
            border-color: transparent;
            box-shadow: inset 0 0 0 1.5px var(--melon), 0 0 0 4px rgba(53,127,75,0.14);
        }
        .field-sm, .field-select-inline { min-height: 0; }
        .field-label, .field-label-dox { font-size: 11.5px; font-weight: 600; color: var(--gray-600); margin-bottom: 7px; }

        /* ── Tombol: gradient tipis + shadow berwarna, naik sedikit saat hover ── */
        .btn-primary,
        .btn-dox-primary,
        .pay-btn {
            background: var(--btn-grad);
            box-shadow: 0 8px 18px -8px rgba(47,122,69,0.65), 0 1px 2px rgba(29,74,43,0.25), inset 0 1px 0 rgba(255,255,255,0.18);
        }
        .btn-primary:hover,
        .btn-dox-primary:hover,
        .pay-btn:hover {
            background: var(--btn-grad-hover);
            transform: translateY(-1px);
            box-shadow: 0 12px 22px -8px rgba(47,122,69,0.7), 0 1px 2px rgba(29,74,43,0.25), inset 0 1px 0 rgba(255,255,255,0.18);
        }
        .btn-primary:active,
        .btn-dox-primary:active,
        .pay-btn:active { transform: translateY(0) scale(0.985); box-shadow: 0 2px 6px -2px rgba(47,122,69,0.5), inset 0 1px 2px rgba(0,0,0,0.12); }
        .btn-secondary,
        .btn-dox-secondary { background: #fff; box-shadow: inset 0 0 0 1px var(--gray-200), var(--shadow-xs); border-color: transparent; }
        .btn-secondary:hover,
        .btn-dox-secondary:hover { background: var(--melon-50); box-shadow: inset 0 0 0 1px var(--melon-mid), var(--shadow-xs); }

        /* ── Bottom nav: floating pill, bukan bar menempel ── */
        .bottom-nav {
            left: 12px; right: 12px;
            bottom: calc(10px + var(--safe-bottom));
            padding: 6px 6px;
            border: 1px solid rgba(255,255,255,0.7);
            border-radius: 28px;
            background: rgba(255,255,255,0.84);
            box-shadow: 0 0 0 1px rgba(27,40,30,0.05), 0 2px 6px rgba(27,40,30,0.06), 0 18px 40px -10px rgba(27,40,30,0.28);
        }
        .nav-item { padding: 7px 2px 5px; border-radius: 22px; }
        .nav-pill { width: 48px; height: 30px; transition: background var(--dur) var(--ease), width 0.24s var(--ease), transform var(--dur) var(--ease); }
        .nav-item.active .nav-pill { width: 60px; background: var(--melon-light); box-shadow: inset 0 0 0 1px var(--melon-100); }
        .nav-item.active .nav-label { font-weight: 700; }
        @media (min-width: 520px) {
            body:not(.desktop-mode) .bottom-nav {
                left: 50%; right: auto;
                width: 456px; max-width: calc(100% - 24px);
                border-radius: 28px;
            }
        }

        /* ── Drawer ── */
        .drawer { border-radius: 30px 30px 0 0; box-shadow: 0 -12px 48px -12px rgba(27,40,30,0.35); }
        .drawer-item { background: #fff; border: none; box-shadow: 0 0 0 1px rgba(27,40,30,0.05), 0 4px 12px -4px rgba(27,40,30,0.10); border-radius: 18px; padding: 15px 6px; }
        .drawer-item:active { background: var(--melon-50); }

        /* ── Menu avatar & flash ── */
        .avatar-menu { border: none; box-shadow: var(--lift-2); border-radius: 20px; }
        .flash-ok, .flash-err { box-shadow: 0 8px 20px -10px rgba(27,40,30,0.25); }

        /* ── Tabel: lebih lega, baris pertama/terakhir ikut padding kartu ── */
        .mob-table :where(th), .do-table th, .rank-table th {
            background: #F6F8F6;
            color: var(--gray-500);
            font-weight: 600;
            padding-top: 12px; padding-bottom: 12px;
        }
        .mob-table :where(td), .do-table td, .rank-table td { padding-top: 13px; padding-bottom: 13px; }
        .mob-table th:first-child, .mob-table td:first-child,
        .do-table th:first-child,  .do-table td:first-child,
        .rank-table th:first-child, .rank-table td:first-child { padding-left: 18px; }
        .mob-table th:last-child, .mob-table td:last-child,
        .do-table th:last-child,  .do-table td:last-child,
        .rank-table th:last-child, .rank-table td:last-child { padding-right: 18px; }

        /* ── Badge & pill: pastel, ada titik status ── */
        .badge { padding: 4px 11px; font-weight: 600; }
        .badge-green::before, .badge-red::before, .badge-orange::before, .badge-blue::before {
            content: '';
            width: 6px; height: 6px;
            border-radius: 50%;
            background: currentColor;
            opacity: 0.75;
            flex-shrink: 0;
        }
        .pill { border-radius: 16px; padding: 11px 14px; }
        .notice-dox { border-radius: 14px; border-left-width: 4px; }

        /* ── Progress: lebih tebal dan mulus ── */
        .progress-track { height: 10px; }
        .progress-fill  { background: linear-gradient(90deg, #A79CE0, #7F72CC); }

        /* ── Satu momen masuk halaman: section naik pelan berurutan ── */
        @keyframes rise { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
        main { animation: none; }
        main > .s-card,
        main > .kpi-grid,
        main > .page-header-row,
        main > .form-card,
        .content-inner > .s-card,
        .content-inner > .kpi-grid,
        .content-inner > .page-header-row,
        .content-inner > .form-card {
            animation: rise 0.5s var(--ease) backwards;
        }
        main > :nth-child(2), .content-inner > :nth-child(2) { animation-delay: 0.05s; }
        main > :nth-child(3), .content-inner > :nth-child(3) { animation-delay: 0.10s; }
        main > :nth-child(4), .content-inner > :nth-child(4) { animation-delay: 0.15s; }
        main > :nth-child(5), .content-inner > :nth-child(5) { animation-delay: 0.20s; }
        main > :nth-child(n+6), .content-inner > :nth-child(n+6) { animation-delay: 0.25s; }

        /* ════════════════════════════════════════════════════════════
        COLOR PASS — taruh setelah POLISH LAYER
        Header melon pekat sebagai jangkar warna, sisanya pastel tonal
        ════════════════════════════════════════════════════════════ */
        :root {
            --zebra: #F6FAF6;
            --head-a: #2F7D47;
            --head-b: #276B3C;
            --head-c: #1F5A33;
        }

        /* ── Latar halaman: tint hijau turun dari header ── */
        body,
        body.desktop-mode {
            background-color: #EEF4EF;
            background-image:
                linear-gradient(180deg, rgba(190,221,200,0.55) 0, rgba(238,244,239,0) 360px),
                radial-gradient(700px 320px at 100% 0, rgba(169,205,178,0.35), transparent 70%);
            background-repeat: no-repeat;
        }

        /* ── Header: gradient melon pekat, teks putih, tanpa border pill ── */
        .app-header {
            background:
                radial-gradient(640px 170px at 92% -50px, rgba(255,255,255,0.16), transparent 70%),
                linear-gradient(135deg, var(--head-a) 0%, var(--head-b) 55%, var(--head-c) 100%);
            -webkit-backdrop-filter: none;
            backdrop-filter: none;
            border-bottom: none;
            box-shadow: 0 14px 32px -16px rgba(31,90,51,0.65);
        }
        .brand-name { color: #fff; }
        .brand-sub  { color: rgba(255,255,255,0.68); }
        .brand-icon {
            background: rgba(255,255,255,0.16);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.26), 0 8px 16px -8px rgba(0,0,0,0.4);
        }
        .avatar-btn {
            background: rgba(255,255,255,0.18);
            border-color: rgba(255,255,255,0.4);
            color: #fff;
            box-shadow: 0 0 0 3px rgba(255,255,255,0.10);
        }
        .avatar-btn:hover  { background: rgba(255,255,255,0.28); }
        .avatar-dot { background: rgba(255,255,255,0.55); border-color: var(--head-b); }
        body.desktop-mode .avatar-dot { background: #B7E4C3; }

        .logout-btn,
        .desktop-toggle-btn {
            background: rgba(255,255,255,0.10);
            border-color: rgba(255,255,255,0.28);
            color: #fff;
        }
        .logout-btn:hover,
        .desktop-toggle-btn:hover  { background: rgba(255,255,255,0.20); color: #fff; }
        .logout-btn:active,
        .desktop-toggle-btn:active { background: rgba(255,255,255,0.28); }
        .toggle-dot { background: rgba(255,255,255,0.45); }
        body.desktop-mode .toggle-dot { background: #B7E4C3; }

        .header-desktop-nav-row { border-top-color: rgba(255,255,255,0.14); }
        .desktop-nav-item {
            color: rgba(255,255,255,0.82);
            background: transparent;
            border-color: transparent;
        }
        .desktop-nav-item:hover  { background: rgba(255,255,255,0.13); color: #fff; }
        .desktop-nav-item:active { background: rgba(255,255,255,0.20); }
        .desktop-nav-item.active {
            background: #fff;
            color: var(--melon-deep);
            box-shadow: 0 6px 14px -6px rgba(0,0,0,0.35);
        }

        /* ── KPI: tiap kartu punya tint + blob dengan hue sendiri (4 hue, low-chroma) ── */
        .kpi-card {
            --k: var(--melon);
            --k-soft: #EDF6EF;
            background: linear-gradient(160deg, var(--k-soft) 0%, #FFFFFF 64%);
        }
        .kpi-card:nth-child(4n+2) { --k: #5B8DC9; --k-soft: #EDF3FB; }
        .kpi-card:nth-child(4n+3) { --k: #D9A441; --k-soft: #FCF4E3; }
        .kpi-card:nth-child(4n+4) { --k: #8C7FD1; --k-soft: #F1EFFB; }
        .kpi-card[data-accent="green"]  { --k: #3E9B6A; --k-soft: #EDF6EF; }
        .kpi-card[data-accent="blue"]   { --k: #5B8DC9; --k-soft: #EDF3FB; }
        .kpi-card[data-accent="orange"] { --k: #E08A4B; --k-soft: #FDF1E4; }
        .kpi-card[data-accent="red"]    { --k: #D9645B; --k-soft: #FCEFEE; }
        .kpi-card[data-accent="purple"] { --k: #8C7FD1; --k-soft: #F1EFFB; }
        .kpi-card::before { background: var(--k); opacity: 0.16; }
        /* warna angka bawaan view (inline) dijinakkan sedikit, tanpa mengubah maknanya */
        .kpi-value { filter: saturate(0.82); }

        /* ── Header kartu & tabel: tint melon lembut ── */
        .s-card-header:not(.s-card-header-purple):not(.s-card-header-warning) {
            background:
                linear-gradient(90deg, var(--melon-light) 0%, rgba(255,255,255,0) 78%),
                #fff;
            border-bottom-color: var(--melon-100);
        }
        .mob-table th,
        .do-table th,
        .rank-table th { background: #EDF4EE; color: var(--gray-600); }
        .mob-table tr.total-row td,
        .do-table tr.total-row td { background: var(--melon-100); color: var(--melon-deep); }
        tfoot td { background: #EDF4EE; }

        /* ── Chip / pill kecil ── */
        .badge-muted { background: var(--gray-100); color: var(--gray-600); }
        .field-select-inline { background: var(--melon-50); }

        /* ── Bottom nav & drawer ikut hangat ── */
        .nav-item.active .nav-pill { background: var(--melon-100); box-shadow: none; }
        .drawer-handle { background: var(--melon-mid); }

        /* ════════════════════════════════
        COMPARE VIEW — KPI + FLOW CARDS
        ════════════════════════════════ */
        .cmp-kpi-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 14px; }
        @media (min-width: 420px) { .cmp-kpi-grid { grid-template-columns: repeat(5, 1fr); } }
        .flow-card { padding: 20px 14px; }
        .flow-stage { display: flex; align-items: center; justify-content: center; gap: 10px; flex-wrap: wrap; }
        .flow-box { background: var(--surface); border: 0.5px solid var(--border); border-radius: var(--radius-sm);
                    padding: 10px 14px; min-width: 120px; text-align: center; }
        .flow-box.result { border-width: 1.5px; background: var(--melon-50); }
        .flow-label { font-size: 9px; color: var(--text3); font-weight: 600; text-transform: uppercase; letter-spacing: .3px; }
        .flow-value { font-size: 14px; font-weight: 700; margin-top: 3px; }
        .flow-op { font-size: 18px; font-weight: 700; color: var(--text3); }
        .flow-connector { display: flex; justify-content: center; padding: 6px 0; font-size: 18px; color: var(--text3); }
        @media (max-width: 480px) { .flow-box { min-width: 100px; padding: 8px 10px; } .flow-value { font-size: 12px; } }

        /* ── Compare view extras ── */
        .page-header  { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; }
        .page-title   { font-size: 15px; font-weight: 700; color: var(--text1); }
        .legend       { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 8px; }
        .legend-item  { display: flex; align-items: center; gap: 5px; font-size: 10px; color: var(--text3); }
        .legend-sw    { width: 10px; height: 10px; border-radius: 2px; flex-shrink: 0; }
        .legend-line  { width: 18px; height: 2px; flex-shrink: 0; }
        .growth-up   { color: var(--melon-dark); font-weight: 600; }
        .growth-down { color: #dc2626; font-weight: 600; }
        .growth-flat { color: var(--text3); }

    </style>
