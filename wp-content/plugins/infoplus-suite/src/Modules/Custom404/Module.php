<?php
namespace Infoplus\Suite\Modules\Custom404;

use Infoplus\Suite\Contracts\ModuleInterface;

if (!defined('ABSPATH')) exit;

/**
 * Custom 404 Module
 *
 * The theme renders its own 404 markup directly (no call to the_content()),
 * so a content filter alone is not enough. Strategy:
 *
 *  1. wp_head  — inject CSS that hides the theme's default .page-header /
 *                .page-content elements on body.error404.
 *  2. wp_footer — inject our branded HTML into the footer, then use a tiny
 *                JS snippet to move it into <main> before the theme content,
 *                guaranteeing it appears in the right place regardless of
 *                how the theme structures its 404 template.
 */
final class Module implements ModuleInterface
{
    public function slug(): string  { return 'custom-404'; }
    public function label(): string { return __('Custom 404 Page', 'infoplus-suite'); }

    public function register(): void
    {
        add_action('wp', [$this, 'maybeRegister404Hooks']);

        // Cleaner document title
        add_filter('wp_title',              [$this, 'filter404Title'], 10, 2);
        add_filter('pre_get_document_title', [$this, 'filter404DocumentTitle']);
    }

    /** Only wire up the heavy hooks when we are actually on a 404. */
    public function maybeRegister404Hooks(): void
    {
        if (!is_404()) return;

        // Don't touch Elementor editor / preview
        if (
            isset($_GET['elementor-preview']) ||
            isset($_GET['elementor_library']) ||
            (isset($_GET['action']) && $_GET['action'] === 'elementor')
        ) {
            return;
        }

        add_action('wp_head',   [$this, 'injectHideStyles']);
        add_action('wp_footer', [$this, 'injectContent']);
    }

    // ── Titles ───────────────────────────────────────────────────────────────

    public function filter404Title(string $title, string $sep): string
    {
        return is_404() ? '404 – Seite nicht gefunden' : $title;
    }

    public function filter404DocumentTitle(string $title): string
    {
        return is_404() ? '404 – Seite nicht gefunden' : $title;
    }

    // ── Step 1: hide the theme's default 404 content via CSS ─────────────────

    public function injectHideStyles(): void
    {
        ?>
        <style id="ip404-hide">
        /* Hide the theme's default 404 elements; keep header/footer intact */
        body.error404 .page-header,
        body.error404 .page-content,
        body.error404 .entry-header,
        body.error404 .widget_search,
        body.error404 .search-form {
            display: none !important;
        }

        /* Give the main element breathing room for our UI */
        body.error404 #content.site-main,
        body.error404 main.site-main,
        body.error404 #main {
            padding: 0 !important;
            min-height: 0 !important;
        }
        </style>
        <?php
    }

    // ── Step 2: inject our branded HTML + move it into <main> via JS ─────────

    public function injectContent(): void
    {
        $logged_in    = is_user_logged_in();
        $home_url     = home_url('/');
        $products_url = home_url('/namensschilder/');
        $dashboard_url = home_url('/account/');
        $login_url    = home_url('/log-in/');
        ?>

        <div id="ip404-mount" style="display:none">
        <div class="ip404-wrap">

            <!-- Hero -->
            <div class="ip404-hero">
                <div class="ip404-hero-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                </div>
                <p class="ip404-hero-code">404</p>
                <h1 class="ip404-hero-title">Diese Seite existiert nicht</h1>
                <p class="ip404-hero-sub">
                    Die aufgerufene Adresse ist falsch oder wurde verschoben.<br>
                    Hier sind ein paar Anlaufstellen, die weiterhelfen:
                </p>
            </div>

            <!-- Crossroads card -->
            <div class="ip404-card">
                <p class="ip404-card-label">SO GEHT'S WEITER</p>
                <ul class="ip404-tips">

                    <li>
                        <span class="ip404-tip-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                <polyline points="9 22 9 12 15 12 15 22"/>
                            </svg>
                        </span>
                        <span>Zurück auf die <a href="<?php echo esc_url($home_url); ?>">Startseite</a> – der sicherste Ausgangspunkt.</span>
                    </li>

                    <li>
                        <span class="ip404-tip-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"/>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            </svg>
                        </span>
                        <span>Stöbern Sie in unseren <a href="<?php echo esc_url($products_url); ?>">Namensschildern</a> und wählen Sie das Passende aus.</span>
                    </li>

                    <?php if ($logged_in): ?>
                    <li>
                        <span class="ip404-tip-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                                <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                            </svg>
                        </span>
                        <span>Besuchen Sie Ihr <a href="<?php echo esc_url($dashboard_url); ?>">Dashboard</a>, um Bestellungen und Einstellungen zu verwalten.</span>
                    </li>
                    <?php else: ?>
                    <li>
                        <span class="ip404-tip-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                        </span>
                        <span>Haben Sie bereits ein Konto? <a href="<?php echo esc_url($login_url); ?>">Jetzt anmelden</a> und Ihre Bestellungen verwalten.</span>
                    </li>
                    <li>
                        <span class="ip404-tip-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="8.5" cy="7" r="4"/>
                                <line x1="20" y1="8" x2="20" y2="14"/>
                                <line x1="23" y1="11" x2="17" y2="11"/>
                            </svg>
                        </span>
                        <span>Noch kein Konto? <a href="<?php echo esc_url($login_url); ?>?eael-register=1">Kostenlos registrieren</a> und von allen Vorteilen profitieren.</span>
                    </li>
                    <?php endif; ?>

                </ul>
            </div>

            <!-- Action buttons -->
            <div class="ip404-actions">
                <a href="<?php echo esc_url($home_url); ?>" class="ip404-btn ip404-btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                    Zur Startseite
                </a>
                <a href="<?php echo esc_url($products_url); ?>" class="ip404-btn ip404-btn-secondary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    Namensschilder
                </a>
                <?php if ($logged_in): ?>
                <a href="<?php echo esc_url($dashboard_url); ?>" class="ip404-btn ip404-btn-secondary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                        <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                    </svg>
                    Dashboard
                </a>
                <?php else: ?>
                <a href="<?php echo esc_url($login_url); ?>" class="ip404-btn ip404-btn-secondary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                        <polyline points="10 17 15 12 10 7"/>
                        <line x1="15" y1="12" x2="3" y2="12"/>
                    </svg>
                    Anmelden
                </a>
                <?php endif; ?>
            </div>

        </div>
        </div><!-- #ip404-mount -->

        <style id="ip404-styles">
        .ip404-wrap {
            /* min-height is set precisely by JS below.
               Fallback uses the known header (83px) + footer (601px) heights. */
            min-height: calc(100vh - 684px);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 28px;
            padding: 32px 16px;
            max-width: 700px;
            margin: 0 auto;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            box-sizing: border-box;
        }

        /* Hero */
        .ip404-hero {
            width: 100%;
            text-align: center;
            padding: 48px 32px 40px;
            background: var(--e-global-color-primary, #e91e8c);
            border-radius: 20px;
            color: #fff;
        }
        .ip404-hero-icon {
            width: 80px; height: 80px;
            background: rgba(255,255,255,.2);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
        }
        .ip404-hero-icon svg { width: 40px; height: 40px; color: #fff; }
        .ip404-hero-code {
            font-size: 5rem; font-weight: 800; line-height: 1;
            letter-spacing: -2px; margin: 0 0 8px; opacity: .3;
        }
        .ip404-hero-title {
            font-size: 1.75rem; font-weight: 700;
            margin: 0 0 12px; color: #fff;
        }
        .ip404-hero-sub { font-size: 1rem; line-height: 1.6; margin: 0; opacity: .9; }

        /* Card */
        .ip404-card {
            width: 100%;
            background: #fff;
            border-radius: 16px;
            padding: 28px 32px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
        }
        .ip404-card-label {
            font-size: .75rem; font-weight: 700; letter-spacing: .08em;
            color: #aaa; text-transform: uppercase; margin: 0 0 16px;
        }
        .ip404-tips { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0; }
        .ip404-tips li {
            display: flex; align-items: flex-start; gap: 14px;
            padding: 14px 0; border-top: 1px solid #f0f0f0;
            font-size: .95rem; color: #444; line-height: 1.5;
        }
        .ip404-tips li:first-child { border-top: none; padding-top: 0; }
        .ip404-tip-icon {
            flex-shrink: 0; width: 36px; height: 36px;
            background: rgba(233,30,140,.08); border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
        }
        .ip404-tip-icon svg { width: 18px; height: 18px; stroke: #e91e8c; }
        .ip404-tips a { color: #e91e8c; font-weight: 600; text-decoration: none; }
        .ip404-tips a:hover { text-decoration: underline; }

        /* Buttons */
        .ip404-actions { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; width: 100%; }
        .ip404-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 13px 24px; border-radius: 10px;
            font-size: .95rem; font-weight: 600; text-decoration: none;
            transition: transform .15s, box-shadow .15s, opacity .15s;
            cursor: pointer; border: none;
        }
        .ip404-btn svg { width: 18px; height: 18px; flex-shrink: 0; }
        .ip404-btn:hover { transform: translateY(-2px); }
        .ip404-btn-primary {
            background: #e91e8c; color: #fff !important;
            box-shadow: 0 4px 14px rgba(233,30,140,.3);
        }
        .ip404-btn-primary:hover { box-shadow: 0 6px 20px rgba(233,30,140,.4); opacity: .93; }
        .ip404-btn-secondary {
            background: #fff; color: #e91e8c !important;
            border: 1.5px solid #e91e8c;
        }
        .ip404-btn-secondary:hover { background: rgba(233,30,140,.04); }

        /* Responsive */
        @media (max-width: 600px) {
            .ip404-hero { padding: 36px 20px 28px; }
            .ip404-hero-code { font-size: 3.5rem; }
            .ip404-hero-title { font-size: 1.35rem; }
            .ip404-card { padding: 20px; }
            .ip404-btn { width: 100%; justify-content: center; }
        }
        </style>

        <script>
        (function () {
            var mount = document.getElementById('ip404-mount');
            if (!mount) return;

            // ── 1. Move our UI into the theme's <main> ───────────────────────
            var mainSelectors = [
                'main#content.site-main',
                'main.site-main',
                '#main',
                '#content',
                '.site-main',
                'main'
            ];
            var container = null;
            for (var i = 0; i < mainSelectors.length; i++) {
                container = document.querySelector(mainSelectors[i]);
                if (container) break;
            }
            if (container) {
                container.insertBefore(mount, container.firstChild);
            }
            mount.style.display = 'block';

            // ── 2. Fit the wrap to exactly the remaining viewport height ──────
            //
            // We measure the real header and footer each time (and on resize) so
            // the layout stays exact even if the header collapses on mobile.
            // If content is naturally taller than the available space we honour
            // that — min-height, not height — so nothing gets clipped.

            var headerSelectors = ['header#masthead', 'header.site-header', '#masthead', '.site-header', 'header'];
            var footerSelectors = ['footer#colophon', 'footer.site-footer', '#colophon', '.site-footer', 'footer'];

            function measureEl(selList) {
                for (var j = 0; j < selList.length; j++) {
                    var el = document.querySelector(selList[j]);
                    if (el) return el.getBoundingClientRect().height;
                }
                return 0;
            }

            function applyHeight() {
                var headerH = measureEl(headerSelectors);
                var footerH = measureEl(footerSelectors);

                // Known fallbacks from design specs (83.39 + 601.02)
                if (headerH === 0) headerH = 83;
                if (footerH === 0) footerH = 601;

                var available = window.innerHeight - headerH - footerH;

                var wrap = document.querySelector('.ip404-wrap');
                if (!wrap) return;

                // Only apply if we get a sensible value; otherwise CSS fallback wins.
                if (available > 200) {
                    wrap.style.minHeight = available + 'px';
                }
            }

            // Run once the layout is fully painted, then again on resize.
            if (document.readyState === 'complete') {
                applyHeight();
            } else {
                window.addEventListener('load', applyHeight);
            }
            window.addEventListener('resize', applyHeight);
        })();
        </script>
        <?php
    }
}
