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
        .card { background: var(--surface); border-radius: var(--radius-sm); border: 0.5px solid var(--border); }
        .s-card { background: var(--surface); border-radius: var(--radius-sm); border: 0.5px solid var(--border); margin-bottom: 10px; overflow: hidden; }
        .s-card-header { background: var(--melon-50); border-bottom: 0.5px solid var(--border); padding: 10px 14px; font-size: 12px; font-weight: 600; color: var(--text1); }

        .section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
        .section-title  { font-size: 13px; font-weight: 600; color: var(--text1); }
        .section-link   { font-size: 11px; font-weight: 500; color: var(--melon-dark); }

        .badge        { display: inline-flex; align-items: center; border-radius: 6px; padding: 2px 7px; font-size: 9px; font-weight: 600; }
        .badge-green  { background: #dcfce7; color: #166534; }
        .badge-red    { background: #fee2e2; color: #991b1b; }
        .badge-orange { background: #ffedd5; color: #9a3412; }
        .badge-blue   { background: #dbeafe; color: #1e40af; }

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

        .mob-table { width: 100%; border-collapse: collapse; font-size: 11px; }
        .mob-table :where(th) { background: #f8faf8; color: var(--text3); font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; padding: 7px 10px; white-space: nowrap; text-align: left; }
        .mob-table th.r { text-align: right; }
        .mob-table :where(td) { padding: 8px 10px; border-bottom: 0.5px solid #eef5ee; white-space: nowrap; color: var(--text1); }
        .mob-table td.r { text-align: right; }
        .mob-table :where(td.bold) { font-weight: 600; }
        .mob-table tbody :where(tr:last-child td) { border-bottom: none; }
        .mob-table tr.total-row td { background: var(--melon); color: #fff; font-weight: 600; }
        .mob-table tr.total-row td.muted { color: rgba(255,255,255,0.72); font-size: 10px; }
        .mob-table tr.hi td { background: #fffbeb; }
        .scroll-x { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .scroll-y { max-height: 240px; overflow-y: auto; -webkit-overflow-scrolling: touch; }

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
            border-top: 1.5px solid var(--border-color);
            background: var(--surface-muted);
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

            /* ════════════════════════════════
        PAGE HEADER
        ════════════════════════════════ */
        .page-header-row { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:4px; margin-bottom:12px; flex-wrap:wrap; }
        .page-header-left { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .page-title { font-size:15px; font-weight:600; color:var(--text1); }
        .field-select-inline { padding:6px 10px; font-size:12px; width:auto; }
        .badge-muted { background:#f0f0f0; color:#666; }

        /* ════════════════════════════════
        GENERIC UTILITIES
        ════════════════════════════════ */
        .bold { font-weight:600; }
        .mb-10 { margin-bottom:10px; }
        .pad-sm { padding:10px 12px; }
        .opacity-muted { opacity:.7; }
        .inline-form { display:inline; }

        .text-orange     { color:#c2410c; }
        .text-red        { color:#991b1b; }
        .text-blue       { color:#1d4ed8; }
        .text-blue-deep  { color:#185FA5; }
        .text-sky        { color:#378ADD; }
        .text-indigo     { color:#7F77DD; }
        .text-melon      { color:var(--melon-dark); }
        .text-muted      { color:var(--text3); }
        .text-secondary  { color:var(--text2); }

        .link-edit { font-size:11px; color:#2563eb; }
        .link-btn.danger { color:#dc2626; }
        .action-links { display:flex; gap:8px; }
        .empty-row-cell { text-align:center; padding:20px; color:var(--text3); }

        /* ════════════════════════════════
        STICKY TABLE COLUMN (tabel rekap harian)
        ════════════════════════════════ */
        .sticky-col { position:sticky; left:0; z-index:1; }
        .sticky-col-header { position:sticky; left:0; z-index:2; background:#f8faf8; }
        .sticky-col-body { background:#fff; }
        .sticky-col-total { background:var(--melon); }

        .day-cell { text-align:center; padding:6px 2px; }
        .day-cell-th { text-align:center; width:28px; }
        .day-cell-active { background:var(--melon-light); font-weight:600; color:var(--melon-dark); }
        .day-cell-empty { color:#d1d5db; }

        /* ════════════════════════════════
        KPI CARDS
        ════════════════════════════════ */
        .kpi-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:10px; }
        .kpi-card { padding:10px 12px; }
        .kpi-label { font-size:10px; color:var(--text3); }
        .kpi-value { font-size:18px; font-weight:600; }
        .kpi-sub { font-size:10px; color:var(--text3); }

        /* ════════════════════════════════
        PROYEKSI / FORECAST BLOCK
        ════════════════════════════════ */
        .s-card-header-purple { background:#EEEDFE; border-color:#CECBF6; color:#3C3489; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:4px; }
        .s-card-header-purple .sub { font-size:10px; color:#534AB7; font-weight:400; }
        .proj-body { padding:12px 14px; }

        .scenario-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; margin-bottom:12px; }
        .scenario-box { border-radius:8px; padding:10px; text-align:center; }
        .scenario-box .scenario-label { font-size:10px; margin-bottom:4px; }
        .scenario-box .scenario-value { font-size:20px; font-weight:600; }
        .scenario-box .scenario-unit { font-size:9px; }
        .scenario-optimis { background:#E6F1FB; }
        .scenario-optimis .scenario-label, .scenario-optimis .scenario-unit { color:#185FA5; }
        .scenario-optimis .scenario-value { color:#0C447C; }
        .scenario-realistis { background:#EEEDFE; border:2px solid #AFA9EC; }
        .scenario-realistis .scenario-label, .scenario-realistis .scenario-unit { color:#534AB7; }
        .scenario-realistis .scenario-value { color:#3C3489; }
        .scenario-konservatif { background:#FAEEDA; }
        .scenario-konservatif .scenario-label, .scenario-konservatif .scenario-unit { color:#854F0B; }
        .scenario-konservatif .scenario-value { color:#633806; }

        .progress-block { margin-bottom:12px; }
        .progress-label-row { display:flex; justify-content:space-between; font-size:10px; color:var(--text3); margin-bottom:4px; }
        .progress-label-row .accent { font-weight:600; color:#534AB7; }
        .progress-track { height:8px; background:#f0f0f0; border-radius:4px; overflow:hidden; position:relative; }
        .progress-fill { height:100%; background:#7F77DD; border-radius:4px; }
        .progress-marker { position:absolute; top:0; width:2px; height:100%; background:#E24B4A; opacity:.7; }
        .progress-footer-row { display:flex; justify-content:space-between; font-size:9px; color:var(--text3); margin-top:3px; }
        .progress-footer-row .accent { color:#E24B4A; }

        .vs-box { border-radius:8px; padding:9px 12px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center; }
        .vs-box.positive { background:#E1F5EE; }
        .vs-box.negative { background:#FCEBEB; }
        .vs-box.positive .vs-title, .vs-box.positive .vs-value, .vs-box.positive .vs-pct { color:#0F6E56; }
        .vs-box.negative .vs-title, .vs-box.negative .vs-value, .vs-box.negative .vs-pct { color:#A32D2D; }
        .vs-title { font-size:10px; font-weight:600; }
        .vs-sub { font-size:10px; color:var(--text3); margin-top:2px; }
        .vs-value { font-size:16px; font-weight:600; text-align:right; }
        .vs-pct { font-size:10px; text-align:right; }

        .subsection-label { font-size:11px; font-weight:600; color:var(--text2); margin-bottom:6px; }
        .table-sm { font-size:11px; }

        .pace-badge { font-size:10px; background:var(--pace-bg); color:var(--pace-text); padding:1px 6px; border-radius:4px; display:inline-block; margin-bottom:3px; }
        .pace-track { height:4px; background:#f0f0f0; border-radius:2px; overflow:hidden; }
        .pace-fill { height:100%; border-radius:2px; background:var(--pace-text); }

        .notice { border-radius:6px; padding:7px 9px; font-size:10px; }
        .notice-danger  { background:#FEF2F2; color:#991B1B; }
        .notice-warning { background:#FFFBEB; color:#92400E; }
        .notice-success { background:#F0FDF4; color:#166534; }
        .notice-stack { margin-top:10px; display:flex; flex-direction:column; gap:6px; }

        .empty-state { border:0.5px solid #86efac; border-radius:8px; margin-bottom:10px; }

        .scenario-note-box { background:#EEEDFE; border-radius:6px; padding:8px 10px; margin-top:8px; }
        .scenario-note-title { font-size:10px; font-weight:600; color:#534AB7; margin-bottom:4px; }
        .scenario-note-row { display:flex; justify-content:space-between; font-size:11px; }
        .scenario-note-value { font-weight:600; color:#3C3489; }

        /* ════════════════════════════════
        CHART CARDS
        ════════════════════════════════ */
        .chart-legend-row { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:8px; }
        .legend-item { font-size:10px; color:var(--text3); display:flex; align-items:center; gap:4px; }
        .legend-dot { width:10px; height:10px; border-radius:2px; display:inline-block; background:var(--legend-color); }
        .legend-line { width:14px; border-top:2px solid var(--legend-color); display:inline-block; }
        .legend-line-dashed { border-top-style:dashed; }
        .chart-wrap { position:relative; width:100%; }
        .chart-wrap-lg { height:200px; }
        .chart-wrap-md { height:180px; }
        .chart-wrap-sm { height:120px; }
        .donut-legend { margin-top:8px; display:flex; flex-direction:column; gap:4px; }
        .donut-legend-row { display:flex; justify-content:space-between; align-items:center; }

        /* ════════════════════════════════
        RANKING TABLE
        ════════════════════════════════ */
        .rank-badge { width:20px; height:20px; border-radius:50%; background:var(--rank-color); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:9px; font-weight:600; }
        .co-tag { font-size:9px; color:#b45309; }
        .mini-progress-label { font-size:9px; color:var(--text3); margin-bottom:3px; }
        .mini-progress-track { height:5px; background:#f0f0f0; border-radius:3px; overflow:hidden; }
        .mini-progress-fill { height:100%; border-radius:3px; background:var(--mini-color); }

        /* ════════════════════════════════
        HEALTH INDICATORS
        ════════════════════════════════ */
        .health-list { display:flex; flex-direction:column; gap:10px; }
        .health-row-label { display:flex; justify-content:space-between; margin-bottom:3px; }
        .health-row-label .lbl { font-size:10px; color:var(--text3); }
        .health-row-label .val { font-size:11px; font-weight:600; color:var(--health-color); }
        .health-track { height:4px; background:#f0f0f0; border-radius:2px; overflow:hidden; }
        .health-fill { height:100%; border-radius:2px; background:var(--health-color); }

        /* ════════════════════════════════
        MISC / SHARED (dipakai lintas modul: DO, Distribusi, dll)
        ════════════════════════════════ */
        .card-accent-orange { border-left:3px solid #f97316; }
        .s-card-header-warning { background:#fff7ed; border-color:#fed7aa; color:#9a3412; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:4px; }
        .warning-note { background:#fff7ed; padding:8px 14px; font-size:11px; color:#c2410c; border-bottom:0.5px solid #fed7aa; }

        /* ════════════════════════════════
        GENERIC UTILITIES (tambahan — Distribusi)
        ════════════════════════════════ */
        .hidden { display:none; }
        .text-center { text-align:center; }
        .mt-10 { margin-top:10px; }
        .row-between { display:flex; align-items:center; justify-content:space-between; gap:8px; }
        .form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:12px; }
        .field-sm { padding:6px 8px; font-size:12px; }
        .field-disabled-bg { background:var(--surface2); }
        .text-note { font-size:10px; font-weight:400; color:var(--text3); }
        .text-green { color:#059669; }
        .text-blue-dark { color:#1e40af; }
        .text-melon-base { color:var(--melon); }
        .bg-melon-50 { background:var(--melon-50); }
        .bg-purple-50 { background:#f5f3ff; }
        .bg-mint-50 { background:#d1fae5; }
        .bg-red-50 { background:#fef2f2; }
        .bg-surface2 { background:var(--surface2); }
        .row-contract { background:#fffbeb; }
        .min-w-110 { min-width:110px; }
        .card-pad-mt { padding:12px; margin-top:10px; }
        .border-warn-red { border-color:#fca5a5; }
        .border-mint { border-color:#d1fae5; }

        /* ════════════════════════════════
        PAGE HEADER (tambahan)
        ════════════════════════════════ */
        .header-acts { display:flex; gap:6px; flex-wrap:wrap; }
        .btn-warning-outline { background:#fff7ed; color:#9a3412; border-color:#fed7aa; }
        .header-legend-note { font-size:9px; font-weight:500; color:var(--text3); }

        /* ════════════════════════════════
        BULK INPUT FORM
        ════════════════════════════════ */
        .bulk-form  { background:#eff6ff; border:0.5px solid #bfdbfe; border-radius:var(--radius-sm); padding:14px; margin-bottom:14px; }
        .bulk-form-title { font-size:13px; font-weight:700; color:#1e40af; margin-bottom:12px; }
        .bulk-table { width:100%; border-collapse:collapse; font-size:12px; }
        .bulk-table th { background:#dbeafe; color:#1e3a5f; font-size:10px; font-weight:600; padding:6px 8px; text-align:left; }
        .bulk-table td { padding:5px 4px; border-bottom:0.5px solid #bfdbfe; vertical-align:middle; }
        .bulk-table tbody tr:last-child td { border-bottom:none; }
        .icon-remove-btn { background:none; border:none; color:#ef4444; font-size:18px; line-height:1; cursor:pointer; padding:0; }
        .bulk-summary-box { background:#fff; border:0.5px solid #bfdbfe; border-radius:8px; padding:10px 12px; margin-bottom:10px; display:grid; grid-template-columns:repeat(2,1fr); gap:8px; }
        .bulk-summary-label { font-size:10px; color:#64748b; }
        .bulk-summary-value { font-size:15px; font-weight:700; }
        .link-add-row { background:none; border:none; font-size:12px; color:#1e40af; cursor:pointer; font-family:inherit; font-weight:500; }

        /* ════════════════════════════════
        CHART TITLE (dipakai lintas modul)
        ════════════════════════════════ */
        .chart-title { font-size:10px; font-weight:700; color:var(--text3); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:10px; display:flex; align-items:center; gap:6px; }
        .chart-dot   { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
        .chart-wrap-xl { height:220px; }

        /* ════════════════════════════════
        RANKING TABLE (varian ringan — Distribusi)
        ════════════════════════════════ */
        .rank-table { width:100%; border-collapse:collapse; font-size:11px; }
        .rank-table th { background:var(--surface2); color:var(--text3); font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:7px 10px; text-align:left; }
        .rank-table th.r { text-align:right; }
        .rank-table td { padding:9px 10px; border-bottom:0.5px solid var(--border); font-size:11px; }
        .rank-table td.r { text-align:right; }
        .rank-table tbody tr:last-child td { border-bottom:none; }
        .rank-num { width:22px; height:22px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:10px; font-weight:600; color:#fff; }
        .progress-bar  { width:100%; height:4px; background:var(--melon-light); border-radius:2px; overflow:hidden; margin-top:4px; }

        /* ════════════════════════════════
        INDIKATOR (varian baris kiri-kanan — Distribusi)
        ════════════════════════════════ */
        .ind-row   { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:12px; }
        .ind-meta  { flex:1; }
        .ind-title { font-size:11px; color:var(--text2); }
        .ind-track { width:100%; height:5px; background:var(--melon-light); border-radius:3px; margin-top:5px; overflow:hidden; }
        .ind-fill  { height:100%; border-radius:3px; }
        .ind-right { text-align:right; flex-shrink:0; }
        .ind-val   { font-size:13px; font-weight:700; }
        .ind-note  { font-size:10px; color:var(--text3); }

        /* ════════════════════════════════
        PILL / REKOMENDASI BOX
        ════════════════════════════════ */
        .pill-box  { display:flex; flex-direction:column; gap:6px; }
        .pill      { display:flex; align-items:flex-start; gap:8px; padding:8px 10px; border-radius:8px; font-size:11px; }
        .pill-icon { font-size:13px; flex-shrink:0; margin-top:1px; }
        .pill-green  { background:var(--melon-50);  color:var(--melon-deep); border:0.5px solid var(--border); }
        .pill-red    { background:#fef2f2; color:#991b1b; border:0.5px solid #fca5a5; }
        .pill-orange { background:#fff7ed; color:#9a3412; border:0.5px solid #fed7aa; }
        .pill-blue   { background:#eff6ff; color:#1e40af; border:0.5px solid #bfdbfe; }

        /* ════════════════════════════════
        PAY FORM (Detail Distribusi)
        ════════════════════════════════ */
        .pay-form  { display:flex; gap:5px; margin-top:5px; align-items:center; }
        .pay-input { border:0.5px solid var(--border2); border-radius:6px; padding:5px 8px; font-size:11px; width:100px; font-family:inherit; }
        .pay-btn   { background:var(--melon); color:#fff; border:none; border-radius:6px; padding:5px 10px; font-size:11px; font-weight:600; cursor:pointer; white-space:nowrap; }
        .action-links-wrap { flex-wrap:wrap; }

        /* ════════════════════════════════
        GRID TABLE (Rekap per Customer per Tanggal)
        ════════════════════════════════ */
        .grid-hdr { font-size:10px; font-weight:700; color:var(--text2); }
        .grid-sub { font-size:9px;  color:var(--text3); font-weight:400; }
        .sticky-col-header-lg { position:sticky; left:0; z-index:10; background:#f8faf8; }
        .sticky-col-body-lg   { position:sticky; left:0; z-index:5; }
        :where(.sticky-col-body-lg) { background:#fff; }
        .bg-contract { background:#fffbeb; }
        .total-row-light { background:#f9fafb; font-weight:700; border-top:2px solid #e5e7eb; }
        .table-footnote { padding:8px 12px; font-size:10px; color:var(--text3); display:flex; flex-wrap:wrap; gap:8px; }

        /* ════════════════════════════════
        SKENARIO CARD (Proyeksi Distribusi Bulanan)
        ════════════════════════════════ */
        .scenario-card { border:0.5px solid var(--border); border-radius:var(--radius-sm); padding:10px 12px; }
        .scenario-card-label { font-size:10px; font-weight:600; color:var(--text2); margin-bottom:4px; }
        .scenario-card-value { font-size:17px; font-weight:700; }
        .scenario-card-desc  { font-size:10px; color:var(--text3); }

        /* ════════════════════════════════
        SIMULATOR "BAGAIMANA JIKA"
        ════════════════════════════════ */
        .simulator-box { background:var(--surface2); border-radius:var(--radius-sm); padding:12px; }
        .simulator-title { font-size:10px; font-weight:600; color:var(--text3); margin-bottom:10px; }
        .range-row { display:flex; align-items:center; gap:10px; margin-bottom:8px; font-size:12px; }
        .range-label { min-width:130px; color:var(--text2); }
        .range-value { min-width:55px; text-align:right; font-weight:600; color:var(--text1); font-size:12px; }
        .simulator-result-grid { border-top:0.5px solid var(--border); padding-top:10px; display:grid; grid-template-columns:1fr 1fr; gap:8px; }
        .simulator-result-label { font-size:10px; color:var(--text3); }
        .simulator-result-value { font-size:20px; font-weight:700; }

        /* ════════════════════════════════
        PERFORMANCE: skip layout/paint untuk section yang belum terlihat
        ════════════════════════════════ */
        .s-card {
            content-visibility: auto;
            contain-intrinsic-size: 0 300px; /* perkiraan tinggi sebelum benar-benar di-render */
        }

        .s-card { content-visibility: auto; contain-intrinsic-size: 0 280px; }
        .chart-wrap-xl { content-visibility: auto; contain-intrinsic-size: 0 220px; }
    </style>
