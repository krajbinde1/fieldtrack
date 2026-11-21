<style>
    /*
     * Login page only. Scoped to the auth body class so the rest of the
     * admin panel keeps its own layout.
     */
    html.fieldtrack-admin:has(body.ft-auth-login) {
        background: #fff !important;
    }

    html.fieldtrack-admin body.ft-auth-login {
        --ft-login-navy: #0b1b3a;
        --ft-login-navy-2: #15224a;
        --ft-login-purple: #3b1d6d;
        --ft-login-purple-2: #5b3aa8;
        --ft-login-accent: #6d5ef6;
        --ft-login-lavender: #ddd6fe;
        --ft-login-text: #0f172a;
        --ft-login-muted: #64748b;
        --ft-login-line: #e2e8f0;
        background: #fff !important;
        color: var(--ft-login-text);
        overflow-x: hidden;
    }

    html.fieldtrack-admin body.ft-auth-login .fi-simple-layout {
        display: grid !important;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        align-items: stretch;
        justify-items: stretch;
        width: 100%;
        max-width: 100%;
        min-height: 100vh;
        min-height: 100dvh;
        margin: 0;
        background: #fff !important;
        overflow-x: hidden;
    }

    html.fieldtrack-admin body.ft-auth-login .ft-login-brand {
        position: relative;
        isolation: isolate;
        min-width: 0;
        min-height: 100%;
        color: #fff;
        background-color: var(--ft-login-navy);
        background-image:
            linear-gradient(165deg, rgba(11, 27, 58, 0.55) 0%, rgba(21, 34, 74, 0.42) 46%, rgba(59, 29, 109, 0.62) 100%),
            url('{{ asset('images/fieldtrack-login-backdrop.svg') }}');
        background-size: cover;
        background-position: center;
        overflow: hidden;
    }

    html.fieldtrack-admin body.ft-auth-login .ft-login-brand__inner {
        position: relative;
        z-index: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 2.5rem;
        box-sizing: border-box;
        width: 100%;
        min-height: 100vh;
        min-height: 100dvh;
        padding: clamp(1.75rem, 4vw, 3.75rem);
    }

    html.fieldtrack-admin body.ft-auth-login .ft-login-brand__logo {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        min-width: 0;
    }

    html.fieldtrack-admin body.ft-auth-login .ft-login-brand__badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        width: 2.75rem;
        height: 2.75rem;
        border-radius: 0.85rem;
        color: var(--ft-login-navy);
        background: var(--ft-login-lavender);
        box-shadow: 0 10px 24px rgba(11, 27, 58, 0.28);
    }

    html.fieldtrack-admin body.ft-auth-login .ft-login-brand__word {
        font-family: var(--font-family), "Segoe UI", sans-serif;
        font-size: 1.15rem;
        font-weight: 700;
        letter-spacing: -0.03em;
        line-height: 1.2;
        color: #fff;
    }

    html.fieldtrack-admin body.ft-auth-login .ft-login-brand__copy {
        max-width: 34rem;
        margin-block: auto;
    }

    html.fieldtrack-admin body.ft-auth-login .ft-login-brand__title {
        margin: 0;
        font-family: var(--font-family), "Segoe UI", sans-serif;
        font-size: clamp(2rem, 3vw, 2.85rem);
        font-weight: 700;
        letter-spacing: -0.035em;
        line-height: 1.12;
        color: #fff;
    }

    html.fieldtrack-admin body.ft-auth-login .ft-login-brand__subtitle {
        margin: 1.15rem 0 0;
        max-width: 28rem;
        font-size: 1.05rem;
        font-weight: 400;
        line-height: 1.6;
        color: rgba(255, 255, 255, 0.78);
    }

    html.fieldtrack-admin body.ft-auth-login .ft-login-brand__footer {
        margin: 0;
        font-size: 0.82rem;
        font-weight: 500;
        letter-spacing: 0.01em;
        color: rgba(221, 214, 254, 0.82);
    }

    html.fieldtrack-admin body.ft-auth-login .fi-simple-main-ctn {
        display: flex !important;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
        width: 100%;
        min-width: 0;
        min-height: 100%;
        padding: clamp(1.75rem, 4vw, 3.5rem);
        background: #fff !important;
    }

    html.fieldtrack-admin body.ft-auth-login .fi-simple-main,
    html.fieldtrack-admin body.ft-auth-login .fi-simple-page,
    html.fieldtrack-admin body.ft-auth-login .fi-simple-page-content,
    html.fieldtrack-admin body.ft-auth-login .fi-simple-header,
    html.fieldtrack-admin body.ft-auth-login .fi-simple-main .fi-section,
    html.fieldtrack-admin body.ft-auth-login .fi-simple-main .fi-sc-section {
        width: 100%;
        max-width: min(26.5rem, 100%);
        min-width: 0;
        margin: 0 auto !important;
        box-sizing: border-box;
        background: transparent !important;
        border: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        --tw-shadow: 0 0 #0000 !important;
        --tw-ring-shadow: 0 0 #0000 !important;
        --tw-ring-offset-shadow: 0 0 #0000 !important;
    }

    html.fieldtrack-admin body.ft-auth-login .fi-simple-main {
        padding: 0 !important;
    }

    html.fieldtrack-admin body.ft-auth-login .fi-simple-page-content {
        row-gap: 1.75rem;
    }

    html.fieldtrack-admin body.ft-auth-login .fi-simple-header {
        align-items: flex-start;
        gap: 0.45rem;
    }

    html.fieldtrack-admin body.ft-auth-login .fi-simple-header .fi-logo {
        display: none;
    }

    html.fieldtrack-admin body.ft-auth-login .fi-simple-header-heading {
        margin: 0;
        text-align: left;
        font-family: var(--font-family), "Segoe UI", sans-serif;
        font-size: 2rem;
        font-weight: 700;
        letter-spacing: -0.035em;
        line-height: 1.15;
        color: var(--ft-login-text);
    }

    html.fieldtrack-admin body.ft-auth-login .fi-simple-header-subheading {
        margin: 0;
        text-align: left;
        font-size: 0.98rem;
        line-height: 1.55;
        color: var(--ft-login-muted);
    }

    html.fieldtrack-admin body.ft-auth-login .fi-sc-form {
        display: flex;
        flex-direction: column;
        gap: 1.15rem;
    }

    html.fieldtrack-admin body.ft-auth-login .fi-fo-field-label,
    html.fieldtrack-admin body.ft-auth-login .fi-fo-field-label-content {
        font-size: 0.84rem;
        font-weight: 600;
        letter-spacing: 0.01em;
        color: #334155;
    }

    html.fieldtrack-admin body.ft-auth-login .fi-simple-layout > *,
    html.fieldtrack-admin body.ft-auth-login .fi-sc,
    html.fieldtrack-admin body.ft-auth-login .fi-grid-col,
    html.fieldtrack-admin body.ft-auth-login .fi-sc-component,
    html.fieldtrack-admin body.ft-auth-login .fi-fo-field,
    html.fieldtrack-admin body.ft-auth-login .fi-fo-field-content-col,
    html.fieldtrack-admin body.ft-auth-login .fi-sc-form {
        min-width: 0;
        max-width: 100%;
    }

    html.fieldtrack-admin body.ft-auth-login .fi-input-wrp {
        display: flex !important;
        align-items: stretch;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
        border: 1px solid var(--ft-login-line) !important;
        border-radius: 0.8rem !important;
        background: #fff !important;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04) !important;
        min-height: 3.05rem;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    html.fieldtrack-admin body.ft-auth-login .fi-input-wrp:focus-within {
        border-color: var(--ft-login-accent) !important;
        box-shadow: 0 0 0 4px rgba(109, 94, 246, 0.16) !important;
    }

    html.fieldtrack-admin body.ft-auth-login .fi-input-wrp-content-ctn {
        flex: 1 1 0% !important;
        width: 0 !important;
        min-width: 0 !important;
        max-width: 100%;
    }

    html.fieldtrack-admin body.ft-auth-login .fi-input-wrp-suffix,
    html.fieldtrack-admin body.ft-auth-login .fi-input-wrp-actions {
        flex: 0 0 auto;
    }

    html.fieldtrack-admin body.ft-auth-login .fi-input {
        border: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
        width: 100%;
        min-width: 0;
        max-width: 100%;
        min-height: 3rem;
        padding-inline: 0.95rem;
        font-size: 0.98rem;
        color: var(--ft-login-text);
    }

    html.fieldtrack-admin body.ft-auth-login .fi-input::placeholder {
        color: #94a3b8;
    }

    html.fieldtrack-admin body.ft-auth-login .fi-input-wrp .fi-icon-btn,
    html.fieldtrack-admin body.ft-auth-login .fi-input-wrp button {
        color: #64748b;
        background: transparent !important;
        box-shadow: none !important;
    }

    html.fieldtrack-admin body.ft-auth-login .fi-input-wrp .fi-icon-btn:hover,
    html.fieldtrack-admin body.ft-auth-login .fi-input-wrp button:hover {
        color: var(--ft-login-purple-2);
        background: rgba(109, 94, 246, 0.08) !important;
    }

    html.fieldtrack-admin body.ft-auth-login .fi-fo-field-label-content,
    html.fieldtrack-admin body.ft-auth-login .fi-checkbox-input + label,
    html.fieldtrack-admin body.ft-auth-login .fi-fo-checkbox label {
        color: #334155;
    }

    html.fieldtrack-admin body.ft-auth-login .fi-sc-actions {
        margin-top: 0.35rem;
    }

    html.fieldtrack-admin body.ft-auth-login .fi-sc-actions .fi-btn,
    html.fieldtrack-admin body.ft-auth-login .fi-sc-form .fi-btn[type="submit"] {
        width: 100%;
        justify-content: center;
        min-height: 3.05rem;
        border: 0 !important;
        border-radius: 0.8rem !important;
        background: var(--ft-login-purple-2) !important;
        color: #fff !important;
        font-size: 0.98rem;
        font-weight: 700;
        letter-spacing: 0.01em;
        box-shadow: 0 10px 22px rgba(91, 58, 168, 0.28) !important;
    }

    html.fieldtrack-admin body.ft-auth-login .fi-sc-actions .fi-btn:hover,
    html.fieldtrack-admin body.ft-auth-login .fi-sc-form .fi-btn[type="submit"]:hover {
        background: #4c2f96 !important;
    }

    html.fieldtrack-admin body.ft-auth-login .fi-sc-actions .fi-btn:focus-visible,
    html.fieldtrack-admin body.ft-auth-login .fi-sc-form .fi-btn[type="submit"]:focus-visible {
        outline: 3px solid rgba(221, 214, 254, 0.95);
        outline-offset: 2px;
    }

    @media (max-width: 1023px) {
        html.fieldtrack-admin body.ft-auth-login .fi-simple-layout {
            display: flex !important;
            flex-direction: column;
        }

        html.fieldtrack-admin body.ft-auth-login .ft-login-brand__inner {
            flex-direction: row;
            align-items: center;
            justify-content: flex-start;
            gap: 0.8rem;
            min-height: 0;
            padding: 0.9rem 1.1rem;
        }

        html.fieldtrack-admin body.ft-auth-login .ft-login-brand__copy,
        html.fieldtrack-admin body.ft-auth-login .ft-login-brand__footer {
            display: none;
        }

        html.fieldtrack-admin body.ft-auth-login .ft-login-brand__badge {
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 0.7rem;
        }

        html.fieldtrack-admin body.ft-auth-login .ft-login-brand__word {
            font-size: 1rem;
        }

        html.fieldtrack-admin body.ft-auth-login .fi-simple-layout,
        html.fieldtrack-admin body.ft-auth-login .fi-simple-main-ctn,
        html.fieldtrack-admin body.ft-auth-login .fi-simple-main,
        html.fieldtrack-admin body.ft-auth-login .fi-simple-page,
        html.fieldtrack-admin body.ft-auth-login .fi-simple-page-content,
        html.fieldtrack-admin body.ft-auth-login .fi-sc-form {
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            box-sizing: border-box !important;
        }

        html.fieldtrack-admin body.ft-auth-login .fi-simple-main-ctn {
            align-items: stretch;
            flex: 1 1 auto;
            padding: 1.6rem 1.15rem 2.25rem;
        }

        html.fieldtrack-admin body.ft-auth-login .fi-simple-header-heading {
            font-size: 1.7rem;
        }
    }
</style>
