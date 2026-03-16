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
        
        // Register shortcode for login guard
        add_shortcode('infoplus_login_guard', [$this, 'renderLoginGuard']);
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

        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'user_name' => $user->display_name,
                'user_email' => $user->user_email,
                'user_company' => get_user_meta($user_id, 'infoplus_company', true),
                'avatar_url' => $this->getUserAvatarUrl($user_id),
            ]
        ], 200);
    }

    /**
     * Update user profile information
     */
    public function updateUserProfile(\WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        
        $name = sanitize_text_field($request->get_param('user_name'));
        $email = sanitize_email($request->get_param('user_email'));
        $company = sanitize_text_field($request->get_param('user_company'));

        // Validate email
        if (!is_email($email)) {
            return new \WP_Error('invalid_email', 'Ungültige E-Mail-Adresse', ['status' => 400]);
        }

        // Check if email is already taken by another user
        $email_exists = email_exists($email);
        if ($email_exists && $email_exists != $user_id) {
            return new \WP_Error('email_exists', 'Diese E-Mail-Adresse wird bereits verwendet', ['status' => 400]);
        }

        // Update user data
        $result = wp_update_user([
            'ID' => $user_id,
            'display_name' => $name,
            'user_email' => $email,
        ]);

        if (is_wp_error($result)) {
            return new \WP_Error('update_failed', $result->get_error_message(), ['status' => 500]);
        }

        // Update company (custom meta)
        update_user_meta($user_id, 'infoplus_company', $company);

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