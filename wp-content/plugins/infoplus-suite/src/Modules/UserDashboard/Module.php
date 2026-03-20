<?php
namespace Infoplus\Suite\Modules\UserDashboard;

use Infoplus\Suite\Contracts\ModuleInterface;
use Infoplus\Suite\Plugin;

if (!defined('ABSPATH')) exit;

final class Module implements ModuleInterface
{
    public function slug(): string  { return 'user-dashboard'; }
    public function label(): string { return __('User Dashboard', 'infoplus-suite'); }

    public function register(): void
    {
        // Enqueue frontend scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontend']);
        
        // Register REST API endpoints
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        
        // Handle login redirects (force redirect to custom account page)
        add_filter('login_redirect', [$this, 'customLoginRedirect'], 999, 3);
        add_filter('woocommerce_login_redirect', [$this, 'woocommerceLoginRedirect'], 999, 2);
        
        // Redirect logged-in users away from login page
        add_action('template_redirect', [$this, 'redirectLoggedInUsers']);

        // Redirect guests away from the account/dashboard page
        add_action('template_redirect', [$this, 'redirectGuestUsers']);
        
        // Register shortcode for login guard
        add_shortcode('infoplus_login_guard', [$this, 'renderLoginGuard']);

        // Register shortcode for full profile settings form
        add_shortcode('infoplus_profile_settings', [$this, 'renderProfileSettings']);
    }

    private function assetUrl(string $path): string
    {
        return INFOPLUS_SUITE_URL . 'src/Modules/UserDashboard/Assets/' . ltrim($path, '/');
    }

    public function enqueueFrontend(): void
    {
        $ver = Plugin::VERSION;

        // Always load menu dropdown styles and script
        wp_enqueue_style(
            'infoplus-menu-dropdown',
            $this->assetUrl('menu-dropdown.css'),
            [],
            $ver
        );
        
        wp_enqueue_script(
            'infoplus-menu-dropdown',
            $this->assetUrl('menu-dropdown.js'),
            ['jquery'],
            $ver,
            true
        );

        // Pass URLs and login status to JavaScript
        wp_localize_script('infoplus-menu-dropdown', 'infoplusMenuDropdown', [
            'isLoggedIn' => is_user_logged_in(),
            'loginUrl' => home_url('/log-in'),
            'registerUrl' => home_url('/log-in/?eael-register=1'), 
            'accountUrl' => home_url('/account'),
            'logoutUrl' => wp_logout_url(home_url()),
        ]);

        // Only load dashboard scripts if user is logged in
        if (!is_user_logged_in()) {
            return;
        }

        wp_enqueue_script(
            'infoplus-user-dashboard',
            $this->assetUrl('user-dashboard.js'),
            ['jquery', 'wp-api'],
            $ver,
            true
        );

        // Pass data to JavaScript
        wp_localize_script('infoplus-user-dashboard', 'infoplusUserDashboard', [
            'restUrl' => rest_url('infoplus/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'currentUserId' => get_current_user_id(),
        ]);
    }

    public function registerRestRoutes(): void
    {
        // Get current user data
        register_rest_route('infoplus/v1', '/user/profile', [
            'methods' => 'GET',
            'callback' => [$this, 'getUserProfile'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);

        // Update profile information
        register_rest_route('infoplus/v1', '/user/profile', [
            'methods' => 'POST',
            'callback' => [$this, 'updateUserProfile'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);

        // Change password
        register_rest_route('infoplus/v1', '/user/password', [
            'methods' => 'POST',
            'callback' => [$this, 'changePassword'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);

        // Upload avatar
        register_rest_route('infoplus/v1', '/user/avatar', [
            'methods' => 'POST',
            'callback' => [$this, 'uploadAvatar'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);
    }

    /**
     * Get current user profile data
     */
    public function getUserProfile(\WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);

        if (!$user) {
            return new \WP_Error('user_not_found', 'Benutzer nicht gefunden', ['status' => 404]);
        }

        // Company: prefer WC billing_company, fall back to legacy infoplus_company
        $company = get_user_meta($user_id, 'billing_company', true)
                ?: get_user_meta($user_id, 'infoplus_company', true);

        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                // Legacy fields kept for backward compat with old Elementor form
                'user_name'    => $user->display_name,
                'user_email'   => $user->user_email,
                'user_company' => $company,
                'avatar_url'   => $this->getUserAvatarUrl($user_id),
                // Extended fields for [infoplus_profile_settings] shortcode
                'first_name'   => $user->first_name,
                'last_name'    => $user->last_name,
                'phone'        => get_user_meta($user_id, 'billing_phone', true),
                'company'      => $company,
                'vat_id'       => get_user_meta($user_id, 'infoplus_vat_id', true),
                'address_1'    => get_user_meta($user_id, 'billing_address_1', true),
                'address_2'    => get_user_meta($user_id, 'billing_address_2', true),
                'postcode'     => get_user_meta($user_id, 'billing_postcode', true),
                'city'         => get_user_meta($user_id, 'billing_city', true),
                'country'      => get_user_meta($user_id, 'billing_country', true),
            ]
        ], 200);
    }

    /**
     * Update user profile information.
     * Accepts both the legacy Elementor-form fields (user_name, user_email, user_company)
     * and the new extended fields from [infoplus_profile_settings] shortcode.
     */
    public function updateUserProfile(\WP_REST_Request $request)
    {
        $user_id = get_current_user_id();

        // ── Email (required by both old and new form) ────────────────────────
        $email = sanitize_email(
            $request->get_param('user_email') ?: $request->get_param('email') ?: ''
        );

        if ($email && !is_email($email)) {
            return new \WP_Error('invalid_email', 'Ungültige E-Mail-Adresse', ['status' => 400]);
        }

        if ($email) {
            $email_exists = email_exists($email);
            if ($email_exists && $email_exists != $user_id) {
                return new \WP_Error('email_exists', 'Diese E-Mail-Adresse wird bereits verwendet', ['status' => 400]);
            }
        }

        // ── Core WP user fields ───────────────────────────────────────────────
        $first_name   = sanitize_text_field($request->get_param('first_name') ?? '');
        $last_name    = sanitize_text_field($request->get_param('last_name')  ?? '');
        // Build display_name from first+last if available, else use legacy user_name
        $display_name = trim($first_name . ' ' . $last_name)
                     ?: sanitize_text_field($request->get_param('user_name') ?? '');

        $user_data = ['ID' => $user_id];
        if ($display_name)  $user_data['display_name'] = $display_name;
        if ($first_name)    $user_data['first_name']   = $first_name;
        if ($last_name)     $user_data['last_name']    = $last_name;
        if ($email)         $user_data['user_email']   = $email;

        $result = wp_update_user($user_data);
        if (is_wp_error($result)) {
            return new \WP_Error('update_failed', $result->get_error_message(), ['status' => 500]);
        }

        // ── WooCommerce billing meta (auto-populates checkout) ────────────────
        $wc_fields = [
            'billing_phone'     => $request->get_param('phone'),
            'billing_company'   => $request->get_param('company')
                                ?: $request->get_param('user_company'),  // legacy compat
            'billing_address_1' => $request->get_param('address_1'),
            'billing_address_2' => $request->get_param('address_2'),
            'billing_postcode'  => $request->get_param('postcode'),
            'billing_city'      => $request->get_param('city'),
            'billing_country'   => $request->get_param('country'),
            // Mirror first/last into billing so WC checkout is fully pre-filled
            'billing_first_name' => $first_name,
            'billing_last_name'  => $last_name,
        ];

        foreach ($wc_fields as $meta_key => $value) {
            if ($value !== null) {
                update_user_meta($user_id, $meta_key, sanitize_text_field((string) $value));
            }
        }

        // ── Custom Infoplus meta ──────────────────────────────────────────────
        $vat_id  = $request->get_param('vat_id');
        $company = $request->get_param('company') ?: $request->get_param('user_company');

        if ($vat_id  !== null) update_user_meta($user_id, 'infoplus_vat_id',  sanitize_text_field($vat_id));
        if ($company !== null) update_user_meta($user_id, 'infoplus_company',  sanitize_text_field($company)); // BC

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Profil erfolgreich aktualisiert',
        ], 200);
    }

    /**
     * Change user password
     */
    public function changePassword(\WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);

        $current_password = $request->get_param('current_password');
        $new_password = $request->get_param('new_password');
        $confirm_password = $request->get_param('confirm_password');

        // Verify current password
        if (!wp_check_password($current_password, $user->user_pass, $user_id)) {
            return new \WP_Error('wrong_password', 'Das aktuelle Passwort ist falsch', ['status' => 400]);
        }

        // Check if new passwords match
        if ($new_password !== $confirm_password) {
            return new \WP_Error('password_mismatch', 'Die neuen Passwörter stimmen nicht überein', ['status' => 400]);
        }

        // Check password strength (minimum 8 characters)
        if (strlen($new_password) < 8) {
            return new \WP_Error('weak_password', 'Das Passwort muss mindestens 8 Zeichen lang sein', ['status' => 400]);
        }

        // Update password
        wp_set_password($new_password, $user_id);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Passwort erfolgreich geändert',
        ], 200);
    }

    /**
     * Upload avatar image
     */
    public function uploadAvatar(\WP_REST_Request $request)
    {
        $user_id = get_current_user_id();

        // Check if file was uploaded
        if (!isset($_FILES['avatar'])) {
            return new \WP_Error('no_file', 'Keine Datei hochgeladen', ['status' => 400]);
        }

        $file = $_FILES['avatar'];

        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowed_types)) {
            return new \WP_Error('invalid_type', 'Ungültiger Dateityp. Nur JPG, PNG und GIF erlaubt', ['status' => 400]);
        }

        // Validate file size (5MB max)
        $max_size = 5 * 1024 * 1024; // 5MB in bytes
        if ($file['size'] > $max_size) {
            return new \WP_Error('file_too_large', 'Die Dateigröße muss kleiner als 5 MB sein', ['status' => 400]);
        }

        // Upload file to WordPress media library
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $attachment_id = media_handle_upload('avatar', 0);

        if (is_wp_error($attachment_id)) {
            return new \WP_Error('upload_failed', $attachment_id->get_error_message(), ['status' => 500]);
        }

        // Save attachment ID to user meta
        update_user_meta($user_id, 'infoplus_user_avatar', $attachment_id);

        $avatar_url = wp_get_attachment_url($attachment_id);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Avatar erfolgreich hochgeladen',
            'avatar_url' => $avatar_url,
        ], 200);
    }

    /**
     * Get user avatar URL
     */
    private function getUserAvatarUrl($user_id)
    {
        $avatar_id = get_user_meta($user_id, 'infoplus_user_avatar', true);
        
        if ($avatar_id) {
            $url = wp_get_attachment_url($avatar_id);
            if ($url) {
                return $url;
            }
        }

        // Fallback to Gravatar
        return get_avatar_url($user_id);
    }
    
    /**
     * Redirect logged-in users away from login page to dashboard
     */
    public function redirectLoggedInUsers(): void
    {
        // Don't redirect if in Elementor editor/preview mode
        if (isset($_GET['elementor-preview']) || 
            isset($_GET['elementor_library']) || 
            (isset($_GET['action']) && $_GET['action'] === 'elementor') ||
            (function_exists('\\Elementor\\Plugin::instance') && \Elementor\Plugin::$instance->editor->is_edit_mode())) {
            return;
        }
    
        // Don't redirect if in WordPress admin
        if (is_admin()) {
            return;
        }
    
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return; // Not logged in, allow access to login page
        }
        
        // Get current page URL path
        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        
        // Check if we're on the login page
        $login_paths = [
            '/log-in/',
            '/log-in',
            '/login/',
            '/login',
            '/sign-in/',
            '/sign-in',
        ];
        
        // Check if current URL matches any login path
        foreach ($login_paths as $path) {
            if (strpos($current_url, $path) !== false) {
                // User is logged in and trying to access login page
                // Redirect to dashboard
                wp_safe_redirect(home_url('/account'));
                exit;
            }
        }
    }

    /**
     * Redirect guests (non-logged-in users) away from the account/dashboard area.
     * Sends them to the login page so they can authenticate first.
     */
    public function redirectGuestUsers(): void
    {
        // Never redirect inside the Elementor editor or WP admin
        if (is_admin()) {
            return;
        }

        if (
            isset($_GET['elementor-preview']) ||
            isset($_GET['elementor_library']) ||
            (isset($_GET['action']) && $_GET['action'] === 'elementor')
        ) {
            return;
        }

        // Already logged in — nothing to do
        if (is_user_logged_in()) {
            return;
        }

        $current_url = $_SERVER['REQUEST_URI'] ?? '';

        // Protected paths that require login
        $protected_paths = [
            '/account',
        ];

        foreach ($protected_paths as $path) {
            if (strpos($current_url, $path) === 0 || $current_url === $path) {
                // Send guests to the login page; after login WC/WP redirects them to /account
                wp_safe_redirect(home_url('/log-in/'));
                exit;
            }
        }
    }

    /**
     * Render the full profile-settings form.
     * Shortcode: [infoplus_profile_settings]
     *
     * Sections:
     *  1. Profilbild       — avatar upload (reuses existing JS #infoplus-avatar-* IDs)
     *  2. Persönliche Daten — Vorname, Nachname, E-Mail, Telefon
     *  3. Firmendaten       — Firmenname, Umsatzsteuer-ID
     *  4. Adresse           — Straße, Adresszusatz, PLZ, Stadt, Land (WC dropdown)
     *  5. Passwort ändern   — reuses existing JS #infoplus_password_form
     *
     * JS in user-dashboard.js pre-populates via GET /user/profile and handles submit
     * via POST /user/profile using the #infoplus-profile-settings-form ID.
     */
    public function renderProfileSettings(): string
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $countries = function_exists('WC') ? WC()->countries->get_countries() : [];

        ob_start();
        ?>
        <style>
        .infoplus-settings-wrap {
            font-family: inherit;
        }
        .infoplus-settings-card {
            background: #fff;
            border-radius: 12px;
            padding: 28px 32px;
            margin-bottom: 24px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        }
        .infoplus-settings-card h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0 0 20px 0;
            padding-bottom: 12px;
            border-bottom: 1px solid #f0f0f0;
        }
        .infoplus-settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .infoplus-settings-grid.cols-1 {
            grid-template-columns: 1fr;
        }
        .infoplus-settings-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .infoplus-settings-field.full-width {
            grid-column: 1 / -1;
        }
        .infoplus-settings-field label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #555;
            letter-spacing: 0.3px;
        }
        .infoplus-settings-field input,
        .infoplus-settings-field select {
            padding: 10px 14px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            color: #1a1a1a;
            background: #fafafa;
            transition: border-color 0.2s, box-shadow 0.2s;
            width: 100%;
            box-sizing: border-box;
        }
        .infoplus-settings-field input:focus,
        .infoplus-settings-field select:focus {
            outline: none;
            border-color: #e91e8c;
            box-shadow: 0 0 0 3px rgba(233,30,140,0.1);
            background: #fff;
        }
        .infoplus-settings-field input::placeholder {
            color: #bbb;
        }
        .infoplus-settings-save-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
            padding: 11px 28px;
            background: #e91e8c;
            color: #fff !important;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s, transform 0.15s;
        }
        .infoplus-settings-save-btn:hover {
            opacity: 0.88;
            transform: translateY(-1px);
        }
        .infoplus-settings-save-btn:disabled {
            opacity: 0.6;
            cursor: default;
            transform: none;
        }
        .infoplus-settings-feedback {
            margin-top: 14px;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            display: none;
        }
        .infoplus-settings-feedback.success {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }
        .infoplus-settings-feedback.error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        /* Avatar */
        .infoplus-avatar-row {
            display: flex;
            align-items: center;
            gap: 24px;
        }
        #infoplus-avatar-preview {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #f0f0f0;
        }
        .infoplus-avatar-actions p {
            font-size: 0.8rem;
            color: #999;
            margin: 6px 0 0 0;
        }
        #infoplus-avatar-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 20px;
            background: #e91e8c;
            color: #fff !important;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        #infoplus-avatar-btn:hover { opacity: 0.88; }
        #infoplus-avatar-upload { display: none; }

        @media (max-width: 640px) {
            .infoplus-settings-card { padding: 20px 16px; }
            .infoplus-settings-grid { grid-template-columns: 1fr; }
        }
        </style>

        <div class="infoplus-settings-wrap">

            <!-- 1. Profilbild -->
            <div class="infoplus-settings-card">
                <h3>Profilbild</h3>
                <div class="infoplus-avatar-row">
                    <img id="infoplus-avatar-preview" src="" alt="Profilbild">
                    <div class="infoplus-avatar-actions">
                        <button type="button" id="infoplus-avatar-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                            Neues Bild hochladen
                        </button>
                        <input type="file" id="infoplus-avatar-upload" accept="image/jpeg,image/png,image/gif">
                        <p>JPG, PNG oder GIF. Maximal 5 MB.</p>
                    </div>
                </div>
            </div>

            <!-- 2–4. Main profile form -->
            <form id="infoplus-profile-settings-form" novalidate>

                <!-- 2. Persönliche Daten -->
                <div class="infoplus-settings-card">
                    <h3>Persönliche Daten</h3>
                    <div class="infoplus-settings-grid">
                        <div class="infoplus-settings-field">
                            <label for="infoplus-field-first_name">Vorname</label>
                            <input type="text" id="infoplus-field-first_name" name="first_name"
                                   placeholder="Max" autocomplete="given-name">
                        </div>
                        <div class="infoplus-settings-field">
                            <label for="infoplus-field-last_name">Nachname</label>
                            <input type="text" id="infoplus-field-last_name" name="last_name"
                                   placeholder="Mustermann" autocomplete="family-name">
                        </div>
                        <div class="infoplus-settings-field">
                            <label for="infoplus-field-email">E-Mail-Adresse</label>
                            <input type="email" id="infoplus-field-email" name="email"
                                   placeholder="max@firma.de" autocomplete="email">
                        </div>
                        <div class="infoplus-settings-field">
                            <label for="infoplus-field-phone">Telefon</label>
                            <input type="tel" id="infoplus-field-phone" name="phone"
                                   placeholder="+49 30 123456" autocomplete="tel">
                        </div>
                    </div>
                </div>

                <!-- 3. Firmendaten -->
                <div class="infoplus-settings-card">
                    <h3>Firmendaten</h3>
                    <div class="infoplus-settings-grid">
                        <div class="infoplus-settings-field">
                            <label for="infoplus-field-company">Firmenname</label>
                            <input type="text" id="infoplus-field-company" name="company"
                                   placeholder="Muster GmbH" autocomplete="organization">
                        </div>
                        <div class="infoplus-settings-field">
                            <label for="infoplus-field-vat_id">Umsatzsteuer-ID</label>
                            <input type="text" id="infoplus-field-vat_id" name="vat_id"
                                   placeholder="DE123456789">
                        </div>
                    </div>
                </div>

                <!-- 4. Adresse -->
                <div class="infoplus-settings-card">
                    <h3>Rechnungs- &amp; Lieferadresse</h3>
                    <div class="infoplus-settings-grid">
                        <div class="infoplus-settings-field full-width">
                            <label for="infoplus-field-address_1">Straße und Hausnummer</label>
                            <input type="text" id="infoplus-field-address_1" name="address_1"
                                   placeholder="Musterstraße 42" autocomplete="address-line1">
                        </div>
                        <div class="infoplus-settings-field full-width">
                            <label for="infoplus-field-address_2">Adresszusatz <span style="font-weight:400;color:#999">(optional)</span></label>
                            <input type="text" id="infoplus-field-address_2" name="address_2"
                                   placeholder="c/o, Etage, Gebäude …" autocomplete="address-line2">
                        </div>
                        <div class="infoplus-settings-field">
                            <label for="infoplus-field-postcode">Postleitzahl</label>
                            <input type="text" id="infoplus-field-postcode" name="postcode"
                                   placeholder="10115" autocomplete="postal-code">
                        </div>
                        <div class="infoplus-settings-field">
                            <label for="infoplus-field-city">Stadt</label>
                            <input type="text" id="infoplus-field-city" name="city"
                                   placeholder="Berlin" autocomplete="address-level2">
                        </div>
                        <div class="infoplus-settings-field full-width">
                            <label for="infoplus-field-country">Land</label>
                            <select id="infoplus-field-country" name="country" autocomplete="country">
                                <option value="">— Land auswählen —</option>
                                <?php foreach ($countries as $code => $label): ?>
                                <option value="<?php echo esc_attr($code); ?>">
                                    <?php echo esc_html($label); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <button type="submit" class="infoplus-settings-save-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                        <polyline points="17 21 17 13 7 13 7 21"/>
                        <polyline points="7 3 7 8 15 8"/>
                    </svg>
                    Änderungen speichern
                </button>

                <div id="infoplus-settings-feedback" class="infoplus-settings-feedback"></div>

            </form>

            <!-- 5. Passwort ändern -->
            <form id="infoplus_password_form" class="infoplus-settings-card" novalidate>
                <h3>Passwort ändern</h3>
                <div class="infoplus-settings-grid cols-1">
                    <div class="infoplus-settings-field">
                        <label for="infoplus-pwd-current">Aktuelles Passwort</label>
                        <input type="password" id="infoplus-pwd-current"
                               name="form_fields[current_password]"
                               placeholder="Aktuelles Passwort eingeben" autocomplete="current-password">
                    </div>
                    <div class="infoplus-settings-field">
                        <label for="infoplus-pwd-new">Neues Passwort</label>
                        <input type="password" id="infoplus-pwd-new"
                               name="form_fields[new_password]"
                               placeholder="Neues Passwort eingeben" autocomplete="new-password">
                    </div>
                    <div class="infoplus-settings-field">
                        <label for="infoplus-pwd-confirm">Neues Passwort bestätigen</label>
                        <input type="password" id="infoplus-pwd-confirm"
                               name="form_fields[confirm_password]"
                               placeholder="Neues Passwort bestätigen" autocomplete="new-password">
                    </div>
                </div>
                <button type="submit" class="infoplus-settings-save-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    Passwort aktualisieren
                </button>
                <div id="infoplus-password-feedback" class="infoplus-settings-feedback"></div>
            </form>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render login guard block (shown to non-logged-in users)
     * Shortcode: [infoplus_login_guard]
     */
    public function renderLoginGuard(): string
    {
        // If user is logged in, return empty (show the dashboard)
        if (is_user_logged_in()) {
            return '';
        }
        
        // Get the login page URL
        $login_url = home_url('/log-in');
        
        // Return the HTML for non-logged-in users
        ob_start();
        ?>
        <div class="infoplus-login-guard">
            <div class="login-guard-container">
                <div class="login-guard-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </div>
                <h2 class="login-guard-title">Dashboard-Zugriff erforderlich</h2>
                <p class="login-guard-message">
                    Sie müssen angemeldet sein, um auf Ihr Dashboard zuzugreifen und Ihre Projekte anzuzeigen.
                </p>
                <a href="<?php echo esc_url($login_url); ?>" class="login-guard-button">
                    Anmelden um fortzufahren
                </a>
            </div>
        </div>
        
        <style>
            .infoplus-login-guard {
                min-height: 60vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 40px 20px;
                background: linear-gradient(135deg, #f5f7fa 0%, #f9f9fb 100%);
                border-radius: 12px;
                margin: 20px 0;
            }
            
            .login-guard-container {
                text-align: center;
                max-width: 480px;
                background: white;
                padding: 60px 40px;
                border-radius: 16px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            }
            
            .login-guard-icon {
                color: #e91e8c;
                margin-bottom: 24px;
                opacity: 0;
                animation: fadeInUp 0.6s ease forwards;
            }
            
            .login-guard-icon svg {
                width: 80px;
                height: 80px;
            }
            
            .login-guard-title {
                font-size: 28px;
                font-weight: 700;
                color: #1a1a1a;
                margin: 0 0 16px 0;
                opacity: 0;
                animation: fadeInUp 0.6s ease 0.1s forwards;
            }
            
            .login-guard-message {
                font-size: 16px;
                color: #666;
                line-height: 1.6;
                margin: 0 0 32px 0;
                opacity: 0;
                animation: fadeInUp 0.6s ease 0.2s forwards;
            }
            
            .login-guard-button {
                display: inline-block;
                background: linear-gradient(135deg, #e91e8c 0%, #d91a7e 100%);
                color: white !important;
                padding: 16px 40px;
                border-radius: 8px;
                font-size: 16px;
                font-weight: 600;
                text-decoration: none;
                transition: all 0.3s ease;
                box-shadow: 0 4px 16px rgba(233, 30, 140, 0.3);
                opacity: 0;
                animation: fadeInUp 0.6s ease 0.3s forwards;
            }
            
            .login-guard-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 24px rgba(233, 30, 140, 0.4);
                background: linear-gradient(135deg, #d91a7e 0%, #c91670 100%);
            }
            
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            @media (max-width: 768px) {
                .login-guard-container {
                    padding: 40px 24px;
                }
                
                .login-guard-title {
                    font-size: 24px;
                }
                
                .login-guard-icon svg {
                    width: 64px;
                    height: 64px;
                }
            }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Custom login redirect - force users to /account page
     */
    public function customLoginRedirect($redirect_to, $request, $user)
    {
        // Only redirect if it's a successful login (user object exists)
        if (isset($user->ID)) {
            // Force redirect to /account instead of /my-account
            return home_url('/account');
        }
        
        return $redirect_to;
    }

    /**
     * WooCommerce login redirect - override the default my-account redirect
     */
    public function woocommerceLoginRedirect($redirect, $user)
    {
        // Force redirect to /account for all WooCommerce logins
        return home_url('/account');
    }
}