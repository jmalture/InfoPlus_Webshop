<?php
namespace Infoplus\Suite\Modules\WooSingleProductAddons;

use Infoplus\Suite\Contracts\ModuleInterface;
use Infoplus\Suite\Plugin;

final class Module implements ModuleInterface
{
    public function slug(): string  { return 'single-product-addons'; }
    public function label(): string { return __('Single Product Addons', 'infoplus-suite'); }

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontend']);
        
        remove_action('woocommerce_after_single_product', [$this, 'outputVariationsJson']);
        add_action('woocommerce_after_single_product', [$this, 'outputProductContextJson'], 5);

        add_action('wp_head', [$this, 'injectArrowSvgUrl']);
        add_action('wp_head', [$this, 'injectEditorSettings']);
        
        // Register REST API endpoints for file uploads
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        
        // Pass REST URL and nonce to frontend
        add_action('wp_head', [$this, 'injectUploadSettings']);
        
        // Cart integration hooks
        add_filter('woocommerce_add_cart_item_data', [$this, 'attachFilesToCart'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'displayFilesInCart'], 10, 2);
        
        // Checkout and order hooks
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'saveFilesToOrderItem'], 10, 4);
        
        // Admin order display
        add_action('woocommerce_after_order_itemmeta', [$this, 'displayFilesInAdminOrder'], 10, 3);
        
        // Enqueue admin styles
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminStyles']);
        
        // Cleanup old transients daily
        add_action('wp_scheduled_delete', [$this, 'cleanupOldTransients']);
        
        // AJAX add to cart handler (for buyable order type button)
        add_action('wp_ajax_infoplus_add_to_cart', [$this, 'ajaxAddToCart']);
        add_action('wp_ajax_nopriv_infoplus_add_to_cart', [$this, 'ajaxAddToCart']);

        // Buy form of the classic theme: a standard WooCommerce add-to-cart POST
        // (WC_Form_Handler::add_to_cart_action) marked with the field infoplus_buy_form.
        // Both callbacks return early for every other add-to-cart request.
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validateBuyForm'], 20, 5);
        add_filter('woocommerce_add_cart_item_data', [$this, 'addBuyFormCartItemData'], 20, 3);

        // Server-side Mindestbestellmenge (replaces the BeRocket Min/Max plugin).
        (new MinQuantity())->register();

        // Show model image in cart/checkout instead of product image
        add_filter('woocommerce_cart_item_thumbnail', [$this, 'cartItemModelImage'], 10, 3);
        
        // Show model image in order details/emails
        add_filter('woocommerce_order_item_thumbnail', [$this, 'orderItemModelImage'], 10, 2);
    }

    /** Build a URL to an asset within this module */
    private function assetUrl(string $path): string
    {
        return rtrim(INFOPLUS_SUITE_URL, '/')
            . '/src/Modules/WooSingleProductAddons/Assets/'
            . ltrim($path, '/');
    }

    public function enqueueFrontend(): void
    {
        if (!is_product()) return;

        $ver = Plugin::VERSION;

        wp_enqueue_script('woo-variation-filter',      $this->assetUrl('variation-filter.js'),         ['jquery'], $ver, true);
        wp_enqueue_script('elementor-bulk-calculator', $this->assetUrl('elementor-bulk-calculator.js'),['jquery'], $ver, true);
        wp_enqueue_script('custom-model-dropdown',     $this->assetUrl('custom-model-dropdown.js'),    ['jquery'], $ver, true);
        wp_enqueue_script('variation-editor-link',     $this->assetUrl('variation-editor-link.js'),    ['jquery', 'infoplus-product-state'], $ver, true);
        wp_enqueue_script('sonderkontur-upload-toggle', $this->assetUrl('sonderkontur-upload-toggle.js'), ['jquery'], $ver, true);
        wp_enqueue_style('sonderkontur-upload-styles', $this->assetUrl('sonderkontur-upload.css'), [], $ver);
    }

    /**
     * Enqueue admin styles for order display
     */
    public function enqueueAdminStyles($hook): void
    {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }
        
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'shop_order') {
            return;
        }
        
        wp_enqueue_style('sonderkontur-admin', $this->assetUrl('sonderkontur-admin.css'), [], Plugin::VERSION);
    }

    /**
     * Get unique storage key for current user/session
     * Uses cookie if available, otherwise creates a temporary key
     */
    private function getStorageKey(): string
    {
        // Try to get WooCommerce session cookie
        if (!empty($_COOKIE['wp_woocommerce_session_' . COOKIEHASH])) {
            $cookie = $_COOKIE['wp_woocommerce_session_' . COOKIEHASH];
            $customer_id = explode('||', $cookie)[0] ?? '';
            if ($customer_id) {
                return 'sonderkontur_' . $customer_id;
            }
        }
        
        // Fallback: use IP + user agent hash (not perfect but works for upload session)
        $unique = md5($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
        return 'sonderkontur_' . $unique;
    }

    /**
     * Get uploaded files from storage
     */
    private function getUploadedFiles(): array
    {
        $key = $this->getStorageKey();
        $files = get_transient($key);
        return is_array($files) ? $files : [];
    }

    /**
     * Save uploaded files to storage
     */
    private function saveUploadedFiles(array $files): bool
    {
        $key = $this->getStorageKey();
        // Store for 24 hours
        return set_transient($key, $files, DAY_IN_SECONDS);
    }

    /**
     * Clear uploaded files from storage
     */
    private function clearUploadedFiles(): bool
    {
        $key = $this->getStorageKey();
        return delete_transient($key);
    }

    /**
     * Cleanup old transients (runs daily)
     */
    public function cleanupOldTransients(): void
    {
        global $wpdb;
        
        // Delete transients older than 2 days
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} 
                WHERE option_name LIKE %s 
                AND option_value < %d",
                '_transient_timeout_sonderkontur_%',
                time() - (2 * DAY_IN_SECONDS)
            )
        );
    }

    /**
     * Inject upload settings (REST URL and nonce) into wp_head
     */
    public function injectUploadSettings(): void
    {
        if (!is_product()) return;
        
        echo '<script>';
        echo 'window.infoplusSonderkonturUpload = ' . wp_json_encode([
            'restUrl' => rest_url('infoplus/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'storageKey' => $this->getStorageKey(), // Send key to frontend for debugging
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';';
        echo '</script>';
    }

    /**
     * Register REST API endpoints
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('infoplus/v1', '/sonderkontur/upload', [
            'methods' => 'POST',
            'callback' => [$this, 'handleFileUpload'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('infoplus/v1', '/sonderkontur/files', [
            'methods' => 'GET',
            'callback' => [$this, 'getUploadedFilesEndpoint'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('infoplus/v1', '/sonderkontur/files/(?P<index>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'deleteUploadedFile'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('infoplus/v1', '/sonderkontur/clear', [
            'methods' => 'POST',
            'callback' => [$this, 'clearUploadedFilesEndpoint'],
            'permission_callback' => '__return_true',
        ]);

        // Note: Bulk pricing endpoint /products/prices?sku=XXX is provided by
        // Advanced Dynamic Pricing for WooCommerce (AlgolPlus) plugin
        // We use their endpoint directly instead of registering our own
    }

    /**
     * Handle file upload
     */
    public function handleFileUpload(\WP_REST_Request $request): \WP_REST_Response
    {
        if (empty($_FILES) || !isset($_FILES['files'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('No files uploaded', 'infoplus-suite'),
            ], 400);
        }

        $files = $_FILES['files'];
        $uploaded_files = $this->getUploadedFiles();

        $allowed_types = [
            'application/pdf',
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'audio/mpeg',
            'video/mp4',
        ];

        $max_size = 38 * 1024 * 1024;
        $uploaded_count = 0;
        $errors = [];

        if (is_array($files['name'])) {
            $file_count = count($files['name']);
            
            for ($i = 0; $i < $file_count; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    $errors[] = sprintf(__('Failed to upload %s', 'infoplus-suite'), $files['name'][$i]);
                    continue;
                }

                if (!in_array($files['type'][$i], $allowed_types)) {
                    $errors[] = sprintf(__('File %s has invalid type', 'infoplus-suite'), $files['name'][$i]);
                    continue;
                }

                if ($files['size'][$i] > $max_size) {
                    $errors[] = sprintf(__('File %s is too large', 'infoplus-suite'), $files['name'][$i]);
                    continue;
                }

                $temp_file = wp_tempnam($files['name'][$i]);
                
                if (!move_uploaded_file($files['tmp_name'][$i], $temp_file)) {
                    $errors[] = sprintf(__('Failed to save file %s', 'infoplus-suite'), $files['name'][$i]);
                    continue;
                }

                $uploaded_files[] = [
                    'name' => sanitize_file_name($files['name'][$i]),
                    'type' => $files['type'][$i],
                    'size' => $files['size'][$i],
                    'size_formatted' => size_format($files['size'][$i], 2),
                    'temp_path' => $temp_file,
                    'uploaded_at' => current_time('mysql'),
                ];

                $uploaded_count++;
            }
        } else {
            if ($files['error'] !== UPLOAD_ERR_OK) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('Upload error', 'infoplus-suite'),
                ], 400);
            }

            if (!in_array($files['type'], $allowed_types)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('Invalid file type', 'infoplus-suite'),
                ], 400);
            }

            if ($files['size'] > $max_size) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('File is too large', 'infoplus-suite'),
                ], 400);
            }

            $temp_file = wp_tempnam($files['name']);
            
            if (!move_uploaded_file($files['tmp_name'], $temp_file)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('Failed to save file', 'infoplus-suite'),
                ], 500);
            }

            $uploaded_files[] = [
                'name' => sanitize_file_name($files['name']),
                'type' => $files['type'],
                'size' => $files['size'],
                'size_formatted' => size_format($files['size'], 2),
                'temp_path' => $temp_file,
                'uploaded_at' => current_time('mysql'),
            ];

            $uploaded_count++;
        }

        $this->saveUploadedFiles($uploaded_files);

        $response = [
            'success' => true,
            'message' => sprintf(_n('%d file uploaded', '%d files uploaded', $uploaded_count, 'infoplus-suite'), $uploaded_count),
            'uploaded_count' => $uploaded_count,
            'total_files' => count($uploaded_files),
            'files' => $uploaded_files,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return new \WP_REST_Response($response, 200);
    }

    /**
     * Get uploaded files endpoint
     */
    public function getUploadedFilesEndpoint(\WP_REST_Request $request): \WP_REST_Response
    {
        $files = $this->getUploadedFiles();

        return new \WP_REST_Response([
            'success' => true,
            'files' => $files,
            'count' => count($files),
        ], 200);
    }

    /**
     * Delete a specific uploaded file
     */
    public function deleteUploadedFile(\WP_REST_Request $request): \WP_REST_Response
    {
        $index = (int) $request->get_param('index');
        $files = $this->getUploadedFiles();

        if (!isset($files[$index])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('File not found', 'infoplus-suite'),
            ], 404);
        }

        if (isset($files[$index]['temp_path']) && file_exists($files[$index]['temp_path'])) {
            @unlink($files[$index]['temp_path']);
        }

        array_splice($files, $index, 1);
        $this->saveUploadedFiles($files);

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('File deleted', 'infoplus-suite'),
            'remaining_count' => count($files),
        ], 200);
    }

    /**
     * Clear all uploaded files endpoint
     */
    public function clearUploadedFilesEndpoint(\WP_REST_Request $request): \WP_REST_Response
    {
        $files = $this->getUploadedFiles();

        foreach ($files as $file) {
            if (isset($file['temp_path']) && file_exists($file['temp_path'])) {
                @unlink($file['temp_path']);
            }
        }

        $this->clearUploadedFiles();

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('All files cleared', 'infoplus-suite'),
        ], 200);
    }

    /**
     * Attach uploaded files to cart
     */
    public function attachFilesToCart($cart_item_data, $product_id, $variation_id)
    {
        $files = $this->getUploadedFiles();
        
        if (empty($files)) {
            return $cart_item_data;
        }

        // Move files from temp to permanent location
        $upload_dir = wp_upload_dir();
        $sonderkontur_dir = $upload_dir['basedir'] . '/sonderkontur-uploads';
        
        if (!file_exists($sonderkontur_dir)) {
            wp_mkdir_p($sonderkontur_dir);
        }

        $permanent_files = [];
        
        foreach ($files as $file) {
            if (!isset($file['temp_path']) || !file_exists($file['temp_path'])) {
                continue;
            }

            $filename = time() . '_' . wp_generate_password(8, false) . '_' . $file['name'];
            $permanent_path = $sonderkontur_dir . '/' . $filename;
            
            if (copy($file['temp_path'], $permanent_path)) {
                @unlink($file['temp_path']);
                
                $permanent_files[] = [
                    'name' => $file['name'],
                    'type' => $file['type'],
                    'size' => $file['size'],
                    'size_formatted' => $file['size_formatted'],
                    'path' => $permanent_path,
                    'url' => $upload_dir['baseurl'] . '/sonderkontur-uploads/' . $filename,
                    'uploaded_at' => $file['uploaded_at'],
                ];
            }
        }

        if (!empty($permanent_files)) {
            $cart_item_data['sonderkontur_files'] = $permanent_files;
            $this->clearUploadedFiles();
        }

        return $cart_item_data;
    }

    /**
     * Display uploaded files in cart
     */
    public function displayFilesInCart($item_data, $cart_item)
    {
        if (empty($cart_item['sonderkontur_files'])) {
            return $item_data;
        }

        $files = $cart_item['sonderkontur_files'];
        $file_count = count($files);
        
        $item_data[] = [
            'key' => __('Sonderkontur Dateien', 'infoplus-suite'),
            'value' => sprintf(_n('%d Datei hochgeladen', '%d Dateien hochgeladen', $file_count, 'infoplus-suite'), $file_count),
            'display' => '',
        ];

        foreach ($files as $file) {
            $item_data[] = [
                'key' => '',
                'value' => '📎 ' . esc_html($file['name']) . ' (' . $file['size_formatted'] . ')',
                'display' => '',
            ];
        }

        return $item_data;
    }

    /**
     * Save files to order item meta
     */
    public function saveFilesToOrderItem($item, $cart_item_key, $values, $order)
    {
        if (!empty($values['sonderkontur_files'])) {
            $item->add_meta_data('_sonderkontur_files', $values['sonderkontur_files'], true);
        }

        // Persist model image URL to order item for thank-you page & order details
        if (!empty($values['_infoplus_model_image_url'])) {
            $item->add_meta_data('_infoplus_model_image_url', $values['_infoplus_model_image_url'], true);
        }
    }

    /**
     * Display files in admin order page
     */
    public function displayFilesInAdminOrder($item_id, $item, $product)
    {
        $files = $item->get_meta('_sonderkontur_files');
        
        if (empty($files)) {
            return;
        }

        echo '<div class="sonderkontur-files-admin">';
        echo '<h4>' . __('Sonderkontur Dateien:', 'infoplus-suite') . '</h4>';
        echo '<ul class="sonderkontur-file-list">';
        
        foreach ($files as $file) {
            $file_exists = isset($file['path']) && file_exists($file['path']);
            $icon = $this->getFileIcon($file['type']);
            
            echo '<li class="sonderkontur-file-item">';
            echo '<span class="file-icon">' . $icon . '</span>';
            echo '<span class="file-name">' . esc_html($file['name']) . '</span>';
            echo '<span class="file-size">(' . $file['size_formatted'] . ')</span>';
            
            if ($file_exists && isset($file['url'])) {
                echo '<a href="' . esc_url($file['url']) . '" class="button button-small" target="_blank">';
                echo __('Download', 'infoplus-suite');
                echo '</a>';
            } else {
                echo '<span class="file-missing">' . __('File missing', 'infoplus-suite') . '</span>';
            }
            
            echo '</li>';
        }
        
        echo '</ul>';
        echo '</div>';
    }

    /**
     * Get appropriate icon for file type
     */
    private function getFileIcon($mime_type): string
    {
        if (strpos($mime_type, 'image/') === 0) {
            return '🖼️';
        } elseif (strpos($mime_type, 'application/pdf') === 0) {
            return '📄';
        } elseif (strpos($mime_type, 'audio/') === 0) {
            return '🎵';
        } elseif (strpos($mime_type, 'video/') === 0) {
            return '🎬';
        } else {
            return '📎';
        }
    }

    /**
     * Output product context JSON: window.currentProduct, window.allWooVariations
     * and window.currentProductImage.
     *
     * The variation list (model, format, befestigung, oberflaeche, variation_id per
     * child) is printed once as window.allWooVariations; window.currentProduct.variations
     * points at the same array. Before 0.10.26 the list was encoded twice, which made
     * up about 390 KB of the Atlantis page (1380 variations).
     *
     * Readers (checked 2026-10-20 on staging and in this repo): variation-filter.js
     * reads window.allWooVariations, and only when a form.variations_form is on the
     * page. Nothing reads window.currentProduct.variations. elementor-bulk-calculator.js
     * reads currentProduct.id and currentProduct.mindestanzahl.
     *
     * Filter infoplus_suite_product_context_variations (bool, default true, args
     * $include and WC_Product): return false to skip the query and print an empty
     * list. A theme that renders no form.variations_form can do that safely.
     */
    public function outputProductContextJson(): void
    {
        if (!is_product()) return;

        $product_id = (int) get_queried_object_id();
        if (!$product_id) return;

        $product = wc_get_product($product_id);
        if (!$product) return;

        $terms = get_the_terms($product_id, 'product_cat');
        $categories = [];
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $t) {
                $categories[] = [
                    'id'     => (int) $t->term_id,
                    'slug'   => (string) $t->slug,
                    'name'   => (string) $t->name,
                    'parent' => (int) $t->parent,
                    'url'    => get_term_link($t),
                ];
            }
        }

        $primary_category = null;
        if (class_exists('\WPSEO_Primary_Term')) {
            $wpseo_primary = new \WPSEO_Primary_Term('product_cat', $product_id);
            $primary_id = (int) $wpseo_primary->get_primary_term();
            if ($primary_id > 0) {
                $pt = get_term($primary_id, 'product_cat');
                if ($pt && !is_wp_error($pt)) {
                    $primary_category = [
                        'id'     => (int) $pt->term_id,
                        'slug'   => (string) $pt->slug,
                        'name'   => (string) $pt->name,
                        'parent' => (int) $pt->parent,
                        'url'    => get_term_link($pt),
                    ];
                }
            }
        }

        $includeVariations = (bool) apply_filters('infoplus_suite_product_context_variations', true, $product);

        $variations = [];
        if ($includeVariations && $product->is_type('variable')) {
            global $wpdb;
            $children = $product->get_children();

            if (!empty($children)) {
                $ids_placeholder = implode(',', array_map('intval', $children));
                $attr_keys = ['attribute_pa_model', 'attribute_pa_format', 'attribute_pa_befestigung', 'attribute_pa_oberflache'];
                $attr_keys_sql = "'" . implode("','", $attr_keys) . "'";

                $rows = $wpdb->get_results(
                    "SELECT post_id, meta_key, meta_value
                     FROM {$wpdb->postmeta}
                     WHERE post_id IN ({$ids_placeholder})
                       AND meta_key IN ({$attr_keys_sql})
                     ORDER BY post_id"
                );

                // Group by post_id
                $attr_map = [];
                foreach ($rows as $row) {
                    $attr_map[$row->post_id][$row->meta_key] = $row->meta_value;
                }

                foreach ($children as $child_id) {
                    $attrs = $attr_map[$child_id] ?? [];
                    $variations[] = [
                        'model'        => $attrs['attribute_pa_model'] ?? '',
                        'format'       => $attrs['attribute_pa_format'] ?? '',
                        'befestigung'  => $attrs['attribute_pa_befestigung'] ?? '',
                        'oberflaeche'  => $attrs['attribute_pa_oberflache'] ?? '',
                        'variation_id' => $child_id,
                    ];
                }
            }
        }

        $imageUrl = wp_get_attachment_url($product->get_image_id());

        // ACF Mindestanzahl (minimum order quantity)
        $mindestanzahl = (int) get_post_meta($product_id, 'mindestanzahl', true);
        if ($mindestanzahl < 1) $mindestanzahl = 1;

        $payload = [
            'id'               => $product_id,
            'sku'              => (string) $product->get_sku(),
            'name'             => (string) $product->get_name(),
            'image'            => $imageUrl ?: '',
            'categories'       => $categories,
            'primary_category' => $primary_category,
            'variations'       => [], // Set below to the same array as window.allWooVariations.
            'mindestanzahl'    => $mindestanzahl,
        ];

        echo '<script>';
        echo 'window.allWooVariations = ' . wp_json_encode($variations, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';';
        echo 'window.currentProduct = ' . wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';';
        echo 'window.currentProduct.variations = window.allWooVariations;';
        echo 'window.currentProductImage = ' . wp_json_encode($imageUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';';
        echo '</script>';
    }

    /**
     * Inject editor settings into wp_head
     */
    public function injectEditorSettings(): void
    {
        if (!is_product()) return;

        $product_id = (int) get_queried_object_id();
        if (!$product_id) return;

        $productUuid = get_post_meta($product_id, '_ife_uuid', true);
        $productUrl = get_permalink($product_id);

        $baseUrl = '';
        $themeId = '';
        $cartEntryPath = '';
        $backPath = '';
        
        if (class_exists('\Infoplus\Suite\Modules\EditorLinkSettings\Module')) {
            $baseUrl = \Infoplus\Suite\Modules\EditorLinkSettings\Module::getBaseUrl();
            $themeId = \Infoplus\Suite\Modules\EditorLinkSettings\Module::getThemeId();
            $cartEntryPath = \Infoplus\Suite\Modules\EditorLinkSettings\Module::getCartEntryPath();
            
            $backPathTemplate = \Infoplus\Suite\Modules\EditorLinkSettings\Module::getBackPath();
            $backPath = str_replace('{product_url}', $productUrl, $backPathTemplate);
        }

        echo '<script>';
        echo 'window.infoplusEditorSettings = ' . wp_json_encode([
            'baseUrl'       => $baseUrl,
            'themeId'       => $themeId,
            'productUuid'   => $productUuid ?: '',
            'cartEntryPath' => $cartEntryPath,
            'backPath'      => $backPath,
            'productUrl'    => $productUrl,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';';
        echo '</script>';
    }

    public function outputVariationsJson(): void {}

    public function injectArrowSvgUrl(): void
    {
        if (!is_product()) return;
        $arrowUrl = $this->assetUrl('assets/arrow.svg');
        echo '<script>window.arrowSvgUrl = ' . wp_json_encode($arrowUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';</script>';
    }

    /**
     * Lightweight AJAX add-to-cart handler for variable products.
     * Uses WC()->cart->add_to_cart() directly — no page rendering overhead.
     */
    public function ajaxAddToCart(): void
    {
        $product_id   = absint($_POST['product_id'] ?? 0);
        $variation_id = absint($_POST['variation_id'] ?? 0);
        $quantity     = max(1, absint($_POST['quantity'] ?? 1));

        if (!$product_id || !$variation_id) {
            wp_send_json_error(['message' => 'Missing product or variation ID'], 400);
        }

        // This handler calls WC()->cart->add_to_cart() directly, which skips
        // woocommerce_add_to_cart_validation, so the minimum is checked here.
        $min_qty = MinQuantity::forProduct($product_id, $variation_id);
        if ($quantity < $min_qty) {
            wp_send_json_error([
                'message' => sprintf(__('Die Mindestbestellmenge beträgt %d Stück.', 'infoplus-suite'), $min_qty),
                'min'     => $min_qty,
            ], 400);
        }

        // Build variation attributes from the variation
        $variation = wc_get_product($variation_id);
        $variation_attrs = $variation ? $variation->get_variation_attributes() : [];

        // Store model image URL in cart item data
        $model_image_url = esc_url_raw($_POST['model_image_url'] ?? '');
        $cart_item_data = [];
        if ($model_image_url) {
            $cart_item_data['_infoplus_model_image_url'] = $model_image_url;
        }

        $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation_attrs, $cart_item_data);

        if ($cart_item_key) {
            wp_send_json_success([
                'cart_item_key' => $cart_item_key,
                'cart_count'    => WC()->cart->get_cart_contents_count(),
            ]);
        } else {
            wp_send_json_error(['message' => 'Fehler beim Hinzufügen zum Warenkorb'], 400);
        }
    }

    /**
     * POST field that marks the classic theme's buy form (template-parts/product/actions.php
     * in the infoplus theme). The form posts add-to-cart, product_id, variation_id,
     * attribute_pa_* and quantity to the product permalink, so WooCommerce's own form
     * handler adds the line: validation filters, cart item data filters of every plugin,
     * notices and the "redirect to cart" setting all apply.
     */
    public const BUY_FORM_FIELD = 'infoplus_buy_form';

    /** True only for an add-to-cart POST from the theme's buy form (never for admin-ajax). */
    private function isBuyFormRequest(): bool
    {
        if (wp_doing_ajax()) {
            return false;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce's add-to-cart form has no nonce either.
        return !empty($_POST[self::BUY_FORM_FIELD]) && isset($_POST['add-to-cart']);
    }

    /**
     * Model (pa_model term slug) of the line being added: the variation's own value,
     * else the posted attribute_pa_model (variations set to "any" model).
     */
    private function buyFormModelSlug(int $variation_id): string
    {
        $slug      = '';
        $variation = $variation_id ? wc_get_product($variation_id) : null;
        if ($variation && $variation->is_type('variation')) {
            $attrs = $variation->get_variation_attributes();
            $slug  = (string) ($attrs['attribute_pa_model'] ?? '');
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ($slug === '' && isset($_POST['attribute_pa_model']) && is_scalar($_POST['attribute_pa_model'])) {
            $slug = sanitize_title(wp_unslash((string) $_POST['attribute_pa_model']));
        }
        return $slug;
    }

    /**
     * Order type of a model on a product, as the product page shows it: the
     * Attribute Overrides value (_infoplus_attr_order_type_model_{slug}), plus the
     * Sonderkontur fallback of variation-editor-link.js (an "editable" model whose
     * slug contains "sonderkontur" is treated as "buyable").
     */
    public static function resolveOrderType(int $product_id, string $model_slug): string
    {
        $class = '\Infoplus\Suite\Modules\ProductDetailSuite\Fields\ProductAttributeImages';
        if ($model_slug === '' || !class_exists($class)) {
            return 'editable';
        }
        $type = $class::getTermOrderType($product_id, 'pa_model', $model_slug);
        if ($type === 'editable' && stripos($model_slug, 'sonderkontur') !== false) {
            $type = 'buyable';
        }
        return $type;
    }

    /** First gallery image of the model, the same URL as infoplusProductData.models[].image_url. */
    private static function modelImageUrl(int $product_id, string $model_slug): string
    {
        $class = '\Infoplus\Suite\Modules\ProductDetailSuite\Fields\ProductAttributeImages';
        if ($model_slug === '' || !class_exists($class)) {
            return '';
        }
        $gallery = $class::getTermGalleryUrls($product_id, 'pa_model', $model_slug);
        return !empty($gallery[0]['url']) ? (string) $gallery[0]['url'] : '';
    }

    /**
     * woocommerce_add_to_cart_validation (priority 20, after the plugins at 10).
     * Buy form only: a model whose order type is not "buyable" cannot be put into the
     * cart from the product page (the page shows no cart button for it). Products
     * without a model attribute are not checked. Filter
     * infoplus_suite_buy_form_check_order_type (bool, default true) turns the check off.
     */
    public function validateBuyForm($passed, $product_id, $quantity, $variation_id = 0, $variations = [])
    {
        if (!$passed || !$this->isBuyFormRequest() || !$variation_id) {
            return $passed; // No variation: WooCommerce adds its own "choose product options" notice.
        }
        if (!apply_filters('infoplus_suite_buy_form_check_order_type', true, (int) $product_id, (int) $variation_id)) {
            return $passed;
        }

        $product_id = (int) $product_id;
        $model_slug = $this->buyFormModelSlug((int) $variation_id);
        if ($model_slug === '') {
            return $passed;
        }

        $type = self::resolveOrderType($product_id, $model_slug);
        if ($type !== 'buyable') {
            $term = get_term_by('slug', $model_slug, 'pa_model');
            $name = $term ? $term->name : $model_slug;
            wc_add_notice(
                sprintf(
                    /* translators: %s: model name */
                    __('Das Modell „%s“ kann nicht direkt in den Warenkorb gelegt werden. Bitte gestalten Sie es im Editor oder senden Sie uns eine Anfrage.', 'infoplus-suite'),
                    esc_html($name)
                ),
                'error'
            );
            return false;
        }

        return $passed;
    }

    /**
     * woocommerce_add_cart_item_data (priority 20). Buy form only: adds what the old
     * admin-ajax handler (ajaxAddToCart) put into the cart line and WooCommerce's own
     * form handler does not, the model image (_infoplus_model_image_url). The URL is
     * resolved on the server from the model of the variation, not taken from the
     * request. Sonderkontur files are attached by attachFilesToCart() on the same
     * filter for both paths, so nothing else is needed here.
     */
    public function addBuyFormCartItemData($cart_item_data, $product_id, $variation_id)
    {
        if (!$this->isBuyFormRequest() || !is_array($cart_item_data) || !empty($cart_item_data['_infoplus_model_image_url'])) {
            return $cart_item_data;
        }
        $url = self::modelImageUrl((int) $product_id, $this->buyFormModelSlug((int) $variation_id));
        if ($url !== '') {
            $cart_item_data['_infoplus_model_image_url'] = esc_url_raw($url);
        }
        return $cart_item_data;
    }

    /**
     * Replace cart item thumbnail with model image when available.
     */
    public function cartItemModelImage(string $thumbnail, array $cart_item, string $cart_item_key): string
    {
        if (!empty($cart_item['_infoplus_model_image_url'])) {
            $url = esc_url($cart_item['_infoplus_model_image_url']);
            $alt = '';
            if (!empty($cart_item['data'])) {
                $alt = esc_attr($cart_item['data']->get_name());
            }
            return '<img src="' . $url . '" alt="' . $alt . '" class="attachment-woocommerce_thumbnail" />';
        }
        return $thumbnail;
    }

    /**
     * Replace order item thumbnail with model image (order received, emails).
     */
    public function orderItemModelImage(string $thumbnail, $item): string
    {
        $model_image_url = $item->get_meta('_infoplus_model_image_url');
        if ($model_image_url) {
            $url = esc_url($model_image_url);
            $alt = esc_attr($item->get_name());
            return '<img src="' . $url . '" alt="' . $alt . '" class="attachment-woocommerce_thumbnail" style="max-width:100%;height:auto;" />';
        }
        return $thumbnail;
    }
}
