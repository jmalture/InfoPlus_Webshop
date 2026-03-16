<?php
namespace Infoplus\Suite\Modules\EditorLinkSettings;

use Infoplus\Suite\Contracts\ModuleInterface;

if (!defined('ABSPATH')) exit;

final class Module implements ModuleInterface
{
    const OPTION_BASE_DESKTOP_URL = 'infoplus_editor_base_desktop_url';
    const OPTION_BASE_MOBILE_URL = 'infoplus_editor_base_mobile_url';
    const OPTION_THEME_ID = 'infoplus_editor_theme_id';
    const OPTION_CART_ENTRY_PATH = 'infoplus_editor_cart_entry_path';
    const OPTION_BACK_PATH = 'infoplus_editor_back_path';
    const OPTION_BEARER_TOKEN = 'infoplus_editor_bearer_token';

    public function slug(): string  { return 'editor-link-settings'; }
    public function label(): string { return __('Settings', 'infoplus-suite'); }

    public function register(): void
    {
        // Register settings
        add_action('admin_init', [$this, 'registerSettings']);
        
        // Add settings UI to dashboard
        add_action('infoplus/module/settings/' . $this->slug(), [$this, 'renderSettings']);
    }

    public function registerSettings(): void
    {
        register_setting('infoplus_editor_link_settings', self::OPTION_BASE_DESKTOP_URL, [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => 'https://editor.infoplus.de/e2c/editor',
        ]);

        register_setting('infoplus_editor_link_settings', self::OPTION_BASE_MOBILE_URL, [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => 'https://m.editor.infoplus.de/e2c/editor',
        ]);

        register_setting('infoplus_editor_link_settings', self::OPTION_THEME_ID, [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);

        register_setting('infoplus_editor_link_settings', self::OPTION_CART_ENTRY_PATH, [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '/e2-cart',
        ]);

        register_setting('infoplus_editor_link_settings', self::OPTION_BACK_PATH, [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '{product_url}',
        ]);

        register_setting('infoplus_editor_link_settings', self::OPTION_BEARER_TOKEN, [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);
    }

    public function renderSettings(): void
    {
        // Handle form submission
        $saved = false;
        if (isset($_POST['infoplus_editor_settings_nonce']) && 
            wp_verify_nonce($_POST['infoplus_editor_settings_nonce'], 'infoplus_editor_settings_save')) {
            
            if (isset($_POST[self::OPTION_BASE_DESKTOP_URL])) {
                update_option(self::OPTION_BASE_DESKTOP_URL, esc_url_raw($_POST[self::OPTION_BASE_DESKTOP_URL]));
            }
            if (isset($_POST[self::OPTION_BASE_MOBILE_URL])) {
                update_option(self::OPTION_BASE_MOBILE_URL, esc_url_raw($_POST[self::OPTION_BASE_MOBILE_URL]));
            }
            if (isset($_POST[self::OPTION_THEME_ID])) {
                update_option(self::OPTION_THEME_ID, sanitize_text_field($_POST[self::OPTION_THEME_ID]));
            }
            if (isset($_POST[self::OPTION_CART_ENTRY_PATH])) {
                update_option(self::OPTION_CART_ENTRY_PATH, sanitize_text_field($_POST[self::OPTION_CART_ENTRY_PATH]));
            }
            if (isset($_POST[self::OPTION_BACK_PATH])) {
                update_option(self::OPTION_BACK_PATH, sanitize_text_field($_POST[self::OPTION_BACK_PATH]));
            }
            if (isset($_POST[self::OPTION_BEARER_TOKEN])) {
                update_option(self::OPTION_BEARER_TOKEN, sanitize_text_field($_POST[self::OPTION_BEARER_TOKEN]));
            }
            
            $saved = true;
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 esc_html__('Settings saved successfully!', 'infoplus-suite') . 
                 '</p></div>';
        }

        $baseDesktopUrl = get_option(self::OPTION_BASE_DESKTOP_URL, 'https://editor.infoplus.de/e2c/editor');
        $baseMobileUrl = get_option(self::OPTION_BASE_MOBILE_URL, 'https://m.editor.infoplus.de/e2c/editor');
        $themeId = get_option(self::OPTION_THEME_ID, '');
        $cartEntryPath = get_option(self::OPTION_CART_ENTRY_PATH, '/e2-cart');
        $backPath = get_option(self::OPTION_BACK_PATH, '{product_url}');
        $bearerToken = get_option(self::OPTION_BEARER_TOKEN, '');
        ?>
        <style>
            .infoplus-settings-wrap {
                max-width: 900px;
                margin-top: 20px;
            }
            .infoplus-settings-wrap .form-table th {
                width: 200px;
            }
            .infoplus-lockable-field {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .infoplus-lockable-field input {
                flex: 1;
            }
            .infoplus-lock-btn {
                background: none;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                padding: 6px 10px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s ease;
                min-width: 36px;
                height: 36px;
            }
            .infoplus-lock-btn:hover {
                background: #f0f0f1;
                border-color: #8c8f94;
            }
            .infoplus-lock-btn.locked {
                background: #f6f7f7;
                color: #8c8f94;
            }
            .infoplus-lock-btn.unlocked {
                background: #e7f5ee;
                color: #137333;
                border-color: #137333;
            }
            .infoplus-lock-btn .dashicons {
                font-size: 18px;
                width: 18px;
                height: 18px;
            }
            .infoplus-lockable-field input:read-only {
                background: #f6f7f7;
                color: #50575e;
                cursor: not-allowed;
            }
            .infoplus-settings-section {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 8px;
                padding: 20px 24px;
                margin-bottom: 20px;
            }
            .infoplus-settings-section h3 {
                margin-top: 0;
                padding-bottom: 12px;
                border-bottom: 1px solid #e5e5e5;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .infoplus-settings-section h3 .dashicons {
                color: #2271b1;
            }
            .infoplus-example-box {
                margin-top: 20px;
                padding: 15px;
                background: #f0f0f1;
                border-left: 4px solid #2271b1;
                border-radius: 0 4px 4px 0;
            }
            .infoplus-example-box h4 {
                margin: 0 0 15px 0;
            }
            .infoplus-example-url {
                background: #fff;
                padding: 10px 12px;
                border-radius: 4px;
                font-family: monospace;
                font-size: 12px;
                word-break: break-all;
                margin: 10px 0;
            }
            .infoplus-save-btn-wrap {
                margin-top: 24px;
                padding-top: 20px;
                border-top: 1px solid #e5e5e5;
            }
            .infoplus-unsaved-indicator {
                display: none;
                padding: 8px 14px;
                background: #fff4e5;
                border: 1px solid #ffd699;
                border-radius: 4px;
                color: #8a6116;
                font-size: 13px;
                margin-bottom: 12px;
            }
            .infoplus-unsaved-indicator.show {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .infoplus-unsaved-indicator .dashicons {
                font-size: 16px;
            }
        </style>

        <div class="infoplus-settings-wrap">
            <h2><?php echo esc_html__('Editor Configuration', 'infoplus-suite'); ?></h2>
            <p class="description">
                <?php echo esc_html__('Configure the editor URLs and parameters for the IFE system integration.', 'infoplus-suite'); ?>
            </p>

            <form method="post" action="" id="infoplus-settings-form">
                <?php wp_nonce_field('infoplus_editor_settings_save', 'infoplus_editor_settings_nonce'); ?>
                
                <!-- Base URLs Section -->
                <div class="infoplus-settings-section">
                    <h3>
                        <span class="dashicons dashicons-admin-links"></span>
                        <?php echo esc_html__('Editor Base URLs', 'infoplus-suite'); ?>
                    </h3>
                    
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="<?php echo esc_attr(self::OPTION_BASE_DESKTOP_URL); ?>">
                                        <?php echo esc_html__('Base Desktop URL', 'infoplus-suite'); ?>
                                    </label>
                                </th>
                                <td>
                                    <div class="infoplus-lockable-field">
                                        <input 
                                            type="url" 
                                            name="<?php echo esc_attr(self::OPTION_BASE_DESKTOP_URL); ?>" 
                                            id="<?php echo esc_attr(self::OPTION_BASE_DESKTOP_URL); ?>" 
                                            value="<?php echo esc_attr($baseDesktopUrl); ?>" 
                                            class="regular-text infoplus-tracked-field" 
                                            placeholder="https://editor.infoplus.de/e2c/editor"
                                            required
                                            readonly
                                        />
                                        <button type="button" class="infoplus-lock-btn locked" data-target="<?php echo esc_attr(self::OPTION_BASE_DESKTOP_URL); ?>" title="<?php echo esc_attr__('Click to unlock and edit', 'infoplus-suite'); ?>">
                                            <span class="dashicons dashicons-lock"></span>
                                        </button>
                                    </div>
                                    <p class="description">
                                        <?php echo esc_html__('The base URL for the desktop editor.', 'infoplus-suite'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="<?php echo esc_attr(self::OPTION_BASE_MOBILE_URL); ?>">
                                        <?php echo esc_html__('Base Mobile URL', 'infoplus-suite'); ?>
                                    </label>
                                </th>
                                <td>
                                    <div class="infoplus-lockable-field">
                                        <input 
                                            type="url" 
                                            name="<?php echo esc_attr(self::OPTION_BASE_MOBILE_URL); ?>" 
                                            id="<?php echo esc_attr(self::OPTION_BASE_MOBILE_URL); ?>" 
                                            value="<?php echo esc_attr($baseMobileUrl); ?>" 
                                            class="regular-text infoplus-tracked-field" 
                                            placeholder="https://m.editor.infoplus.de/e2c/editor"
                                            required
                                            readonly
                                        />
                                        <button type="button" class="infoplus-lock-btn locked" data-target="<?php echo esc_attr(self::OPTION_BASE_MOBILE_URL); ?>" title="<?php echo esc_attr__('Click to unlock and edit', 'infoplus-suite'); ?>">
                                            <span class="dashicons dashicons-lock"></span>
                                        </button>
                                    </div>
                                    <p class="description">
                                        <?php echo esc_html__('The base URL for the mobile editor.', 'infoplus-suite'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="<?php echo esc_attr(self::OPTION_THEME_ID); ?>">
                                        <?php echo esc_html__('Global Theme ID', 'infoplus-suite'); ?>
                                    </label>
                                </th>
                                <td>
                                    <div class="infoplus-lockable-field">
                                        <input 
                                            type="text" 
                                            name="<?php echo esc_attr(self::OPTION_THEME_ID); ?>" 
                                            id="<?php echo esc_attr(self::OPTION_THEME_ID); ?>" 
                                            value="<?php echo esc_attr($themeId); ?>" 
                                            class="regular-text infoplus-tracked-field" 
                                            placeholder="a42e4312-8bd0-4d53-b0c0-b17c28b81fa9"
                                            required
                                            readonly
                                        />
                                        <button type="button" class="infoplus-lock-btn locked" data-target="<?php echo esc_attr(self::OPTION_THEME_ID); ?>" title="<?php echo esc_attr__('Click to unlock and edit', 'infoplus-suite'); ?>">
                                            <span class="dashicons dashicons-lock"></span>
                                        </button>
                                    </div>
                                    <p class="description">
                                        <?php echo esc_html__('The theme UUID to be used for all products.', 'infoplus-suite'); ?>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- URL Parameters Section (Locked fields) -->
                <div class="infoplus-settings-section">
                    <h3>
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php echo esc_html__('URL Parameters', 'infoplus-suite'); ?>
                    </h3>
                    <p class="description" style="margin-top: -8px; margin-bottom: 16px;">
                        <?php echo esc_html__('These parameters are appended to the editor URL. Click the lock icon to edit.', 'infoplus-suite'); ?>
                    </p>
                    
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="<?php echo esc_attr(self::OPTION_CART_ENTRY_PATH); ?>">
                                        <?php echo esc_html__('Cart Entry Path', 'infoplus-suite'); ?>
                                    </label>
                                </th>
                                <td>
                                    <div class="infoplus-lockable-field">
                                        <input 
                                            type="text" 
                                            name="<?php echo esc_attr(self::OPTION_CART_ENTRY_PATH); ?>" 
                                            id="<?php echo esc_attr(self::OPTION_CART_ENTRY_PATH); ?>" 
                                            value="<?php echo esc_attr($cartEntryPath); ?>" 
                                            class="regular-text infoplus-tracked-field" 
                                            placeholder="/e2-cart"
                                            readonly
                                        />
                                        <button type="button" class="infoplus-lock-btn locked" data-target="<?php echo esc_attr(self::OPTION_CART_ENTRY_PATH); ?>" title="<?php echo esc_attr__('Click to unlock and edit', 'infoplus-suite'); ?>">
                                            <span class="dashicons dashicons-lock"></span>
                                        </button>
                                    </div>
                                    <p class="description">
                                        <?php echo esc_html__('URL parameter: cartEntryPath=', 'infoplus-suite'); ?><code><?php echo esc_html($cartEntryPath); ?></code>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="<?php echo esc_attr(self::OPTION_BACK_PATH); ?>">
                                        <?php echo esc_html__('Back Path', 'infoplus-suite'); ?>
                                    </label>
                                </th>
                                <td>
                                    <div class="infoplus-lockable-field">
                                        <input 
                                            type="text" 
                                            name="<?php echo esc_attr(self::OPTION_BACK_PATH); ?>" 
                                            id="<?php echo esc_attr(self::OPTION_BACK_PATH); ?>" 
                                            value="<?php echo esc_attr($backPath); ?>" 
                                            class="regular-text infoplus-tracked-field" 
                                            placeholder="{product_url}"
                                            readonly
                                        />
                                        <button type="button" class="infoplus-lock-btn locked" data-target="<?php echo esc_attr(self::OPTION_BACK_PATH); ?>" title="<?php echo esc_attr__('Click to unlock and edit', 'infoplus-suite'); ?>">
                                            <span class="dashicons dashicons-lock"></span>
                                        </button>
                                    </div>
                                    <p class="description">
                                        <?php echo esc_html__('URL parameter: backPath= (use {product_url} as placeholder for the current product URL)', 'infoplus-suite'); ?>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- API Authentication Section -->
                <div class="infoplus-settings-section">
                    <h3>
                        <span class="dashicons dashicons-shield"></span>
                        <?php echo esc_html__('API Authentication', 'infoplus-suite'); ?>
                    </h3>
                    
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="<?php echo esc_attr(self::OPTION_BEARER_TOKEN); ?>">
                                        <?php echo esc_html__('Bearer Token', 'infoplus-suite'); ?>
                                    </label>
                                </th>
                                <td>
                                    <div class="infoplus-lockable-field">
                                        <input 
                                            type="password" 
                                            name="<?php echo esc_attr(self::OPTION_BEARER_TOKEN); ?>" 
                                            id="<?php echo esc_attr(self::OPTION_BEARER_TOKEN); ?>" 
                                            value="<?php echo esc_attr($bearerToken); ?>" 
                                            class="regular-text infoplus-tracked-field" 
                                            placeholder="<?php echo esc_attr__('Enter Bearer Token', 'infoplus-suite'); ?>"
                                            autocomplete="off"
                                            readonly
                                        />
                                        <button type="button" class="infoplus-lock-btn locked" data-target="<?php echo esc_attr(self::OPTION_BEARER_TOKEN); ?>" title="<?php echo esc_attr__('Click to unlock and edit', 'infoplus-suite'); ?>">
                                            <span class="dashicons dashicons-lock"></span>
                                        </button>
                                    </div>
                                    <p class="description">
                                        <?php echo esc_html__('API bearer token for authentication (will be obtained through API).', 'infoplus-suite'); ?>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Example URLs Box -->
                <div class="infoplus-example-box">
                    <h4><?php echo esc_html__('Generated Editor URL Example:', 'infoplus-suite'); ?></h4>
                    <p class="description" style="margin-bottom: 10px;">
                        <?php echo esc_html__('When a customer clicks the editor button on a product page, the following URL is generated:', 'infoplus-suite'); ?>
                    </p>
                    <div class="infoplus-example-url" id="infoplus-example-url">
                        <?php 
                        $exampleProductUrl = 'https://yourshop.com/product/example-product/';
                        $exampleUuid = '49bf6048-6a9c-42f0-bd9d-2d771ab481ab';
                        $exampleBackPath = ($backPath === '{product_url}') ? $exampleProductUrl : $backPath;
                        
                        // Build example URL WITHOUT encoding for readability
                        $exampleUrl = $baseDesktopUrl . '?product=' . $exampleUuid;
                        if (!empty($themeId)) {
                            $exampleUrl .= '&theme=' . $themeId;
                        }
                        $exampleUrl .= '&cartEntryPath=' . $cartEntryPath;
                        $exampleUrl .= '&backPath=' . $exampleBackPath;
                        
                        echo esc_html($exampleUrl);
                        ?>
                    </div>
                    <p class="description">
                        <code>{product_url}</code> <?php echo esc_html__('is dynamically replaced with the actual product page URL where the button was clicked.', 'infoplus-suite'); ?>
                    </p>
                </div>

                <!-- Save Button -->
                <div class="infoplus-save-btn-wrap">
                    <div class="infoplus-unsaved-indicator" id="infoplus-unsaved-indicator">
                        <span class="dashicons dashicons-warning"></span>
                        <?php echo esc_html__('You have unsaved changes.', 'infoplus-suite'); ?>
                    </div>
                    <button type="submit" class="button button-primary button-large" id="infoplus-save-btn">
                        <?php echo esc_html__('Save Settings', 'infoplus-suite'); ?>
                    </button>
                </div>
            </form>
        </div>

        <script>
        jQuery(function($) {
            var formChanged = false;
            var initialValues = {};
            
            // Store initial values
            $('.infoplus-tracked-field').each(function() {
                initialValues[this.id] = $(this).val();
            });
            
            // Track changes
            $(document).on('input change', '.infoplus-tracked-field', function() {
                checkForChanges();
                updateExampleUrl();
            });
            
            function checkForChanges() {
                formChanged = false;
                $('.infoplus-tracked-field').each(function() {
                    if ($(this).val() !== initialValues[this.id]) {
                        formChanged = true;
                        return false;
                    }
                });
                
                if (formChanged) {
                    $('#infoplus-unsaved-indicator').addClass('show');
                } else {
                    $('#infoplus-unsaved-indicator').removeClass('show');
                }
            }
            
            // Lock/Unlock functionality
            $('.infoplus-lock-btn').on('click', function() {
                var btn = $(this);
                var targetId = btn.data('target');
                var input = $('#' + targetId);
                
                if (btn.hasClass('locked')) {
                    // Unlock
                    btn.removeClass('locked').addClass('unlocked');
                    btn.find('.dashicons').removeClass('dashicons-lock').addClass('dashicons-unlock');
                    btn.attr('title', '<?php echo esc_js(__('Click to lock', 'infoplus-suite')); ?>');
                    input.prop('readonly', false).focus();
                } else {
                    // Lock
                    btn.removeClass('unlocked').addClass('locked');
                    btn.find('.dashicons').removeClass('dashicons-unlock').addClass('dashicons-lock');
                    btn.attr('title', '<?php echo esc_js(__('Click to unlock and edit', 'infoplus-suite')); ?>');
                    input.prop('readonly', true);
                }
            });
            
            // Save confirmation
            $('#infoplus-settings-form').on('submit', function(e) {
                if (!confirm('<?php echo esc_js(__('Are you sure you want to save these settings?', 'infoplus-suite')); ?>')) {
                    e.preventDefault();
                    return false;
                }
                formChanged = false;
            });
            
            // Unsaved changes warning on page leave
            $(window).on('beforeunload', function(e) {
                if (formChanged) {
                    var message = '<?php echo esc_js(__('You have unsaved changes. Are you sure you want to leave?', 'infoplus-suite')); ?>';
                    e.returnValue = message;
                    return message;
                }
            });
            
            // Update example URL when fields change
            function updateExampleUrl() {
                var baseUrl = $('#<?php echo esc_js(self::OPTION_BASE_DESKTOP_URL); ?>').val() || 'https://editor.infoplus.de/e2c/editor';
                var themeId = $('#<?php echo esc_js(self::OPTION_THEME_ID); ?>').val();
                var cartEntryPath = $('#<?php echo esc_js(self::OPTION_CART_ENTRY_PATH); ?>').val() || '/e2-cart';
                var backPath = $('#<?php echo esc_js(self::OPTION_BACK_PATH); ?>').val() || '{product_url}';
                
                var exampleProductUrl = 'https://yourshop.com/product/example-product/';
                var exampleUuid = '49bf6048-6a9c-42f0-bd9d-2d771ab481ab';
                var finalBackPath = (backPath === '{product_url}') ? exampleProductUrl : backPath;
                
                // Build URL WITHOUT encoding for readability
                var url = baseUrl + '?product=' + exampleUuid;
                if (themeId) {
                    url += '&theme=' + themeId;
                }
                url += '&cartEntryPath=' + cartEntryPath;
                url += '&backPath=' + finalBackPath;
                
                $('#infoplus-example-url').text(url);
            }
            
            <?php if ($saved): ?>
            // Reset tracking after successful save
            $('.infoplus-tracked-field').each(function() {
                initialValues[this.id] = $(this).val();
            });
            formChanged = false;
            $('#infoplus-unsaved-indicator').removeClass('show');
            <?php endif; ?>
        });
        </script>
        <?php
    }

    /**
     * Check if the current request is from a mobile device
     */
    public static function isMobile(): bool
    {
        // Check if wp_is_mobile() function exists (it should in WordPress)
        if (function_exists('wp_is_mobile')) {
            return wp_is_mobile();
        }
        
        // Fallback detection using user agent
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $mobile_patterns = [
            '/Android/i',
            '/webOS/i',
            '/iPhone/i',
            '/iPad/i',
            '/iPod/i',
            '/BlackBerry/i',
            '/Windows Phone/i',
            '/Mobile/i',
            '/Tablet/i'
        ];
        
        foreach ($mobile_patterns as $pattern) {
            if (preg_match($pattern, $user_agent)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get the appropriate base editor URL based on device type
     */
    public static function getBaseUrl(): string
    {
        if (self::isMobile()) {
            return get_option(self::OPTION_BASE_MOBILE_URL, 'https://m.editor.infoplus.de/e2c/editor');
        }
        return get_option(self::OPTION_BASE_DESKTOP_URL, 'https://editor.infoplus.de/e2c/editor');
    }

    /**
     * Get the desktop base URL
     */
    public static function getDesktopBaseUrl(): string
    {
        return get_option(self::OPTION_BASE_DESKTOP_URL, 'https://editor.infoplus.de/e2c/editor');
    }

    /**
     * Get the mobile base URL
     */
    public static function getMobileBaseUrl(): string
    {
        return get_option(self::OPTION_BASE_MOBILE_URL, 'https://m.editor.infoplus.de/e2c/editor');
    }

    /**
     * Get the global theme ID
     */
    public static function getThemeId(): string
    {
        return get_option(self::OPTION_THEME_ID, '');
    }

    /**
     * Get the cart entry path
     */
    public static function getCartEntryPath(): string
    {
        return get_option(self::OPTION_CART_ENTRY_PATH, '/e2-cart');
    }

    /**
     * Get the back path template
     */
    public static function getBackPath(): string
    {
        return get_option(self::OPTION_BACK_PATH, '{product_url}');
    }

    /**
     * Get the bearer token
     */
    public static function getBearerToken(): string
    {
        return get_option(self::OPTION_BEARER_TOKEN, '');
    }

    /**
     * Generate full editor URL for a product based on device type
     * 
     * @param string $productUuid The product UUID
     * @param string $productUrl The product page URL (for backPath)
     * @param bool|null $forceMobile Force mobile URL if true
     * @return string The complete editor URL
     */
    public static function generateEditorUrl(string $productUuid, string $productUrl = '', bool $forceMobile = null): string
    {
        if (empty($productUuid)) {
            return '';
        }

        // Determine if we should use mobile URL
        $useMobile = $forceMobile !== null ? $forceMobile : self::isMobile();
        
        // Get the appropriate base URL
        $baseUrl = $useMobile ? self::getMobileBaseUrl() : self::getDesktopBaseUrl();
        $themeId = self::getThemeId();

        if (empty($baseUrl) || empty($themeId)) {
            return '';
        }

        // Build URL
        $url = $baseUrl . '?product=' . urlencode($productUuid) . '&theme=' . urlencode($themeId);
        
        // Add cartEntryPath
        $cartEntryPath = self::getCartEntryPath();
        if (!empty($cartEntryPath)) {
            $url .= '&cartEntryPath=' . urlencode($cartEntryPath);
        }
        
        // Add backPath
        $backPathTemplate = self::getBackPath();
        if (!empty($backPathTemplate)) {
            // Replace {product_url} placeholder with actual product URL
            $backPath = str_replace('{product_url}', $productUrl, $backPathTemplate);
            $url .= '&backPath=' . urlencode($backPath);
        }

        return $url;
    }
}
