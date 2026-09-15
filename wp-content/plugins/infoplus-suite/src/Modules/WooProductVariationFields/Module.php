<?php
namespace Infoplus\Suite\Modules\WooProductVariationFields;

use Infoplus\Suite\Contracts\ModuleInterface;
use Infoplus\Suite\Plugin;

if (!defined('ABSPATH')) exit;

final class Module implements ModuleInterface
{
    public function slug(): string  { return 'product-variation-fields'; }
    public function label(): string { return __('Product & Variation Fields', 'infoplus-suite'); }

    public function register(): void
    {
        // Save product fields on product save (backup save — primary save is in ProductAttributeImages::saveMetaBox)
        add_action('woocommerce_process_product_meta', [$this, 'saveProductFields']);

        // REST: expose product fields on product endpoint
        add_filter('woocommerce_rest_prepare_product_object', [$this, 'exposeFieldsToRest'], 10, 3);
    }

    public function saveProductFields(int $post_id): void
    {
        // Admin-only fields: only save if current user has manage_options capability
        if (current_user_can('manage_options')) {
            if (isset($_POST['ife_uuid'])) {
                update_post_meta($post_id, '_ife_uuid', sanitize_text_field($_POST['ife_uuid']));
            }
            if (isset($_POST['ife_unique_text_name'])) {
                update_post_meta($post_id, '_ife_unique_text_name', sanitize_text_field($_POST['ife_unique_text_name']));
            }
            if (isset($_POST['ife_print_api_id'])) {
                update_post_meta($post_id, '_ife_print_api_id', sanitize_text_field($_POST['ife_print_api_id']));
            }
        }
        if (isset($_POST['ife_name_2'])) {
            update_post_meta($post_id, '_ife_name_2', sanitize_text_field($_POST['ife_name_2']));
        }
        if (isset($_POST['ife_ife_image'])) {
            update_post_meta($post_id, '_ife_ife_image', esc_url_raw($_POST['ife_ife_image']));
        }
        if (isset($_POST['ife_preview_images'])) {
            $val = json_decode(stripslashes((string) $_POST['ife_preview_images']), true);
            $val = is_array($val) ? array_values(array_filter(array_map('esc_url_raw', $val))) : [];
            update_post_meta($post_id, '_ife_preview_images', $val);
        }
        if (isset($_POST['ife_template_zip'])) {
            update_post_meta($post_id, '_ife_template_zip', esc_url_raw($_POST['ife_template_zip']));
        }

        if (isset($_POST['infoplus_min_quantity'])) {
            $min_qty = absint($_POST['infoplus_min_quantity']);
            if ($min_qty > 0) {
                update_post_meta($post_id, '_infoplus_min_quantity', $min_qty);
            } else {
                delete_post_meta($post_id, '_infoplus_min_quantity');
            }
        }
        if (isset($_POST['infoplus_delivery_days'])) {
            $delivery = absint($_POST['infoplus_delivery_days']);
            if ($delivery > 0) {
                update_post_meta($post_id, '_infoplus_delivery_days', $delivery);
            } else {
                delete_post_meta($post_id, '_infoplus_delivery_days');
            }
        }

        // Cleanup: remove old fields we no longer use
        delete_post_meta($post_id, '_ife_product_bg_image');
        delete_post_meta($post_id, '_ife_overlay_frame_image');
    }

    public function exposeFieldsToRest($response, $product, $request)
    {
        $pid = $product->get_id();

        $fields = [
            'ife_uuid'             => get_post_meta($pid, '_ife_uuid', true),
            'ife_print_api_id'     => get_post_meta($pid, '_ife_print_api_id', true),
            'ife_unique_text_name' => get_post_meta($pid, '_ife_unique_text_name', true),
            'ife_name_2'           => get_post_meta($pid, '_ife_name_2', true),
            'ife_ife_image'        => get_post_meta($pid, '_ife_ife_image', true),
            'ife_preview_images'   => get_post_meta($pid, '_ife_preview_images', true),
            'ife_template_zip'     => get_post_meta($pid, '_ife_template_zip', true),
        ];

        // Ensure preview images is an array in REST
        if (!empty($fields['ife_preview_images']) && !is_array($fields['ife_preview_images'])) {
            $fields['ife_preview_images'] = [];
        }

        foreach ($fields as $key => $value) {
            if ($value !== '' && $value !== [] && $value !== null) {
                $response->data[$key] = $value;
            }
        }
        return $response;
    }
}
