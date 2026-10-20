<!--
Elementor Custom Code snippet (post_type elementor_snippet) exported read-only from staging
post_id: 14273
title: Kontact floating button.js
status: publish
location (_elementor_location): elementor_body_end
priority (_elementor_priority): 1
conditions (_elementor_conditions): ["include/general"]
conditions raw: a:1:{i:0;s:15:"include/general";}
extra options (_elementor_extra_options): []
code bytes (LENGTH(_elementor_code)): 11373
Everything below the END HEADER line is the _elementor_code meta value verbatim (post_content is empty for these posts).
-->
<!-- END HEADER -->
<script>
(function () {
  'use strict';

  const BRAND_PINK = '#e91e8c';
  const BRAND_GREEN = '#2e9e45';

  function createFloatingContactPanel() {
    if (document.getElementById('ip-floating-contact')) return;

    const wrapper = document.createElement('div');
    wrapper.id = 'ip-floating-contact';
    wrapper.innerHTML = `
      <div id="ip-fc-tab">
        <svg class="ip-fc-tab-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
        </svg>
        <span class="ip-fc-tab-label">Kontakt</span>
      </div>
      <div id="ip-fc-content">
        <div id="ip-fc-header">
          <h3>Persönlich für Sie da</h3>
          <p>Stellen Sie Ihre Fragen einfach und bequem per Mail oder Fax!</p>
        </div>
        <div id="ip-fc-items">
          <div class="ip-fc-item ip-fc-hours">
            <div class="ip-fc-icon">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
              </svg>
            </div>
            <div class="ip-fc-text">
              <strong>MO–DO</strong> 8:00–17:00<br>
              <strong>FR</strong> 8:00–16:00
            </div>
          </div>
          <a href="tel:+494069630030" class="ip-fc-item">
            <div class="ip-fc-icon ip-fc-icon-pink">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="${BRAND_PINK}" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
              </svg>
            </div>
            <div class="ip-fc-text">+49 (0)40 6963003-0</div>
          </a>
       
          <a href="mailto:info@infoplus.de" class="ip-fc-item">
            <div class="ip-fc-icon ip-fc-icon-pink">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="${BRAND_PINK}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="22 4 12 13 2 4"/>
              </svg>
            </div>
            <div class="ip-fc-text">info@infoplus.de</div>
          </a>
        </div>
      </div>
    `;

    const style = document.createElement('style');
    style.textContent = `
      /* ═══════════════════════════════════════════════════════════
         BASE / DESKTOP
         ═══════════════════════════════════════════════════════════ */
      #ip-floating-contact {
        position: fixed;
        top: 70%;
        right: 0;
        transform: translateY(-50%);
        display: flex;
        flex-direction: row;
        align-items: stretch;
        z-index: 999999;
        transition: right 0.45s cubic-bezier(0.25, 0.8, 0.25, 1);
      }
      #ip-floating-contact.ip-fc-closed {
        right: calc(-1 * var(--ip-fc-content-width, 280px));
      }

      /* ── Tab ────────────────────────────────────────────────── */
      #ip-fc-tab {
        display: flex;
        flex-direction: row;
        align-items: center;
        justify-content: center;
        gap: 8px;
        background: ${BRAND_PINK};
        color: #fff;
        padding: 18px 10px;
        border-radius: 16px 0 0 16px;
        cursor: pointer;
        writing-mode: vertical-lr;
        text-orientation: mixed;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        font-size: 14px;
        font-weight: 700;
        letter-spacing: 1px;
        text-transform: uppercase;
        user-select: none;
        transition: background 0.2s;
        flex-shrink: 0;
      }
      .ip-fc-tab-icon { width: 20px; height: 20px; flex-shrink: 0; }

      /* ── Content ────────────────────────────────────────────── */
      #ip-fc-content {
        width: var(--ip-fc-content-width, 280px);
        background: #fff;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        flex-shrink: 0;
      }

      #ip-fc-header {
        background: ${BRAND_PINK};
        color: #fff;
        padding: 20px 20px 16px;
      }
      #ip-fc-header h3 {
        margin: 0 0 6px;
        font-size: 17px;
        font-weight: 700;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        line-height: 1.3;
      }
      #ip-fc-header p {
        margin: 0;
        font-size: 12.5px;
        line-height: 1.4;
        opacity: 0.92;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      }

      #ip-fc-items {
        padding: 12px 14px 16px;
        display: flex;
        flex-direction: column;
        gap: 4px;
      }

      .ip-fc-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        text-decoration: none;
        color: #333;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        font-size: 13.5px;
        transition: background 0.15s;
      }
      a.ip-fc-item:hover { background: #f5f5f5; }

      .ip-fc-hours {
        background: ${BRAND_GREEN};
        color: #fff;
        border-radius: 12px;
        padding: 12px 14px;
        margin-bottom: 4px;
      }
      .ip-fc-hours .ip-fc-text { font-size: 13px; font-weight: 600; line-height: 1.5; }
      .ip-fc-hours .ip-fc-text strong { font-weight: 800; }

      .ip-fc-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        flex-shrink: 0;
      }
      .ip-fc-icon svg { width: 20px; height: 20px; }
      .ip-fc-icon-pink { background: rgba(233, 30, 140, 0.08); }
      .ip-fc-text { font-weight: 500; line-height: 1.3; }

      /* ═══════════════════════════════════════════════════════════
         MOBILE  (≤ 768px)
         ═══════════════════════════════════════════════════════════ */
      @media (max-width: 768px) {

        /* Smaller content panel */
        #ip-floating-contact {
          --ip-fc-content-width: 220px;
    				top: 60%;
        }

        /* Compact tab: icon only, no label */
        #ip-fc-tab {
          padding: 12px 8px;
          border-radius: 12px 0 0 12px;
          gap: 0;
          writing-mode: horizontal-tb;
        }
        .ip-fc-tab-icon { width: 18px; height: 18px; }
        .ip-fc-tab-label { display: none; }

        /* Compact header */
        #ip-fc-header {
          padding: 14px 14px 10px;
        }
        #ip-fc-header h3 { font-size: 14px; margin-bottom: 3px; }
        #ip-fc-header p { font-size: 11px; }

        /* Tighter items */
        #ip-fc-items {
          padding: 8px 10px 12px;
          gap: 2px;
        }
        .ip-fc-item {
          gap: 10px;
          padding: 8px 10px;
          font-size: 12px;
        }
        .ip-fc-hours {
          padding: 10px 12px;
          border-radius: 10px;
          margin-bottom: 2px;
        }
        .ip-fc-hours .ip-fc-text { font-size: 11.5px; }

        /* Smaller icons */
        .ip-fc-icon { width: 30px; height: 30px; }
        .ip-fc-icon svg { width: 16px; height: 16px; }
      }

      /* ═══════════════════════════════════════════════════════════
         SMALL MOBILE  (≤ 400px)
         ═══════════════════════════════════════════════════════════ */
      @media (max-width: 400px) {
        #ip-floating-contact {
          --ip-fc-content-width: 200px;
    				top: 65%;
        }
        #ip-fc-header h3 { font-size: 13px; }
        #ip-fc-header p { font-size: 10.5px; }
        .ip-fc-item { font-size: 11.5px; padding: 7px 8px; }
      }
    `;

    document.head.appendChild(style);
    document.body.appendChild(wrapper);
    wrapper.classList.add('ip-fc-closed');

    const contentEl = document.getElementById('ip-fc-content');
    const tabEl = document.getElementById('ip-fc-tab');

    requestAnimationFrame(() => {
      const contentWidth = contentEl.getBoundingClientRect().width;
      wrapper.style.setProperty('--ip-fc-content-width', contentWidth + 'px');
      console.group('[FloatingContact] Initialised');
      console.log('Tab:', tabEl.getBoundingClientRect().width.toFixed(0) + '×' + tabEl.getBoundingClientRect().height.toFixed(0) + 'px');
      console.log('Content:', contentWidth.toFixed(0) + '×' + contentEl.getBoundingClientRect().height.toFixed(0) + 'px');
      console.groupEnd();
    });

    // Recalculate on resize (orientation change, etc.)
    let resizeTimer;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(() => {
        const w = contentEl.getBoundingClientRect().width;
        wrapper.style.setProperty('--ip-fc-content-width', w + 'px');
      }, 150);
    });

    let isOpen = false;
    tabEl.addEventListener('click', () => {
      isOpen = !isOpen;
      wrapper.classList.toggle('ip-fc-closed', !isOpen);
      console.log('[FloatingContact] ' + (isOpen ? 'OPENED' : 'CLOSED'));
    });

    document.addEventListener('click', (e) => {
      if (isOpen && !wrapper.contains(e.target)) {
        isOpen = false;
        wrapper.classList.add('ip-fc-closed');
      }
    });

    console.log('[FloatingContact] ✅ Ready');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', createFloatingContactPanel);
  } else {
    createFloatingContactPanel();
  }
})();
</script>