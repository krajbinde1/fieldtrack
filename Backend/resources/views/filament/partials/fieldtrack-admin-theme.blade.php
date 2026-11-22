<script>
    (function () {
        var root = document.documentElement;
        root.classList.add('fieldtrack-admin');
        function applyBody() {
            if (document.body) {
                document.body.classList.add('fieldtrack-admin');
            }
        }
        applyBody();
        if (!document.body) {
            document.addEventListener('DOMContentLoaded', applyBody);
        }
        if (!window.__ftAdminBackGuard) {
            window.__ftAdminBackGuard = true;
            window.addEventListener('pageshow', function (event) {
                if (event.persisted) {
                    window.location.reload();
                }
            });
        }
    })();
</script>
<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0" />
<meta http-equiv="Pragma" content="no-cache" />
<style>
    /*
     * Param FieldTrack admin design system.
     * Scoped to html.fieldtrack-admin so styles apply immediately from HEAD
     * (body class is added as soon as <body> exists).
     */
    html.fieldtrack-admin {
        --ft-navy: #0b1b3a;
        --ft-navy-2: #15224a;
        --ft-purple: #3b1d6d;
        --ft-purple-2: #5b3aa8;
        --ft-primary: #6d5ef6;
        --ft-primary-2: #4f46e5;
        --ft-primary-soft: rgba(109, 94, 246, 0.14);
        --ft-bg: #f4f6fb;
        --ft-header: #ffffff;
        --ft-card: #ffffff;
        --ft-text: #0f172a;
        --ft-muted: #64748b;
        --ft-border: rgba(15, 23, 42, 0.08);
        --ft-shadow: 0 1px 2px rgba(15, 23, 42, 0.05), 0 8px 24px rgba(15, 23, 42, 0.04);
        --ft-radius: 14px;
        --ft-radius-sm: 10px;
        --ft-amber-bg: #fef3c7;
        --ft-amber-fg: #b45309;
        --ft-blue-bg: #dbeafe;
        --ft-blue-fg: #1d4ed8;
        --ft-green-bg: #dcfce7;
        --ft-green-fg: #15803d;
        --ft-orange-bg: #ffedd5;
        --ft-orange-fg: #c2410c;
        --ft-red-bg: #fee2e2;
        --ft-red-fg: #b91c1c;
        --ft-header-height: 3.25rem;
        color-scheme: light;
        background: var(--ft-bg);
    }

    html.fieldtrack-admin,
    html.fieldtrack-admin body.fieldtrack-admin,
    html.fieldtrack-admin body.fi-body,
    html.fieldtrack-admin .fi-body {
        background: var(--ft-bg);
        color: var(--ft-text);
    }

    html.fieldtrack-admin.dark,
    html.fieldtrack-admin.dark body {
        color-scheme: light;
        background: var(--ft-bg);
    }

    html.fieldtrack-admin .fi-layout,
    html.fieldtrack-admin .fi-main-ctn,
    html.fieldtrack-admin .fi-page,
    html.fieldtrack-admin .fi-page-content,
    html.fieldtrack-admin .fi-header-widgets {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        background: transparent;
    }

    /* -------------------------------------------------------------------------
     * Sidebar — navy to purple, compact navigation items
     * ------------------------------------------------------------------------- */
    html.fieldtrack-admin .fi-sidebar,
    html.fieldtrack-admin .fi-sidebar-ctn,
    html.fieldtrack-admin aside.fi-sidebar,
    html.fieldtrack-admin .fi-sidebar-header,
    html.fieldtrack-admin .fi-sidebar-nav,
    html.fieldtrack-admin .fi-sidebar-footer {
        background: linear-gradient(180deg, var(--ft-navy) 0%, var(--ft-navy-2) 42%, var(--ft-purple) 100%) !important;
        color: rgba(255, 255, 255, 0.92);
        border-color: transparent !important;
        box-shadow: none;
    }

    html.fieldtrack-admin .fi-sidebar {
        top: 0 !important;
        height: 100vh !important;
        height: 100dvh !important;
        max-height: 100dvh !important;
        border-right: 0 !important;
        overflow: hidden;
    }

    html.fieldtrack-admin .fi-sidebar-nav {
        flex: 1 1 auto;
        min-height: 0;
        gap: 0.4rem;
        padding: 0.55rem 0.55rem 0.75rem;
        overflow-x: hidden;
        overflow-y: auto;
        overscroll-behavior: contain;
    }

    html.fieldtrack-admin .fi-sidebar-header {
        padding: 0.75rem 0.85rem 0.55rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
    }

    html.fieldtrack-admin .fi-sidebar .fi-logo,
    html.fieldtrack-admin .fi-sidebar .fi-logo-text,
    html.fieldtrack-admin .fi-sidebar .fi-brand,
    html.fieldtrack-admin .fi-sidebar a.fi-logo,
    html.fieldtrack-admin .fi-sidebar .fi-sidebar-header span {
        color: #fff !important;
        font-weight: 800;
        letter-spacing: -0.02em;
        font-size: 0.98rem;
    }

    html.fieldtrack-admin .fi-sidebar-nav-groups {
        gap: 0.45rem;
    }

    html.fieldtrack-admin .fi-sidebar-group-btn,
    html.fieldtrack-admin .fi-sidebar-group-button {
        padding-top: 0.28rem;
        padding-bottom: 0.28rem;
    }

    html.fieldtrack-admin .fi-sidebar-nav-groups,
    html.fieldtrack-admin .fi-sidebar-group {
        margin-bottom: 0;
    }

    html.fieldtrack-admin .fi-sidebar-group {
        gap: 0.1rem;
    }

    html.fieldtrack-admin .fi-sidebar-group + .fi-sidebar-group {
        margin-top: 0;
    }

    html.fieldtrack-admin .fi-sidebar-group-label,
    html.fieldtrack-admin .fi-sidebar-group-btn,
    html.fieldtrack-admin .fi-sidebar-group-button,
    html.fieldtrack-admin .fi-sidebar-group .fi-sidebar-group-label {
        color: rgba(255, 255, 255, 0.48) !important;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-size: 0.675rem !important;
        font-weight: 700 !important;
        padding-inline: 0.7rem;
        margin-top: 0.15rem;
        margin-bottom: 0.05rem;
    }

    html.fieldtrack-admin .fi-sidebar-item,
    html.fieldtrack-admin .fi-sidebar-nav-item {
        margin: 0.05rem 0;
    }

    html.fieldtrack-admin .fi-sidebar-item-btn,
    html.fieldtrack-admin .fi-sidebar-item-button,
    html.fieldtrack-admin .fi-sidebar-item > a,
    html.fieldtrack-admin .fi-sidebar-item a.fi-sidebar-item-btn,
    html.fieldtrack-admin .fi-sidebar-nav-item a {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        color: rgba(255, 255, 255, 0.88) !important;
        border-radius: 0.4rem !important;
        padding: 0.42rem 0.65rem !important;
        background: transparent !important;
        box-shadow: none !important;
        font-weight: 500;
        font-size: 0.875rem;
        line-height: 1.25;
        letter-spacing: 0;
    }

    html.fieldtrack-admin .fi-sidebar-item-btn::after,
    html.fieldtrack-admin .fi-sidebar-item-button::after,
    html.fieldtrack-admin .fi-sidebar-item > a::after,
    html.fieldtrack-admin .fi-sidebar-nav-item a::after {
        content: "";
        width: 0.38rem;
        height: 0.38rem;
        margin-left: auto;
        flex: 0 0 auto;
        border-top: 1.5px solid currentColor;
        border-right: 1.5px solid currentColor;
        transform: rotate(45deg);
        opacity: 0.45;
    }

    html.fieldtrack-admin .fi-sidebar-item-icon,
    html.fieldtrack-admin .fi-sidebar-item-btn svg,
    html.fieldtrack-admin .fi-sidebar-item-button svg,
    html.fieldtrack-admin .fi-sidebar-nav-item svg {
        width: 1.15rem;
        height: 1.15rem;
        color: rgba(255, 255, 255, 0.86) !important;
        stroke: currentColor;
    }

    html.fieldtrack-admin .fi-sidebar-item-label,
    html.fieldtrack-admin .fi-sidebar-item-btn span,
    html.fieldtrack-admin .fi-sidebar-item-button span {
        color: inherit !important;
    }

    html.fieldtrack-admin .fi-sidebar-item-btn:hover,
    html.fieldtrack-admin .fi-sidebar-item-button:hover,
    html.fieldtrack-admin .fi-sidebar-item > a:hover,
    html.fieldtrack-admin .fi-sidebar-nav-item a:hover {
        background: rgba(255, 255, 255, 0.08) !important;
        color: #fff !important;
    }

    html.fieldtrack-admin .fi-sidebar-item.fi-active > .fi-sidebar-item-btn,
    html.fieldtrack-admin .fi-sidebar-item.fi-active > .fi-sidebar-item-button,
    html.fieldtrack-admin .fi-sidebar-item.fi-active > a,
    html.fieldtrack-admin .fi-sidebar-item.fi-active .fi-sidebar-item-btn,
    html.fieldtrack-admin .fi-sidebar-nav-item.fi-active > a,
    html.fieldtrack-admin .fi-sidebar-item.fi-active,
    html.fieldtrack-admin .fi-sidebar-item[aria-current="page"] > a,
    html.fieldtrack-admin .fi-sidebar-item[aria-current="page"] .fi-sidebar-item-btn {
        background: rgba(255, 255, 255, 0.1) !important;
        color: #fff !important;
        box-shadow: inset 2px 0 0 #ddd6fe !important;
    }

    html.fieldtrack-admin .fi-sidebar-item.fi-active .fi-sidebar-item-btn::after,
    html.fieldtrack-admin .fi-sidebar-item.fi-active > a::after,
    html.fieldtrack-admin .fi-sidebar-item[aria-current="page"] > a::after,
    html.fieldtrack-admin .fi-sidebar-item[aria-current="page"] .fi-sidebar-item-btn::after {
        opacity: 0.85;
    }

    html.fieldtrack-admin .fi-sidebar-item.fi-active .fi-sidebar-item-icon,
    html.fieldtrack-admin .fi-sidebar-item.fi-active svg {
        color: #fff !important;
    }

    html.fieldtrack-admin .fi-sidebar-group-items {
        display: flex;
        flex-direction: column;
        gap: 0.1rem;
    }

    html.fieldtrack-admin .fi-sidebar-close-overlay,
    html.fieldtrack-admin .fi-sidebar-open-overlay {
        background: rgba(11, 27, 58, 0.45);
    }

    html.fieldtrack-admin .fi-sidebar-nav .ft-main-dashboard-link {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        width: 100%;
        box-sizing: border-box;
        margin: 0 0 0.2rem;
        padding: 0.42rem 0.65rem;
        border-radius: 0.4rem;
        text-decoration: none;
        color: rgba(255, 255, 255, 0.88) !important;
        font-weight: 500;
        font-size: 0.875rem;
        letter-spacing: 0;
        line-height: 1.25;
        background: transparent;
        border: 0;
        box-shadow: none;
        transition: background 0.15s ease, color 0.15s ease;
    }

    html.fieldtrack-admin .fi-sidebar-nav .ft-main-dashboard-link:hover {
        color: #fff !important;
        background: rgba(255, 255, 255, 0.08);
        box-shadow: none;
    }

    html.fieldtrack-admin .fi-sidebar-nav .ft-main-dashboard-link.is-active,
    html.fieldtrack-admin .fi-sidebar-nav .ft-main-dashboard-link[aria-current="page"] {
        color: #fff !important;
        background: rgba(255, 255, 255, 0.1);
        box-shadow: inset 2px 0 0 #ddd6fe;
    }

    html.fieldtrack-admin .fi-sidebar-nav .ft-main-dashboard-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.25rem;
        height: 1.25rem;
        flex: 0 0 auto;
        border-radius: 0;
        background: transparent;
        color: rgba(255, 255, 255, 0.86);
    }

    html.fieldtrack-admin .fi-sidebar-nav .ft-main-dashboard-icon svg,
    html.fieldtrack-admin .fi-sidebar-nav .ft-main-dashboard-chevron svg {
        width: 1.15rem;
        height: 1.15rem;
        display: block;
    }

    html.fieldtrack-admin .fi-sidebar-nav .ft-main-dashboard-label {
        min-width: 0;
        color: inherit;
    }

    html.fieldtrack-admin .fi-sidebar-nav .ft-main-dashboard-chevron {
        display: inline-flex;
        margin-left: auto;
        color: rgba(255, 255, 255, 0.5);
        flex: 0 0 auto;
    }

    html.fieldtrack-admin .fi-sidebar-nav .ft-main-dashboard-chevron svg {
        width: 0.85rem;
        height: 0.85rem;
    }

    html.fieldtrack-admin .fi-sidebar-nav .ft-main-dashboard-link.is-active .ft-main-dashboard-chevron,
    html.fieldtrack-admin .fi-sidebar-nav .ft-main-dashboard-link[aria-current="page"] .ft-main-dashboard-chevron {
        color: rgba(255, 255, 255, 0.85);
    }

    html.fieldtrack-admin .fi-sidebar:not(.fi-sidebar-open) .ft-main-dashboard-link {
        justify-content: center;
        gap: 0;
        padding-inline: 0.4rem;
        box-shadow: none;
    }

    html.fieldtrack-admin .fi-sidebar:not(.fi-sidebar-open) .ft-main-dashboard-label,
    html.fieldtrack-admin .fi-sidebar:not(.fi-sidebar-open) .ft-main-dashboard-chevron,
    html.fieldtrack-admin .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item-btn::after,
    html.fieldtrack-admin .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item > a::after {
        display: none;
    }

    html.fieldtrack-admin.dark .fi-sidebar-nav .ft-main-dashboard-link,
    html.fieldtrack-admin.dark .fi-sidebar-nav .ft-main-dashboard-link:hover,
    html.fieldtrack-admin.dark .fi-sidebar-nav .ft-main-dashboard-link.is-active {
        color: #fff !important;
    }

    /* -------------------------------------------------------------------------
     * Top header
     * ------------------------------------------------------------------------- */
    html.fieldtrack-admin .fi-topbar,
    html.fieldtrack-admin .fi-topbar nav,
    html.fieldtrack-admin header.fi-topbar {
        background: var(--ft-header) !important;
        border-bottom: 1px solid var(--ft-border) !important;
        box-shadow: 0 1px 0 rgba(15, 23, 42, 0.03);
        min-height: var(--ft-header-height);
        height: var(--ft-header-height);
        padding-block: 0;
        padding-inline: 0.85rem;
        gap: 0.65rem;
        align-items: center;
    }

    html.fieldtrack-admin .fi-topbar-start {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex: 0 1 auto;
        min-width: 0;
    }

    html.fieldtrack-admin .fi-topbar-open-sidebar-btn,
    html.fieldtrack-admin .fi-topbar-close-sidebar-btn,
    html.fieldtrack-admin .fi-topbar-open-collapse-sidebar-btn,
    html.fieldtrack-admin .fi-topbar-close-collapse-sidebar-btn,
    html.fieldtrack-admin .fi-topbar button.fi-icon-btn,
    html.fieldtrack-admin .fi-topbar .fi-icon-btn {
        border-radius: 0.65rem;
        color: #334155;
        background: #f8fafc;
        border: 1px solid var(--ft-border);
        width: 2.25rem;
        height: 2.25rem;
    }

    html.fieldtrack-admin .fi-topbar .fi-logo,
    html.fieldtrack-admin .fi-topbar .fi-logo-text {
        display: none;
    }

    .ft-topbar-user {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        margin-left: auto;
        padding: 0.15rem 0.15rem 0.15rem 0.15rem;
        position: relative;
    }

    .ft-change-center {
        display: inline-flex;
        flex-direction: column;
        align-items: flex-end;
        justify-content: center;
        max-width: 14rem;
        padding: 0.28rem 0.7rem;
        border: 1px solid var(--ft-border);
        border-radius: 999px;
        background: #fff;
        line-height: 1.15;
        text-decoration: none;
    }

    .ft-change-center-name {
        max-width: 12rem;
        overflow: hidden;
        color: var(--ft-text);
        font-size: 0.72rem;
        font-weight: 700;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ft-change-center-actions {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    .ft-change-center-action {
        color: #6d5ef6;
        font-size: 0.68rem;
        font-weight: 700;
        background: transparent;
        border: 0;
        padding: 0;
        cursor: pointer;
        text-decoration: none;
    }

    .ft-clear-center {
        display: inline-flex;
        margin: 0;
        padding-left: 0.4rem;
        border-left: 1px solid var(--ft-border);
    }

    .ft-topbar-bell {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 999px;
        border: 1px solid var(--ft-border);
        background: #fff;
        color: #475569;
        cursor: default;
    }

    .ft-topbar-bell svg {
        width: 1.05rem;
        height: 1.05rem;
    }

    .ft-topbar-avatar {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.15rem;
        height: 2.15rem;
        border-radius: 999px;
        background: linear-gradient(135deg, #6d5ef6, #4f7dff);
        color: #fff;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.02em;
        flex: 0 0 auto;
    }

    .ft-topbar-identity {
        display: flex;
        flex-direction: column;
        min-width: 0;
        line-height: 1.15;
        padding-right: 0.15rem;
    }

    .ft-topbar-name {
        font-size: 0.82rem;
        font-weight: 700;
        color: var(--ft-text);
        white-space: nowrap;
        max-width: 10rem;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ft-topbar-role {
        font-size: 0.7rem;
        color: var(--ft-muted);
        white-space: nowrap;
    }

    .ft-topbar-profile {
        position: relative;
    }

    .ft-topbar-profile-trigger {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        list-style: none;
        cursor: pointer;
        border-radius: 999px;
        padding: 0.1rem 0.25rem 0.1rem 0.1rem;
    }

    .ft-topbar-profile-trigger::-webkit-details-marker {
        display: none;
    }

    .ft-topbar-profile-trigger::marker {
        content: '';
        display: none;
    }

    .ft-topbar-profile[open] .ft-topbar-profile-trigger {
        background: #f8fafc;
    }

    .ft-topbar-menu {
        position: absolute;
        right: 0;
        top: calc(100% + 0.45rem);
        z-index: 80;
        min-width: 12.5rem;
        padding: 0.4rem;
        border-radius: 0.9rem;
        background: #fff;
        border: 1px solid var(--ft-border);
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12);
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
    }

    .ft-topbar-menu-item {
        display: block;
        width: 100%;
        text-align: left;
        border: 0;
        background: transparent;
        color: var(--ft-text);
        font-size: 0.84rem;
        font-weight: 600;
        line-height: 1.2;
        padding: 0.62rem 0.75rem;
        border-radius: 0.65rem;
        text-decoration: none;
        cursor: pointer;
    }

    .ft-topbar-menu-item:hover {
        background: #f4f6fb;
        color: var(--ft-text);
    }

    .ft-topbar-menu-logout {
        color: #b42318;
    }

    .ft-topbar-menu-logout:hover {
        background: #fef2f2;
        color: #b42318;
    }

    html.fieldtrack-admin .fi-global-search-ctn,
    html.fieldtrack-admin .fi-global-search,
    html.fieldtrack-admin .fi-topbar .fi-global-search-ctn,
    html.fieldtrack-admin .fi-topbar .fi-global-search,
    html.fieldtrack-admin .ft-topbar-search {
        display: none !important;
    }

    html.fieldtrack-admin .fi-topbar-end {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex: 1 1 auto;
        min-width: 0;
        justify-content: flex-end;
    }

    html.fieldtrack-admin .fi-user-menu {
        display: none !important;
    }

    html.fieldtrack-admin .fi-topbar .fi-user-menu .fi-avatar {
        display: none;
    }

    html.fieldtrack-admin .fi-theme-switcher,
    html.fieldtrack-admin .fi-user-menu .fi-dropdown-list-item:has(svg[class*="moon"]),
    html.fieldtrack-admin .fi-user-menu .fi-dropdown-list-item:has(svg[class*="sun"]) {
        display: none !important;
    }

    @media (max-width: 767px) {
        .ft-topbar-identity {
            display: none;
        }
    }

    /*
     * Desktop: sidebar is flush with the viewport and the compact account
     * header sits only over the content column. Mobile keeps the header in
     * flow so the menu button, close button, and account controls stay usable.
     */
    @media (max-width: 1023px) {
        html.fieldtrack-admin .fi-body-has-topbar .fi-sidebar {
            top: var(--ft-header-height) !important;
            height: calc(100dvh - var(--ft-header-height)) !important;
            max-height: calc(100dvh - var(--ft-header-height)) !important;
        }

        html.fieldtrack-admin .fi-topbar-ctn {
            position: sticky;
            top: 0;
            z-index: 40;
            overflow: visible;
        }
    }

    @media (min-width: 1024px) {
        html.fieldtrack-admin .fi-body-has-topbar.fi-body-has-navigation .fi-topbar-ctn {
            position: fixed;
            top: 0;
            inset-inline-start: var(--sidebar-width);
            inset-inline-end: 0;
            z-index: 30;
            width: auto;
            overflow: visible;
        }

        html.fieldtrack-admin .fi-body-has-topbar.fi-body-has-navigation:has(.fi-main-sidebar:not(.fi-sidebar-open)) .fi-topbar-ctn {
            inset-inline-start: var(--collapsed-sidebar-width);
        }

        html.fieldtrack-admin .fi-body-has-topbar.fi-body-has-navigation .fi-main-sidebar:not(.fi-sidebar-open) {
            width: var(--collapsed-sidebar-width);
        }

        html.fieldtrack-admin .fi-body-has-topbar.fi-body-has-navigation .fi-main-ctn {
            padding-top: var(--ft-header-height);
            box-sizing: border-box;
        }
    }

    /* -------------------------------------------------------------------------
     * Main content — full width, light canvas
     * ------------------------------------------------------------------------- */
    html.fieldtrack-admin .fi-main,
    html.fieldtrack-admin .fi-main.fi-width-full,
    html.fieldtrack-admin .fi-main.fi-width-7xl,
    html.fieldtrack-admin .fi-main-ctn > .fi-main {
        width: 100% !important;
        max-width: none !important;
        margin-left: 0;
        margin-right: 0;
        background: var(--ft-bg);
        padding-top: 0;
        padding-bottom: 1rem;
        padding-left: 1rem;
        padding-right: 1rem;
        box-sizing: border-box;
    }

    @media (min-width: 768px) {
        html.fieldtrack-admin .fi-main,
        html.fieldtrack-admin .fi-main.fi-width-full,
        html.fieldtrack-admin .fi-main.fi-width-7xl {
            padding-left: 1.35rem;
            padding-right: 1.35rem;
        }
    }

    @media (min-width: 1280px) {
        html.fieldtrack-admin .fi-main,
        html.fieldtrack-admin .fi-main.fi-width-full,
        html.fieldtrack-admin .fi-main.fi-width-7xl {
            padding-left: 1.6rem;
            padding-right: 1.6rem;
        }
    }

    html.fieldtrack-admin .fi-page,
    html.fieldtrack-admin .fi-page-content,
    html.fieldtrack-admin .fi-page-main {
        margin-top: 0;
        padding-top: 0;
    }

    html.fieldtrack-admin .fi-page-header-main-ctn {
        padding-top: 0.75rem !important;
        padding-bottom: 0.75rem !important;
        row-gap: 0.75rem !important;
        column-gap: 0.75rem !important;
    }

    html.fieldtrack-admin .fi-page-main {
        gap: 0.75rem !important;
        min-width: 0;
    }

    html.fieldtrack-admin .fi-page.fi-page-has-sub-navigation .fi-page-main {
        gap: 0.75rem !important;
    }

    html.fieldtrack-admin .fi-header {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        gap: 0.5rem 1rem;
        margin: 0;
        padding: 0;
    }

    html.fieldtrack-admin .fi-header.fi-header-has-breadcrumbs .fi-header-actions-ctn {
        margin-top: 0 !important;
    }

    html.fieldtrack-admin .fi-breadcrumbs,
    html.fieldtrack-admin nav.fi-breadcrumbs,
    html.fieldtrack-admin .fi-header .fi-breadcrumbs {
        width: 100%;
        font-size: 0.75rem;
        color: var(--ft-muted);
        margin: 0 !important;
        order: -1;
    }

    html.fieldtrack-admin .fi-breadcrumbs a,
    html.fieldtrack-admin .fi-breadcrumbs-item,
    html.fieldtrack-admin .fi-breadcrumbs li {
        color: var(--ft-muted);
        font-weight: 550;
    }

    html.fieldtrack-admin .fi-header-heading,
    html.fieldtrack-admin .fi-header h1 {
        font-size: 1.7rem;
        font-weight: 800;
        line-height: 1.2;
        letter-spacing: -0.03em;
        color: var(--ft-text);
        margin: 0;
    }

    html.fieldtrack-admin .fi-header-subheading,
    html.fieldtrack-admin .fi-header p.fi-header-subheading {
        font-size: 0.875rem;
        color: var(--ft-muted);
        font-weight: 450;
        margin-top: 0.2rem;
        max-width: 46rem;
    }

    html.fieldtrack-admin .fi-header-actions-ctn,
    html.fieldtrack-admin .fi-ac-header-actions,
    html.fieldtrack-admin .fi-header-actions {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* -------------------------------------------------------------------------
     * Cards / sections / widgets
     * ------------------------------------------------------------------------- */
    html.fieldtrack-admin .fi-section,
    html.fieldtrack-admin .fi-sc-section,
    html.fieldtrack-admin .fi-wi-widget .fi-section,
    html.fieldtrack-admin .fi-wi-widget .fi-sc-section,
    html.fieldtrack-admin .fi-ta-ctn,
    html.fieldtrack-admin .fi-wi-stats-overview-stat,
    html.fieldtrack-admin .fi-wi-stats-overview .fi-section {
        background: var(--ft-card) !important;
        border: 1px solid var(--ft-border) !important;
        border-radius: var(--ft-radius) !important;
        box-shadow: var(--ft-shadow) !important;
    }

    html.fieldtrack-admin .fi-section-content,
    html.fieldtrack-admin .fi-sc-section-content,
    html.fieldtrack-admin .fi-section-header,
    html.fieldtrack-admin .fi-sc-section-header {
        padding-left: 1rem;
        padding-right: 1rem;
    }

    html.fieldtrack-admin .fi-section-content,
    html.fieldtrack-admin .fi-sc-section-content {
        padding-top: 0.9rem;
        padding-bottom: 0.9rem;
    }

    html.fieldtrack-admin .fi-wi-stats-overview {
        gap: 0.75rem;
    }

    html.fieldtrack-admin .fi-wi-stats-overview-stat {
        padding: 0.85rem 0.95rem;
    }

    html.fieldtrack-admin .fi-wi-stats-overview-stat-value,
    html.fieldtrack-admin .fi-stats-overview-stat-value {
        font-weight: 800;
        letter-spacing: -0.02em;
        color: var(--ft-text);
    }

    html.fieldtrack-admin .fi-wi-stats-overview-stat-label,
    html.fieldtrack-admin .fi-stats-overview-stat-label {
        color: var(--ft-muted);
        font-size: 0.78rem;
        font-weight: 650;
        text-transform: none;
    }

    /* -------------------------------------------------------------------------
     * Dashboard — compact premium layout (scoped to the dashboard page)
     * ------------------------------------------------------------------------- */
    html.fieldtrack-admin body.ft-dashboard-page .fi-main,
    html.fieldtrack-admin body.ft-dashboard-page .fi-page {
        padding-top: 0 !important;
        padding-block-start: 0 !important;
        margin-top: 0 !important;
    }

    html.fieldtrack-admin body.ft-dashboard-page .fi-page-header-main-ctn {
        padding-top: 14px !important;
        padding-bottom: 0 !important;
        padding-block: 14px 0 !important;
        padding-block-start: 14px !important;
        padding-block-end: 0 !important;
        row-gap: 0.65rem !important;
        margin-top: 0 !important;
        margin-bottom: 0 !important;
    }

    html.fieldtrack-admin body.ft-dashboard-page .fi-header-heading,
    html.fieldtrack-admin body.ft-dashboard-page .fi-header h1 {
        font-size: 1.2rem;
        font-weight: 750;
        letter-spacing: -0.025em;
        margin-bottom: 0 !important;
    }

    html.fieldtrack-admin body.ft-dashboard-page .fi-header-subheading:empty,
    html.fieldtrack-admin body.ft-dashboard-page .fi-header-widgets:empty,
    html.fieldtrack-admin body.ft-dashboard-page .fi-header-widgets:not(:has(.fi-wi-widget)) {
        display: none !important;
        margin: 0 !important;
        padding: 0 !important;
        min-height: 0 !important;
        height: 0 !important;
        overflow: hidden;
    }

    html.fieldtrack-admin body.ft-dashboard-page .fi-page-main,
    html.fieldtrack-admin body.ft-dashboard-page .fi-header-widgets,
    html.fieldtrack-admin body.ft-dashboard-page .fi-page-content {
        gap: 0.65rem !important;
        row-gap: 0.65rem !important;
        margin-top: 0 !important;
        padding-top: 0 !important;
        width: 100%;
        max-width: 100%;
    }

    html.fieldtrack-admin body.ft-dashboard-page .fi-header,
    html.fieldtrack-admin body.ft-dashboard-page .fi-page-content .fi-sc,
    html.fieldtrack-admin body.ft-dashboard-page .fi-page-content .fi-grid,
    html.fieldtrack-admin body.ft-dashboard-page .fi-wi-widget,
    html.fieldtrack-admin body.ft-dashboard-page .fi-wi-table {
        width: 100%;
        max-width: 100%;
    }

    html.fieldtrack-admin body.ft-dashboard-page .fi-page-content .fi-grid {
        --cols-default: repeat(1, minmax(0, 1fr)) !important;
        --cols-lg: repeat(1, minmax(0, 1fr)) !important;
        grid-template-columns: minmax(0, 1fr) !important;
    }

    html.fieldtrack-admin body.ft-dashboard-page .fi-wi-widget:has(.ft-dash-stats),
    html.fieldtrack-admin body.ft-dashboard-page .ft-dash-stats {
        grid-column: 1 / -1;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }

    html.fieldtrack-admin body.ft-dashboard-page .fi-ta .fi-ta-header-heading {
        font-size: 0.88rem;
        padding: 0.55rem 0.85rem 0.15rem;
    }

    html.fieldtrack-admin body.ft-dashboard-page .fi-ta .fi-ta-header-toolbar {
        padding: 0.4rem 0.85rem 0.5rem;
    }

    html.fieldtrack-admin body.ft-dashboard-page .fi-ta .fi-ta-header-cell,
    html.fieldtrack-admin body.ft-dashboard-page .fi-ta thead th {
        height: 2.1rem;
        padding: 0.32rem 0.7rem;
        font-size: 0.68rem;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        color: #64748b;
        background: #f6f7fb !important;
    }

    html.fieldtrack-admin body.ft-dashboard-page .fi-ta .fi-ta-cell,
    html.fieldtrack-admin body.ft-dashboard-page .fi-ta tbody td {
        min-height: 2.15rem;
        padding: 0.32rem 0.7rem;
        font-size: 0.8rem;
    }

    html.fieldtrack-admin body.ft-dashboard-page .fi-pagination {
        padding: 0.4rem 0.85rem;
    }

    .ft-dash-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.7rem;
        width: 100%;
        max-width: 100%;
        align-items: stretch;
        box-sizing: border-box;
    }

    html.fieldtrack-admin .ft-dash-card {
        display: flex;
        align-items: flex-start;
        gap: 0.7rem;
        min-height: 6.15rem;
        height: 100%;
        width: 100%;
        min-width: 0;
        padding: 0.8rem 0.85rem;
        border-radius: 14px;
        border: 1px solid #e8ecf4;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        text-decoration: none;
        color: inherit;
        box-sizing: border-box;
        transition: transform 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease;
    }

    html.fieldtrack-admin .ft-dash-card.is-link {
        cursor: pointer;
    }

    html.fieldtrack-admin .ft-dash-card.is-link:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
        border-color: rgba(91, 58, 168, 0.18);
    }

    .ft-dash-card-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.15rem;
        height: 2.15rem;
        border-radius: 0.7rem;
        flex: 0 0 auto;
        background: rgba(255, 255, 255, 0.72);
    }

    .ft-dash-card-icon svg {
        width: 1.1rem;
        height: 1.1rem;
    }

    .ft-dash-card-copy {
        display: flex;
        flex-direction: column;
        min-width: 0;
        flex: 1 1 auto;
        gap: 0.12rem;
    }

    .ft-dash-card-title {
        font-size: 0.72rem;
        font-weight: 700;
        color: #475569;
        line-height: 1.25;
    }

    .ft-dash-card-value {
        font-size: 1.28rem;
        font-weight: 800;
        letter-spacing: -0.03em;
        color: #0f172a;
        line-height: 1.15;
    }

    .ft-dash-card-hint {
        font-size: 0.68rem;
        font-weight: 550;
        color: #64748b;
        line-height: 1.25;
    }

    html.fieldtrack-admin .ft-dash-card--lavender { background: #eee9fe; border-color: #ddd6fe; }
    html.fieldtrack-admin .ft-dash-card--lavender .ft-dash-card-icon { color: #6d28d9; }
    html.fieldtrack-admin .ft-dash-card--sky { background: #e8f3fe; border-color: #d7e9fd; }
    html.fieldtrack-admin .ft-dash-card--sky .ft-dash-card-icon { color: #2563eb; }
    html.fieldtrack-admin .ft-dash-card--mint { background: #e9f8f1; border-color: #d5f0e3; }
    html.fieldtrack-admin .ft-dash-card--mint .ft-dash-card-icon { color: #059669; }
    html.fieldtrack-admin .ft-dash-card--peach { background: #fff1e8; border-color: #fde4d4; }
    html.fieldtrack-admin .ft-dash-card--peach .ft-dash-card-icon { color: #ea580c; }
    html.fieldtrack-admin .ft-dash-card--amber { background: #fef6e4; border-color: #fdecc8; }
    html.fieldtrack-admin .ft-dash-card--amber .ft-dash-card-icon { color: #d97706; }
    html.fieldtrack-admin .ft-dash-card--lilac { background: #f3eaff; border-color: #e7d7fe; }
    html.fieldtrack-admin .ft-dash-card--lilac .ft-dash-card-icon { color: #7c3aed; }
    html.fieldtrack-admin .ft-dash-card--blue { background: #eaf2ff; border-color: #d9e6ff; }
    html.fieldtrack-admin .ft-dash-card--blue .ft-dash-card-icon { color: #3b82f6; }
    html.fieldtrack-admin .ft-dash-card--sage { background: #eef6ee; border-color: #dceadc; }
    html.fieldtrack-admin .ft-dash-card--sage .ft-dash-card-icon { color: #16a34a; }

    html.fieldtrack-admin .ft-dash-card-value {
        color: #0f172a;
    }

    .ft-dash-confirmed {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        min-width: 6.75rem;
    }

    .ft-dash-confirmed-n {
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        min-width: 1.35rem;
        color: #0f172a;
    }

    .ft-dash-confirmed-bar {
        display: block;
        flex: 1 1 auto;
        height: 0.38rem;
        border-radius: 999px;
        background: #e8ecf4;
        overflow: hidden;
    }

    .ft-dash-confirmed-bar > span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #8b7cf6 0%, #6d8dff 100%);
    }

    @media (max-width: 1199px) {
        .ft-dash-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 639px) {
        .ft-dash-stats {
            grid-template-columns: minmax(0, 1fr);
        }
    }

    /* -------------------------------------------------------------------------
     * Tables
     * ------------------------------------------------------------------------- */
    html.fieldtrack-admin .fi-ta,
    html.fieldtrack-admin .fi-ta-ctn {
        width: 100%;
        max-width: 100%;
    }

    html.fieldtrack-admin .fi-ta-ctn {
        overflow: hidden;
    }

    html.fieldtrack-admin .fi-ta .fi-ta-header {
        background: transparent;
        padding: 0;
        border: 0;
    }

    html.fieldtrack-admin .fi-ta .fi-ta-header-heading {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--ft-text);
        padding: 0.75rem 0.9rem 0;
    }

    html.fieldtrack-admin .fi-ta .fi-ta-header-toolbar,
    html.fieldtrack-admin .fi-ta-header-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-end;
        gap: 0.5rem 0.65rem;
        padding: 0.7rem 0.9rem;
        background: #fff;
        border-bottom: 1px solid var(--ft-border);
    }

    html.fieldtrack-admin .fi-ta .fi-ta-header-toolbar > div:last-child,
    html.fieldtrack-admin .fi-ta-actions,
    html.fieldtrack-admin .fi-ta-toolbar-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-end;
        gap: 0.5rem;
        margin-left: auto;
    }

    html.fieldtrack-admin .fi-ta .fi-ta-search-field {
        width: 16rem;
        max-width: 100%;
        flex: 0 0 auto;
    }

    html.fieldtrack-admin .fi-ta .fi-ta-search-field .fi-input-wrp,
    html.fieldtrack-admin .fi-ta .fi-ta-search-field .fi-input-wrapper,
    html.fieldtrack-admin .fi-ta .fi-ta-search-field input {
        min-height: 2.25rem;
        border-radius: 0.6rem;
    }

    html.fieldtrack-admin .fi-ta .fi-ta-content {
        width: 100%;
        overflow-x: auto;
        background: #fff;
    }

    html.fieldtrack-admin .fi-ta .fi-ta-table {
        width: 100%;
        min-width: 100%;
        table-layout: auto;
        border-collapse: separate;
        border-spacing: 0;
    }

    html.fieldtrack-admin .fi-ta .fi-ta-header-cell,
    html.fieldtrack-admin .fi-ta thead th {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        text-transform: none;
        color: #475569;
        background: #f7f8fc !important;
        height: 2.55rem;
        padding: 0.5rem 0.8rem;
        white-space: nowrap;
        vertical-align: middle;
        border-bottom: 1px solid var(--ft-border);
    }

    html.fieldtrack-admin .fi-ta .fi-ta-cell,
    html.fieldtrack-admin .fi-ta tbody td {
        font-size: 0.8125rem;
        line-height: 1.35;
        min-height: 2.7rem;
        padding: 0.48rem 0.8rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        vertical-align: middle;
        color: #1e293b;
        border-bottom: 1px solid #eef1f6;
        background: #fff;
    }

    html.fieldtrack-admin .fi-ta .fi-ta-row:hover > .fi-ta-cell,
    html.fieldtrack-admin .fi-ta tbody tr:hover td {
        background: #fafafe;
    }

    html.fieldtrack-admin .fi-ta .fi-ta-actions-header-cell,
    html.fieldtrack-admin .fi-ta .fi-ta-cell:has(.fi-ta-actions) {
        width: 1%;
        white-space: nowrap;
        text-align: end;
    }

    html.fieldtrack-admin .fi-ta .fi-ta-cell .fi-ta-actions {
        display: inline-flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.3rem;
        width: max-content;
    }

    html.fieldtrack-admin .fi-ta .fi-ta-cell .fi-ta-actions .fi-btn,
    html.fieldtrack-admin .fi-ta .fi-ta-cell .fi-ta-actions .fi-ac-btn,
    html.fieldtrack-admin .fi-ta .fi-ta-cell .fi-ta-actions a,
    html.fieldtrack-admin .fi-ta .fi-ta-cell .fi-ta-actions button {
        white-space: nowrap;
        border-radius: 0.55rem;
        min-height: 2rem;
        padding-inline: 0.7rem;
        font-weight: 650;
    }

    @media (min-width: 768px) {
        html.fieldtrack-admin .fi-ta .fi-ta-table:not(.fi-ta-table-stacked-on-mobile) .fi-ta-actions-header-cell,
        html.fieldtrack-admin .fi-ta .fi-ta-table:not(.fi-ta-table-stacked-on-mobile) .fi-ta-cell:has(.fi-ta-actions) {
            position: sticky;
            right: 0;
            z-index: 2;
            background-color: #fff;
            box-shadow: -6px 0 8px -8px rgba(15, 23, 42, 0.16);
        }

        html.fieldtrack-admin .fi-ta .fi-ta-row:hover > .fi-ta-cell:has(.fi-ta-actions) {
            background-color: #fafafe;
        }
    }

    html.fieldtrack-admin .fi-ta .fi-pagination,
    html.fieldtrack-admin .fi-pagination {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.45rem 0.75rem;
        padding: 0.5rem 0.9rem;
        border-top: 1px solid var(--ft-border);
        background: #fff;
    }

    html.fieldtrack-admin .fi-pagination-overview,
    html.fieldtrack-admin .fi-ta .fi-pagination-overview {
        font-size: 0.75rem;
        color: var(--ft-muted);
    }

    html.fieldtrack-admin .fi-ta .fi-ta-table-stacked-on-mobile .fi-ta-header-cell,
    html.fieldtrack-admin .fi-ta .fi-ta-table-stacked-on-mobile .fi-ta-cell {
        width: auto;
        white-space: normal;
    }

    /* -------------------------------------------------------------------------
     * Badges
     * ------------------------------------------------------------------------- */
    html.fieldtrack-admin .fi-badge {
        border-radius: 999px !important;
        font-weight: 700 !important;
        font-size: 0.72rem !important;
        padding: 0.18rem 0.62rem !important;
        letter-spacing: 0.01em;
    }

    html.fieldtrack-admin .fi-badge.fi-color-warning,
    html.fieldtrack-admin .fi-color-warning.fi-badge {
        background: var(--ft-amber-bg) !important;
        color: var(--ft-amber-fg) !important;
    }

    html.fieldtrack-admin .fi-badge.fi-color-info,
    html.fieldtrack-admin .fi-color-info.fi-badge {
        background: var(--ft-blue-bg) !important;
        color: var(--ft-blue-fg) !important;
    }

    html.fieldtrack-admin .fi-badge.fi-color-success,
    html.fieldtrack-admin .fi-color-success.fi-badge {
        background: var(--ft-green-bg) !important;
        color: var(--ft-green-fg) !important;
    }

    html.fieldtrack-admin .fi-badge.fi-color-orange,
    html.fieldtrack-admin .fi-color-orange.fi-badge {
        background: var(--ft-orange-bg) !important;
        color: var(--ft-orange-fg) !important;
    }

    html.fieldtrack-admin .fi-badge.fi-color-danger,
    html.fieldtrack-admin .fi-color-danger.fi-badge {
        background: var(--ft-red-bg) !important;
        color: var(--ft-red-fg) !important;
    }

    /* -------------------------------------------------------------------------
     * Buttons
     * ------------------------------------------------------------------------- */
    html.fieldtrack-admin .fi-btn,
    html.fieldtrack-admin .fi-ac-btn,
    html.fieldtrack-admin button.fi-btn {
        border-radius: 0.6rem !important;
        min-height: 2.25rem;
        font-weight: 700;
        box-shadow: none;
    }

    html.fieldtrack-admin .fi-btn.fi-color-primary,
    html.fieldtrack-admin .fi-ac-btn.fi-color-primary,
    html.fieldtrack-admin .fi-btn-color-primary {
        background: linear-gradient(90deg, var(--ft-primary) 0%, var(--ft-primary-2) 100%) !important;
        color: #fff !important;
        border-color: transparent !important;
    }

    html.fieldtrack-admin .fi-btn.fi-color-success,
    html.fieldtrack-admin .fi-ac-btn.fi-color-success {
        background: #16a34a !important;
        color: #fff !important;
        border-color: transparent !important;
    }

    html.fieldtrack-admin .fi-btn.fi-color-orange,
    html.fieldtrack-admin .fi-ac-btn.fi-color-orange {
        background: #ea580c !important;
        color: #fff !important;
        border-color: transparent !important;
    }

    html.fieldtrack-admin .fi-btn.fi-color-danger,
    html.fieldtrack-admin .fi-ac-btn.fi-color-danger,
    html.fieldtrack-admin .fi-btn-color-danger {
        background: #dc2626 !important;
        color: #fff !important;
        border-color: transparent !important;
    }

    html.fieldtrack-admin .fi-btn.fi-color-warning,
    html.fieldtrack-admin .fi-ac-btn.fi-color-warning {
        background: #f59e0b !important;
        color: #111827 !important;
        border-color: transparent !important;
    }

    html.fieldtrack-admin .fi-btn.fi-color-gray,
    html.fieldtrack-admin .fi-ac-btn.fi-color-gray,
    html.fieldtrack-admin .fi-btn-color-gray {
        background: #fff !important;
        color: #334155 !important;
        border: 1px solid #dbe1ea !important;
    }

    /* -------------------------------------------------------------------------
     * Forms / modals
     * ------------------------------------------------------------------------- */
    html.fieldtrack-admin .fi-input,
    html.fieldtrack-admin .fi-input-wrp,
    html.fieldtrack-admin .fi-select-wrp,
    html.fieldtrack-admin .fi-fo-text-input,
    html.fieldtrack-admin .fi-fo-select,
    html.fieldtrack-admin textarea.fi-input,
    html.fieldtrack-admin .fi-fo-textarea textarea {
        border-radius: 0.6rem !important;
    }

    html.fieldtrack-admin .fi-modal-window,
    html.fieldtrack-admin .fi-modal-content,
    html.fieldtrack-admin .fi-modal {
        border-radius: 0.9rem;
    }

    html.fieldtrack-admin .fi-fo-field-wrp-label,
    html.fieldtrack-admin .fi-fo-field-label,
    html.fieldtrack-admin .fi-sc-component-label {
        font-weight: 650;
        color: #334155;
        font-size: 0.8rem;
    }

    /* -------------------------------------------------------------------------
     * Login / simple layout
     * ------------------------------------------------------------------------- */
    html.fieldtrack-admin .fi-simple-layout,
    html.fieldtrack-admin .fi-simple-main,
    html.fieldtrack-admin .fi-simple-page {
        background: var(--ft-bg) !important;
    }

    html.fieldtrack-admin .fi-simple-card,
    html.fieldtrack-admin .fi-simple-page-content,
    html.fieldtrack-admin .fi-simple-main .fi-sc-section,
    html.fieldtrack-admin .fi-simple-main .fi-section {
        background: #fff !important;
        border: 1px solid var(--ft-border);
        border-radius: 1rem !important;
        box-shadow: var(--ft-shadow);
    }

    html.fieldtrack-admin .fi-simple-header .fi-logo,
    html.fieldtrack-admin .fi-simple-layout .fi-logo {
        color: var(--ft-text);
        font-weight: 800;
    }

    /* -------------------------------------------------------------------------
     * Responsive
     * ------------------------------------------------------------------------- */
    html.fieldtrack-admin body,
    html.fieldtrack-admin .fi-layout {
        overflow-x: hidden;
    }

    @media (max-width: 767px) {
        html.fieldtrack-admin .fi-main,
        html.fieldtrack-admin .fi-main.fi-width-full {
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }

        html.fieldtrack-admin .fi-header-heading,
        html.fieldtrack-admin .fi-header h1 {
            font-size: 1.35rem;
        }

        html.fieldtrack-admin .fi-wi-stats-overview,
        html.fieldtrack-admin .fi-header-widgets,
        html.fieldtrack-admin .fi-page-content {
            display: flex;
            flex-direction: column;
        }

        html.fieldtrack-admin .fi-wi-stats-overview-stat {
            width: 100%;
        }
    }

    /* -------------------------------------------------------------------------
     * Admission view — documents table, review action grid, compact infolist
     * (kept from the previous admin theme so the view page still works)
     * ------------------------------------------------------------------------- */
    .fi-resource-admissions.fi-resource-view-record .fi-page-content,
    .admission-view-infolist {
        gap: 0.75rem;
        row-gap: 0.75rem;
        width: 100%;
        max-width: 100%;
    }

    .admission-view-row {
        width: 100%;
        align-items: start;
        gap: 0.75rem;
    }

    .admission-view-row > * {
        min-width: 0;
        width: 100%;
    }

    .admission-view-infolist .fi-section,
    .admission-view-infolist .fi-sc-section,
    .admission-view-card {
        height: auto;
        min-height: 0;
        align-self: start;
        width: 100%;
    }

    .admission-view-infolist .fi-section-content,
    .admission-view-infolist .fi-sc-section-content {
        padding: 0.75rem 1rem;
    }

    .admission-review-actions,
    .admission-review-actions .fi-ac,
    .admission-review-actions .fi-sc-actions {
        display: grid !important;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.5rem;
        width: 100%;
    }

    .admission-review-actions .fi-btn,
    .admission-review-btn {
        width: 100%;
        justify-content: center;
    }

    .admission-view-documents .fi-section-content,
    .admission-view-documents .fi-sc-section-content {
        padding: 0.5rem 0.75rem 0.75rem;
        overflow-x: auto;
    }

    .admission-docs-empty {
        margin: 0;
        padding: 0.5rem 0.25rem;
        color: rgb(100 116 139);
        font-size: 0.875rem;
    }

    .admission-docs-table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    .admission-docs-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: auto;
        font-size: 0.8125rem;
    }

    .admission-docs-table th,
    .admission-docs-table td {
        padding: 0.5rem 0.65rem;
        text-align: left;
        vertical-align: middle;
        border-bottom: 1px solid rgb(226 232 240);
        white-space: nowrap;
    }

    .admission-docs-table th {
        font-weight: 700;
        color: rgb(71 85 105);
        background: rgb(248 250 252);
    }

    .admission-docs-num {
        width: 2.25rem;
        text-align: center !important;
        color: rgb(100 116 139);
    }

    .admission-docs-name {
        white-space: normal;
        overflow-wrap: anywhere;
        word-break: break-word;
        max-width: 22rem;
    }

    .admission-docs-actions {
        white-space: nowrap;
        width: 1%;
    }

    .admission-docs-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 0.35rem;
        padding: 0.25rem 0.6rem;
        border-radius: 0.4rem;
        border: 1px solid rgb(203 213 225);
        background: #fff;
        color: rgb(30 41 59);
        font-size: 0.75rem;
        font-weight: 700;
        line-height: 1.2;
        cursor: pointer;
    }

    .admission-docs-btn-primary {
        border-color: rgb(37 99 235);
        background: rgb(37 99 235);
        color: #fff;
        margin-right: 0;
    }

    @media (max-width: 640px) {
        .admission-review-actions,
        .admission-review-actions .fi-ac,
        .admission-review-actions .fi-sc-actions {
            grid-template-columns: 1fr;
        }
    }

    .ft-cm-dash { display: flex; flex-direction: column; gap: 0.85rem; }
    .ft-cm-head, .ft-cm-filters, .ft-cm-panel-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem; }
    .ft-cm-kicker { margin: 0; font-size: 0.68rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: #6d28d9; }
    .ft-cm-name { margin: 0.1rem 0 0; font-size: 1.15rem; font-weight: 800; color: #0f172a; }
    .ft-cm-meta, .ft-cm-date { margin: 0.15rem 0 0; font-size: 0.78rem; color: #64748b; }
    .ft-cm-filters { flex-wrap: wrap; align-items: flex-end; }
    .ft-cm-chip, .ft-cm-shortcut-link {
        border: 1px solid #ddd6fe;
        background: #fff;
        color: #5b21b6;
        border-radius: 999px;
        padding: 0.32rem 0.7rem;
        font-size: 0.75rem;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
    }
    .ft-cm-chip.is-active, .ft-cm-shortcut-link { background: #5b21b6; color: #fff; border-color: #5b21b6; }
    .ft-cm-center { display: flex; flex-direction: column; gap: 0.15rem; font-size: 0.68rem; font-weight: 700; color: #64748b; }
    .ft-cm-center select, .ft-cm-center input { border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.3rem 0.45rem; font-size: 0.78rem; color: #0f172a; background: #fff; }
    .ft-cm-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.7rem; }
    .ft-cm-dash .ft-dash-card { min-height: 4.55rem; padding: 0.65rem 0.7rem; }
    .ft-cm-dash .ft-dash-card-value { font-size: 1.12rem; }
    .ft-cm-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.75rem; }
    .ft-cm-panel { border: 1px solid #e8ecf4; border-radius: 14px; background: #fff; padding: 0.75rem 0.85rem; min-width: 0; }
    .ft-cm-panel h3 { margin: 0; font-size: 0.86rem; font-weight: 800; color: #0f172a; }
    .ft-cm-panel-head span, .ft-cm-panel-head a { font-size: 0.72rem; font-weight: 700; color: #6d28d9; text-decoration: none; }
    .ft-cm-chart { display: flex; flex-direction: column; gap: 0.45rem; margin-top: 0.65rem; }
    .ft-cm-bar { display: grid; grid-template-columns: 4.2rem 1fr 2.6rem; grid-template-rows: auto auto; column-gap: 0.4rem; row-gap: 0.18rem; align-items: center; }
    .ft-cm-bar-label, .ft-cm-bar-n { font-size: 0.68rem; font-weight: 700; color: #475569; }
    .ft-cm-bar-n { grid-column: 3; grid-row: 1 / span 2; text-align: right; }
    .ft-cm-track { grid-column: 2; height: 0.38rem; border-radius: 999px; background: #f1f5f9; overflow: hidden; }
    .ft-cm-fill { display: block; height: 100%; border-radius: inherit; }
    .ft-cm-fill.is-target { background: #c4b5fd; }
    .ft-cm-fill.is-achieved { background: #6d28d9; }
    .ft-cm-legend { display: flex; align-items: center; gap: 0.35rem; margin: 0.55rem 0 0; font-size: 0.68rem; color: #64748b; }
    .ft-cm-legend span { width: 0.7rem; height: 0.35rem; border-radius: 999px; display: inline-block; }
    .ft-cm-legend .is-target { background: #c4b5fd; }
    .ft-cm-legend .is-achieved { background: #6d28d9; }
    .ft-cm-table { width: 100%; border-collapse: collapse; font-size: 0.75rem; }
    .ft-cm-table th, .ft-cm-table td { text-align: left; padding: 0.35rem 0.25rem; border-bottom: 1px solid #f1f5f9; color: #0f172a; }
    .ft-cm-table th { font-size: 0.68rem; color: #64748b; }
    .ft-cm-row { display: flex; flex-wrap: wrap; gap: 0.25rem 0.6rem; align-items: baseline; padding: 0.4rem 0; border-bottom: 1px solid #f1f5f9; font-size: 0.75rem; }
    .ft-cm-row strong { color: #0f172a; }
    .ft-cm-row span, .ft-cm-row em { color: #64748b; font-style: normal; }
    .ft-cm-empty { margin: 0.45rem 0 0; font-size: 0.75rem; color: #64748b; }
    .ft-cm-shortcut { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; }
    .ft-cm-shortcut p { margin: 0.2rem 0 0; font-size: 0.75rem; color: #64748b; }
    @media (max-width: 1199px) {
        .ft-cm-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .ft-cm-grid { grid-template-columns: 1fr; }
    }
</style>
