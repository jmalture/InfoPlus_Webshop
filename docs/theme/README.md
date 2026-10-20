# Infoplus webshop theme

Classic PHP theme for the Infoplus webshop (WordPress + WooCommerce), replacing Elementor.
Plan and template-by-template map: "Infoplus rebuild map" (Claude doc).

Customer account, login and order confirmation: `ACCOUNT.md` (routing, the one-time settings change, feature inventory, findings).

## Layout

- `_legacy/`        read-only export of everything that lived in Elementor on staging (2026-10-20). Reference only, never deployed. See `_legacy/README.md`.
- `mu-plugins/`     `infoplus-theme-preview.php`: admin-only preview of the new theme. Copy to `wp-content/mu-plugins/`.
- theme files       follow in `infoplus/` (deployed to `wp-content/themes/infoplus/`).

## Deploy

Staging: files are uploaded through the EMCP Tools connection. Production: not decided yet.
