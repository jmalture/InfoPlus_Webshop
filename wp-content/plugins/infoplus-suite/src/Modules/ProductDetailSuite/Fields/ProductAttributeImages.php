<?php
/**
 * Product Attribute Images Handler
 *
 * Consolidated "Infoplus Suite" meta box for the product edit page.
 * Contains three top-level tabs:
 *   1. Attribute Overrides  — per-product term image/tooltip/modal overrides
 *   2. IBE/IFE              — UUID, Print API, min quantity, delivery days
 *   3. Default Variation    — cascading dropdowns to set the pre-selected variation
 *
 * @package Infoplus\Suite\Modules\ProductDetailSuite\Fields
 * @since 1.0.0
 */

namespace Infoplus\Suite\Modules\ProductDetailSuite\Fields;

if (!defined('ABSPATH')) exit;

class ProductAttributeImages
{
    /**
     * Meta key prefixes for storing product-level attribute overrides
     * Format: _infoplus_attr_{type}_{attribute_slug}_{term_slug}
     */
    private const META_KEY_IMAGE = '_infoplus_attr_image_';
    private const META_KEY_TOOLTIP = '_infoplus_attr_tooltip_';
    private const META_KEY_MODAL = '_infoplus_attr_modal_';

    /**
     * Meta key prefixes for "hide/clear" flags
     * When set to '1', the global value is hidden for this product
     */
    private const META_KEY_HIDE_IMAGE = '_infoplus_attr_hide_image_';
    private const META_KEY_HIDE_TOOLTIP = '_infoplus_attr_hide_tooltip_';
    private const META_KEY_HIDE_MODAL = '_infoplus_attr_hide_modal_';

    /**
     * Meta key prefix for per-product model gallery (comma-separated attachment IDs)
     * Only used for pa_model attribute terms
     */
    private const META_KEY_GALLERY = '_infoplus_attr_gallery_';

    /**
     * Meta key prefix for per-model order type (editable | requestable | buyable)
     * Only used for pa_model attribute terms
     */
    private const META_KEY_ORDER_TYPE = '_infoplus_attr_order_type_';

    /**
     * Meta key for the pre-selected default variation ID (legacy — single product default)
     */
    private const META_KEY_DEFAULT_VARIATION = '_infoplus_default_variation_id';

    /**
     * Meta key for per-model default variation IDs (JSON map: model_slug => variation_id)
     */
    private const META_KEY_MODEL_DEFAULT_VARIATIONS = '_infoplus_model_default_variations';

    /**
     * List of product attribute taxonomies to show in the Attribute Overrides tab
     */
    private array $targetAttributes = [
        'pa_model' => 'Model',
        'pa_format' => 'Format',
        'pa_befestigung' => 'Befestigung',
        'pa_oberflache' => 'Oberfläche',
        'pa_farbe' => 'Farbe',
        'pa_flachenfarbe' => 'Flächenfarbe',
        'pa_holzart' => 'Holzart',
        'pa_tragerfarbe' => 'Trägerfarbe',
    ];

    /**
     * Register hooks
     */
    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'addMetaBox']);
        add_action('save_post_product', [$this, 'saveMetaBox'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }

    /**
     * Add the consolidated meta box to product edit page
     */
    public function addMetaBox(): void
    {
        add_meta_box(
            'infoplus_product_attribute_images',
            __('Infoplus Suite', 'infoplus-suite'),
            [$this, 'renderMetaBox'],
            'product',
            'normal',
            'default'
        );
    }

    /**
     * Render the meta box — three-tab "Infoplus Suite" layout
     */
    public function renderMetaBox(\WP_Post $post): void
    {
        $product = wc_get_product($post->ID);

        if (!$product) {
            echo '<p>' . esc_html__('Product not found.', 'infoplus-suite') . '</p>';
            return;
        }

        if (!$product->is_type('variable')) {
            echo '<p>' . esc_html__('These settings only apply to variable products.', 'infoplus-suite') . '</p>';
            return;
        }

        // Nonce for security
        wp_nonce_field('infoplus_product_attr_images', 'infoplus_product_attr_images_nonce');
        ?>
        <div class="infoplus-suite-wrap">

            <!-- Top-level suite tab navigation -->
            <div class="infoplus-suite-tabs">
                <button type="button" class="infoplus-suite-tab is-active" data-suite-panel="attribute-overrides">
                    <?php esc_html_e('Attribute Overrides', 'infoplus-suite'); ?>
                </button>
                <button type="button" class="infoplus-suite-tab" data-suite-panel="ife">
                    <?php esc_html_e('IBE/IFE', 'infoplus-suite'); ?>
                </button>
                <button type="button" class="infoplus-suite-tab" data-suite-panel="default-variation">
                    <?php esc_html_e('Default Variation', 'infoplus-suite'); ?>
                </button>
            </div>

            <div class="infoplus-suite-panels">

                <!-- Tab 1: Attribute Overrides -->
                <div class="infoplus-suite-panel is-active" data-suite-panel="attribute-overrides">
                    <?php $this->renderAttributeOverridesPanel($post->ID, $product); ?>
                </div>

                <!-- Tab 2: IBE/IFE -->
                <div class="infoplus-suite-panel" data-suite-panel="ife">
                    <?php $this->renderIfeTab($post->ID); ?>
                </div>

                <!-- Tab 3: Default Variation -->
                <div class="infoplus-suite-panel" data-suite-panel="default-variation">
                    <?php $this->renderDefaultVariationTab($post->ID, $product); ?>
                </div>

            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Panel renderers
    // -------------------------------------------------------------------------

    /**
     * Render the Attribute Overrides panel (original meta box content)
     */
    private function renderAttributeOverridesPanel(int $product_id, \WC_Product $product): void
    {
        $product_attributes = $product->get_attributes();

        if (empty($product_attributes)) {
            echo '<p>' . esc_html__('This product has no attributes. Add attributes in the "Attributes" tab first.', 'infoplus-suite') . '</p>';
            return;
        }

        // Filter to only show target attributes that this product uses
        $active_attributes = [];
        foreach ($this->targetAttributes as $taxonomy => $label) {
            if (isset($product_attributes[$taxonomy])) {
                $attribute = $product_attributes[$taxonomy];
                if ($attribute->is_taxonomy()) {
                    $terms = wc_get_product_terms($product_id, $taxonomy, ['fields' => 'all']);
                    if (!empty($terms)) {
                        $active_attributes[$taxonomy] = [
                            'label' => $label,
                            'terms' => $terms,
                        ];
                    }
                }
            }
        }

        if (empty($active_attributes)) {
            echo '<p>' . esc_html__('No supported attributes found on this product.', 'infoplus-suite') . '</p>';
            return;
        }

        $this->renderTabs($product_id, $active_attributes);
    }

    /**
     * Render the IBE/IFE fields panel (migrated from WooProductVariationFields tab)
     */
    private function renderIfeTab(int $product_id): void
    {
        $uuid_value        = get_post_meta($product_id, '_ife_uuid', true);
        $unique_name_value = get_post_meta($product_id, '_ife_unique_text_name', true);
        $print_api_value   = get_post_meta($product_id, '_ife_print_api_id', true);
        $name2_value       = get_post_meta($product_id, '_ife_name_2', true);
        $ife_image         = get_post_meta($product_id, '_ife_ife_image', true);
        $preview_images    = get_post_meta($product_id, '_ife_preview_images', true);
        $preview_images    = is_array($preview_images) ? $preview_images : [];
        $template_zip      = get_post_meta($product_id, '_ife_template_zip', true);
        $min_quantity      = get_post_meta($product_id, '_infoplus_min_quantity', true);
        $delivery_days     = get_post_meta($product_id, '_infoplus_delivery_days', true);
        ?>
        <div class="infoplus-ife-panel">

            <!-- UUID (locked) -->
            <p class="form-field infoplus-locked-field">
                <label for="ife_uuid"><?php esc_html_e('UUID', 'infoplus-suite'); ?></label>
                <span class="locked-field-wrap">
                    <input type="text" id="ife_uuid" name="ife_uuid"
                           value="<?php echo esc_attr($uuid_value); ?>"
                           readonly="readonly" style="width:100%;max-width:400px;" />
                    <span class="dashicons dashicons-lock infoplus-lock-icon"></span>
                </span>
                <span class="dashicons dashicons-editor-help woocommerce-help-tip"
                      data-tip="<?php esc_attr_e('This field is managed by the IFE sync system and cannot be edited manually.', 'infoplus-suite'); ?>"></span>
            </p>

            <!-- Einzigart. Name (locked) -->
            <p class="form-field infoplus-locked-field">
                <label for="ife_unique_text_name"><?php esc_html_e('Einzigart. Name', 'infoplus-suite'); ?></label>
                <span class="locked-field-wrap">
                    <input type="text" id="ife_unique_text_name" name="ife_unique_text_name"
                           value="<?php echo esc_attr($unique_name_value); ?>"
                           readonly="readonly" style="width:100%;max-width:400px;" />
                    <span class="dashicons dashicons-lock infoplus-lock-icon"></span>
                </span>
                <span class="dashicons dashicons-editor-help woocommerce-help-tip"
                      data-tip="<?php esc_attr_e('Unique display name used by the editor/system. Managed by IFE sync.', 'infoplus-suite'); ?>"></span>
            </p>

            <!-- Print API ID (locked) -->
            <p class="form-field infoplus-locked-field">
                <label for="ife_print_api_id"><?php esc_html_e('Print API ID', 'infoplus-suite'); ?></label>
                <span class="locked-field-wrap">
                    <input type="text" id="ife_print_api_id" name="ife_print_api_id"
                           value="<?php echo esc_attr($print_api_value); ?>"
                           readonly="readonly" style="width:100%;max-width:400px;" />
                    <span class="dashicons dashicons-lock infoplus-lock-icon"></span>
                </span>
                <span class="dashicons dashicons-editor-help woocommerce-help-tip"
                      data-tip="<?php esc_attr_e('Print API ID for this product. Managed by IFE sync.', 'infoplus-suite'); ?>"></span>
            </p>

            <!-- Hidden fields — preserve data without showing UI -->
            <input type="hidden" name="ife_name_2"        value="<?php echo esc_attr($name2_value); ?>" />
            <input type="hidden" name="ife_ife_image"     value="<?php echo esc_attr($ife_image); ?>" />
            <input type="hidden" name="ife_preview_images" value="<?php echo esc_attr(wp_json_encode($preview_images)); ?>" />
            <input type="hidden" name="ife_template_zip"  value="<?php echo esc_attr($template_zip); ?>" />

            <!-- Mindestanzahl (editable) -->
            <p class="form-field">
                <label for="infoplus_min_quantity"><?php esc_html_e('Mindestanzahl', 'infoplus-suite'); ?></label>
                <input type="number" id="infoplus_min_quantity" name="infoplus_min_quantity"
                       value="<?php echo esc_attr($min_quantity); ?>"
                       min="1" step="1" style="width:100%;max-width:400px;" placeholder="1" />
                <span class="dashicons dashicons-editor-help woocommerce-help-tip"
                      data-tip="<?php esc_attr_e('Minimum order quantity shown in the product order panel.', 'infoplus-suite'); ?>"></span>
            </p>

            <!-- Lieferzeit (editable) -->
            <p class="form-field">
                <label for="infoplus_delivery_days"><?php esc_html_e('Lieferzeit (Arbeitstage)', 'infoplus-suite'); ?></label>
                <input type="number" id="infoplus_delivery_days" name="infoplus_delivery_days"
                       value="<?php echo esc_attr($delivery_days); ?>"
                       min="1" step="1" style="width:100%;max-width:400px;" />
                <span class="dashicons dashicons-editor-help woocommerce-help-tip"
                      data-tip="<?php esc_attr_e('Maximum delivery time in working days shown in the product order panel.', 'infoplus-suite'); ?>"></span>
            </p>

        </div>
        <?php
    }

    /**
     * Render the Default Variation panel — one section per pa_model term.
     * Each section has cascading selects for all non-model variation attributes.
     * The resolved variation ID is stored in _infoplus_model_default_variations JSON map.
     */
    private function renderDefaultVariationTab(int $product_id, \WC_Product $product): void
    {
        // Fetch all pa_model terms assigned to this product
        $model_terms = wc_get_product_terms($product_id, 'pa_model', ['fields' => 'all']);

        if (empty($model_terms)) {
            echo '<p>' . esc_html__('No model (pa_model) attribute terms found on this product.', 'infoplus-suite') . '</p>';
            return;
        }

        // Collect all variation-type attributes EXCEPT pa_model
        $variation_attrs = [];
        foreach ($product->get_attributes() as $attr_slug => $attr) {
            if ($attr_slug === 'pa_model') continue;
            if ($attr->get_variation() && $attr->is_taxonomy()) {
                $terms = wc_get_product_terms($product_id, $attr_slug, ['fields' => 'all']);
                if (!empty($terms)) {
                    $label = $this->targetAttributes[$attr_slug] ?? ucfirst(str_replace('pa_', '', $attr_slug));
                    $variation_attrs[$attr_slug] = [
                        'label' => $label,
                        'terms' => $terms,
                    ];
                }
            }
        }

        if (empty($variation_attrs)) {
            echo '<p>' . esc_html__('No variation-type attributes (other than Model) found on this product.', 'infoplus-suite') . '</p>';
            return;
        }

        // Load saved per-model defaults map
        $saved_raw      = get_post_meta($product_id, self::META_KEY_MODEL_DEFAULT_VARIATIONS, true);
        $saved_defaults = $saved_raw ? json_decode($saved_raw, true) : [];
        if (!is_array($saved_defaults)) {
            $saved_defaults = [];
        }
        ?>
        <div class="infoplus-model-defaults-wrap">

            <p class="description" style="margin-bottom:20px;">
                <?php esc_html_e(
                    'Set a default variation for each model. The first model\'s default is applied on page load; switching to any model applies that model\'s default automatically.',
                    'infoplus-suite'
                ); ?>
            </p>

            <?php foreach ($model_terms as $model_term):
                $model_slug         = $model_term->slug;
                $saved_variation_id = isset($saved_defaults[$model_slug]) ? (int) $saved_defaults[$model_slug] : 0;

                // Validate the stored variation still belongs to this product
                $saved_variation_attrs = [];
                if ($saved_variation_id > 0) {
                    $saved_var = wc_get_product($saved_variation_id);
                    if ($saved_var && $saved_var->get_parent_id() === $product_id) {
                        $saved_variation_attrs = $saved_var->get_variation_attributes();
                    } else {
                        $saved_variation_id = 0;
                    }
                }
            ?>
            <div class="infoplus-model-default-section" data-model-slug="<?php echo esc_attr($model_slug); ?>"
                 style="border:1px solid #e0e0e0;border-radius:4px;padding:16px;margin-bottom:16px;">

                <strong style="display:block;margin-bottom:12px;font-size:13px;">
                    <?php echo esc_html($model_term->name); ?>
                </strong>

                <div class="infoplus-default-attr-rows">
                    <?php foreach ($variation_attrs as $attr_slug => $data):
                        $saved_value = $saved_variation_attrs['attribute_' . $attr_slug] ?? '';
                    ?>
                    <div class="infoplus-default-attr-row">
                        <label><?php echo esc_html($data['label']); ?></label>
                        <select class="infoplus-model-default-attr-select"
                                data-attr="<?php echo esc_attr($attr_slug); ?>">
                            <option value=""><?php esc_html_e('— Select —', 'infoplus-suite'); ?></option>
                            <?php foreach ($data['terms'] as $term): ?>
                                <option value="<?php echo esc_attr($term->slug); ?>"
                                        <?php selected($saved_value, $term->slug); ?>>
                                    <?php echo esc_html($term->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Status badge -->
                <div class="infoplus-model-variation-status" style="margin-top:10px;font-size:12px;">
                    <?php if ($saved_variation_id > 0):
                        $var = wc_get_product($saved_variation_id); ?>
                        <span class="status-found">
                            &#10003; <?php printf(
                                /* translators: 1: variation ID, 2: SKU */
                                esc_html__('Variation #%1$d (SKU: %2$s)', 'infoplus-suite'),
                                $saved_variation_id,
                                esc_html($var ? ($var->get_sku() ?: '—') : '—')
                            ); ?>
                        </span>
                    <?php else: ?>
                        <span class="status-none" style="color:#888;"><?php esc_html_e('No default set', 'infoplus-suite'); ?></span>
                    <?php endif; ?>
                </div>

                <!-- Hidden input — JS writes the resolved variation ID here; PHP reads it on save -->
                <input type="hidden"
                       class="infoplus-model-default-variation-id"
                       name="_infoplus_model_default_variations[<?php echo esc_attr($model_slug); ?>]"
                       value="<?php echo esc_attr($saved_variation_id > 0 ? $saved_variation_id : ''); ?>" />

            </div>
            <?php endforeach; ?>

        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Attribute Overrides tab — inner tabs per attribute
    // -------------------------------------------------------------------------

    /**
     * Render the tabbed interface inside Attribute Overrides panel
     */
    private function renderTabs(int $product_id, array $attributes): void
    {
        $first = true;
        ?>
        <div class="infoplus-attr-images-wrap">
            <div class="infoplus-attr-images-tabs">
                <?php foreach ($attributes as $taxonomy => $data): ?>
                    <button type="button"
                            class="infoplus-attr-tab <?php echo $first ? 'is-active' : ''; ?>"
                            data-tab="<?php echo esc_attr($taxonomy); ?>">
                        <?php echo esc_html($data['label']); ?>
                        <span class="count">(<?php echo count($data['terms']); ?>)</span>
                    </button>
                    <?php $first = false; ?>
                <?php endforeach; ?>
            </div>

            <div class="infoplus-attr-images-panels">
                <?php
                $first = true;
                foreach ($attributes as $taxonomy => $data):
                ?>
                    <div class="infoplus-attr-panel <?php echo $first ? 'is-active' : ''; ?>"
                         data-panel="<?php echo esc_attr($taxonomy); ?>">

                        <p class="description">
                            <?php printf(
                                esc_html__('Customize %s options for this product. These settings override global attribute settings.', 'infoplus-suite'),
                                '<strong>' . esc_html($data['label']) . '</strong>'
                            ); ?>
                        </p>

                        <div class="infoplus-attr-terms-grid">
                            <?php foreach ($data['terms'] as $term):
                                $this->renderTermFields($product_id, $taxonomy, $term);
                            endforeach; ?>
                        </div>
                    </div>
                    <?php $first = false; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render a single term's customization fields (image, tooltip, modal)
     */
    private function renderTermFields(int $product_id, string $taxonomy, \WP_Term $term): void
    {
        $attr_slug = str_replace('pa_', '', $taxonomy);
        $term_key_suffix = $attr_slug . '_' . $term->slug;

        // Meta keys for values
        $image_key   = self::META_KEY_IMAGE   . $term_key_suffix;
        $tooltip_key = self::META_KEY_TOOLTIP . $term_key_suffix;
        $modal_key   = self::META_KEY_MODAL   . $term_key_suffix;

        // Meta keys for hide flags
        $hide_image_key   = self::META_KEY_HIDE_IMAGE   . $term_key_suffix;
        $hide_tooltip_key = self::META_KEY_HIDE_TOOLTIP . $term_key_suffix;
        $hide_modal_key   = self::META_KEY_HIDE_MODAL   . $term_key_suffix;

        // Get saved values
        $image_id     = get_post_meta($product_id, $image_key, true);
        $image_url    = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
        $tooltip      = get_post_meta($product_id, $tooltip_key, true);
        $modal_content = get_post_meta($product_id, $modal_key, true);

        // Get hide flags
        $hide_image   = get_post_meta($product_id, $hide_image_key, true) === '1';
        $hide_tooltip = get_post_meta($product_id, $hide_tooltip_key, true) === '1';
        $hide_modal   = get_post_meta($product_id, $hide_modal_key, true) === '1';
        $hide_all     = $hide_image && $hide_tooltip && $hide_modal;

        // For pa_model: also fetch multi-image gallery IDs
        $gallery_ids_str = '';
        $gallery_ids = [];
        if ($taxonomy === 'pa_model') {
            $gallery_key     = self::META_KEY_GALLERY . $term_key_suffix;
            $gallery_ids_str = get_post_meta($product_id, $gallery_key, true) ?: '';
            $gallery_ids     = $gallery_ids_str
                ? array_values(array_filter(array_map('absint', explode(',', $gallery_ids_str))))
                : [];
        }

        // For pa_model: also fetch order type
        $order_type     = 'editable';
        $order_type_key = '';
        if ($taxonomy === 'pa_model') {
            $order_type_key    = self::META_KEY_ORDER_TYPE . $term_key_suffix;
            $saved_order_type  = get_post_meta($product_id, $order_type_key, true);
            $allowed_order_types = ['editable', 'requestable', 'requestable_with_variants', 'buyable'];
            $order_type = in_array($saved_order_type, $allowed_order_types, true) ? $saved_order_type : 'editable';
        }

        // Get global values for reference
        $global_image_id  = get_term_meta($term->term_id, '_infoplus_term_image_id', true);
        $global_image_url = $global_image_id ? wp_get_attachment_image_url($global_image_id, 'thumbnail') : '';
        $global_tooltip   = get_term_meta($term->term_id, '_infoplus_term_tooltip', true);
        $global_modal     = get_term_meta($term->term_id, '_infoplus_term_modal_content', true);

        $has_any_override = !empty($image_id) || !empty($gallery_ids) || !empty($tooltip) || !empty($modal_content)
            || $hide_image || $hide_tooltip || $hide_modal
            || ($taxonomy === 'pa_model' && $order_type !== 'editable');
        $has_any_global = !empty($global_image_id) || !empty($global_tooltip) || !empty($global_modal);
        ?>
        <div class="infoplus-term-card <?php echo $has_any_override ? 'has-override' : ''; ?>">
            <div class="term-header">
                <span class="term-name"><?php echo esc_html($term->name); ?></span>
                <?php if ($has_any_override): ?>
                    <span class="override-badge"><?php esc_html_e('Custom', 'infoplus-suite'); ?></span>
                <?php endif; ?>
            </div>

            <div class="term-fields">
                <?php if ($taxonomy === 'pa_model'): ?>
                <!-- Order Type -->
                <div class="field-group field-order-type">
                    <label for="<?php echo esc_attr($order_type_key); ?>">
                        <?php esc_html_e('Bestelltyp', 'infoplus-suite'); ?>
                    </label>
                    <div class="field-content">
                        <select id="<?php echo esc_attr($order_type_key); ?>"
                                name="<?php echo esc_attr($order_type_key); ?>"
                                class="infoplus-order-type-select widefat">
                            <option value="editable" <?php selected($order_type, 'editable'); ?>>
                                <?php esc_html_e('Editable (Editor)', 'infoplus-suite'); ?>
                            </option>
                            <option value="requestable" <?php selected($order_type, 'requestable'); ?>>
                                <?php esc_html_e('Requestable (Anfrage)', 'infoplus-suite'); ?>
                            </option>
                            <option value="requestable_with_variants" <?php selected($order_type, 'requestable_with_variants'); ?>>
                                <?php esc_html_e('Anfrage mit Varianten (Requestable + Variants)', 'infoplus-suite'); ?>
                            </option>
                            <option value="buyable" <?php selected($order_type, 'buyable'); ?>>
                                <?php esc_html_e('Buyable (Warenkorb)', 'infoplus-suite'); ?>
                            </option>
                        </select>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Master Hide All Checkbox -->
                <?php if ($has_any_global): ?>
                <div class="field-group field-hide-all">
                    <label class="hide-checkbox-label">
                        <input type="checkbox"
                               class="infoplus-hide-all-checkbox"
                               <?php checked($hide_all); ?> />
                        <span><?php esc_html_e('Hide all for this product', 'infoplus-suite'); ?></span>
                    </label>
                </div>
                <?php endif; ?>

                <!-- Image Upload -->
                <div class="field-group field-image <?php echo $hide_image ? 'is-hidden' : ''; ?>">
                    <label><?php esc_html_e('Image', 'infoplus-suite'); ?></label>

                    <?php if ($global_image_url): ?>
                    <div class="field-hide-row">
                        <label class="hide-checkbox-label hide-single">
                            <input type="checkbox"
                                   name="<?php echo esc_attr($hide_image_key); ?>"
                                   value="1"
                                   class="infoplus-hide-checkbox"
                                   data-field="image"
                                   <?php checked($hide_image); ?> />
                            <span><?php esc_html_e('Hide for this product', 'infoplus-suite'); ?></span>
                        </label>
                    </div>
                    <?php endif; ?>

                    <div class="field-content <?php echo $hide_image ? 'is-disabled' : ''; ?>">
                        <?php if ($global_image_url && !$image_id): ?>
                            <div class="global-preview <?php echo $hide_image ? 'is-hidden-preview' : ''; ?>">
                                <img src="<?php echo esc_url($global_image_url); ?>" alt="" />
                                <span class="global-label"><?php esc_html_e('Global', 'infoplus-suite'); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($taxonomy === 'pa_model'): ?>
                            <!-- Gallery upload for pa_model (multiple images, sortable) -->
                            <div class="gallery-upload-field">
                                <input type="hidden"
                                       name="<?php echo esc_attr($gallery_key); ?>"
                                       value="<?php echo esc_attr($gallery_ids_str); ?>"
                                       class="infoplus-gallery-ids"
                                       <?php echo $hide_image ? 'disabled' : ''; ?> />

                                <div class="gallery-preview-row">
                                    <?php foreach ($gallery_ids as $idx => $gid):
                                        $g_thumb = wp_get_attachment_image_url($gid, 'thumbnail') ?: '';
                                    ?>
                                        <div class="gallery-thumb-item" data-id="<?php echo esc_attr($gid); ?>">
                                            <img src="<?php echo esc_url($g_thumb); ?>" alt="" />
                                            <?php if ($idx === 0): ?>
                                                <span class="gallery-main-badge">Main</span>
                                            <?php endif; ?>
                                            <button type="button" class="gallery-remove-btn" title="<?php esc_attr_e('Remove', 'infoplus-suite'); ?>">&#215;</button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="gallery-actions">
                                    <button type="button"
                                            class="button button-small infoplus-gallery-add-btn"
                                            <?php echo $hide_image ? 'disabled' : ''; ?>>
                                        <?php echo !empty($gallery_ids)
                                            ? esc_html__('Add More', 'infoplus-suite')
                                            : esc_html__('Add Images', 'infoplus-suite'); ?>
                                    </button>
                                    <button type="button"
                                            class="button button-small infoplus-gallery-clear-btn"
                                            style="<?php echo !empty($gallery_ids) ? '' : 'display:none;'; ?>"
                                            <?php echo $hide_image ? 'disabled' : ''; ?>>
                                        <?php esc_html_e('Clear All', 'infoplus-suite'); ?>
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Single image upload for other attributes -->
                            <div class="image-upload-field" data-meta-key="<?php echo esc_attr($image_key); ?>">
                                <input type="hidden"
                                       name="<?php echo esc_attr($image_key); ?>"
                                       value="<?php echo esc_attr($image_id); ?>"
                                       class="infoplus-image-id"
                                       <?php echo $hide_image ? 'disabled' : ''; ?> />

                                <div class="image-preview" style="<?php echo $image_url ? '' : 'display:none;'; ?>">
                                    <img src="<?php echo esc_url($image_url); ?>" alt="" />
                                </div>

                                <div class="image-actions">
                                    <button type="button"
                                            class="button button-small infoplus-upload-btn"
                                            <?php echo $hide_image ? 'disabled' : ''; ?>>
                                        <?php echo $image_url
                                            ? esc_html__('Change', 'infoplus-suite')
                                            : esc_html__('Upload', 'infoplus-suite'); ?>
                                    </button>
                                    <button type="button"
                                            class="button button-small infoplus-remove-btn"
                                            style="<?php echo $image_url ? '' : 'display:none;'; ?>"
                                            <?php echo $hide_image ? 'disabled' : ''; ?>>
                                        <?php esc_html_e('Remove', 'infoplus-suite'); ?>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tooltip -->
                <div class="field-group field-tooltip <?php echo $hide_tooltip ? 'is-hidden' : ''; ?>">
                    <label for="<?php echo esc_attr($tooltip_key); ?>">
                        <?php esc_html_e('Tooltip', 'infoplus-suite'); ?>
                    </label>

                    <?php if ($global_tooltip): ?>
                    <div class="field-hide-row">
                        <label class="hide-checkbox-label hide-single">
                            <input type="checkbox"
                                   name="<?php echo esc_attr($hide_tooltip_key); ?>"
                                   value="1"
                                   class="infoplus-hide-checkbox"
                                   data-field="tooltip"
                                   <?php checked($hide_tooltip); ?> />
                            <span><?php esc_html_e('Hide for this product', 'infoplus-suite'); ?></span>
                        </label>
                    </div>
                    <?php endif; ?>

                    <div class="field-content <?php echo $hide_tooltip ? 'is-disabled' : ''; ?>">
                        <input type="text"
                               id="<?php echo esc_attr($tooltip_key); ?>"
                               name="<?php echo esc_attr($tooltip_key); ?>"
                               value="<?php echo esc_attr($tooltip); ?>"
                               class="regular-text"
                               placeholder="<?php echo esc_attr($global_tooltip ?: __('Hover text...', 'infoplus-suite')); ?>"
                               <?php echo $hide_tooltip ? 'disabled' : ''; ?> />
                        <?php if ($global_tooltip && !$tooltip && !$hide_tooltip): ?>
                            <small class="global-hint"><?php printf(__('Global: %s', 'infoplus-suite'), esc_html($global_tooltip)); ?></small>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Modal Content -->
                <div class="field-group field-modal <?php echo $hide_modal ? 'is-hidden' : ''; ?>">
                    <label for="<?php echo esc_attr($modal_key); ?>">
                        <?php esc_html_e('Modal Content', 'infoplus-suite'); ?>
                    </label>

                    <?php if ($global_modal): ?>
                    <div class="field-hide-row">
                        <label class="hide-checkbox-label hide-single">
                            <input type="checkbox"
                                   name="<?php echo esc_attr($hide_modal_key); ?>"
                                   value="1"
                                   class="infoplus-hide-checkbox"
                                   data-field="modal"
                                   <?php checked($hide_modal); ?> />
                            <span><?php esc_html_e('Hide for this product', 'infoplus-suite'); ?></span>
                        </label>
                    </div>
                    <?php endif; ?>

                    <div class="field-content <?php echo $hide_modal ? 'is-disabled' : ''; ?>">
                        <textarea id="<?php echo esc_attr($modal_key); ?>"
                                  name="<?php echo esc_attr($modal_key); ?>"
                                  rows="3"
                                  class="large-text"
                                  placeholder="<?php echo esc_attr($global_modal ? __('Has global content...', 'infoplus-suite') : __('Detailed description...', 'infoplus-suite')); ?>"
                                  <?php echo $hide_modal ? 'disabled' : ''; ?>><?php echo esc_textarea($modal_content); ?></textarea>
                        <?php if ($global_modal && !$modal_content && !$hide_modal): ?>
                            <small class="global-hint"><?php esc_html_e('Using global modal content', 'infoplus-suite'); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Save
    // -------------------------------------------------------------------------

    /**
     * Save meta box data — handles all three tabs
     */
    public function saveMetaBox(int $post_id, \WP_Post $post): void
    {
        // Security checks
        if (!isset($_POST['infoplus_product_attr_images_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['infoplus_product_attr_images_nonce'], 'infoplus_product_attr_images')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // ---- Tab 2: IBE/IFE fields ----------------------------------------

        if (isset($_POST['ife_uuid'])) {
            update_post_meta($post_id, '_ife_uuid', sanitize_text_field($_POST['ife_uuid']));
        }
        if (isset($_POST['ife_unique_text_name'])) {
            update_post_meta($post_id, '_ife_unique_text_name', sanitize_text_field($_POST['ife_unique_text_name']));
        }
        if (isset($_POST['ife_print_api_id'])) {
            update_post_meta($post_id, '_ife_print_api_id', sanitize_text_field($_POST['ife_print_api_id']));
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

        // ---- Tab 3: Default Variation ID (legacy single default) -----------

        if (isset($_POST[self::META_KEY_DEFAULT_VARIATION])) {
            $variation_id = absint($_POST[self::META_KEY_DEFAULT_VARIATION]);
            if ($variation_id > 0) {
                // Validate it is actually a variation of this product
                $variation = wc_get_product($variation_id);
                if ($variation && $variation->is_type('variation') && $variation->get_parent_id() === $post_id) {
                    update_post_meta($post_id, self::META_KEY_DEFAULT_VARIATION, $variation_id);
                } else {
                    delete_post_meta($post_id, self::META_KEY_DEFAULT_VARIATION);
                }
            } else {
                delete_post_meta($post_id, self::META_KEY_DEFAULT_VARIATION);
            }
        }

        // ---- Tab 3: Per-model default variations ---------------------------

        if (isset($_POST['_infoplus_model_default_variations']) && is_array($_POST['_infoplus_model_default_variations'])) {
            $raw = (array) $_POST['_infoplus_model_default_variations'];
            $map = [];
            foreach ($raw as $model_slug => $variation_id) {
                $model_slug   = sanitize_key($model_slug);
                $variation_id = absint($variation_id);
                if ($model_slug && $variation_id > 0) {
                    $var = wc_get_product($variation_id);
                    if ($var && $var->get_parent_id() === $post_id) {
                        $map[$model_slug] = $variation_id;
                    }
                }
            }
            if (!empty($map)) {
                update_post_meta($post_id, self::META_KEY_MODEL_DEFAULT_VARIATIONS, wp_json_encode($map));
            } else {
                delete_post_meta($post_id, self::META_KEY_MODEL_DEFAULT_VARIATIONS);
            }
        }

        // ---- Tab 1: Attribute Override fields (prefix-matched) -------------

        $processed_keys = [];

        foreach ($_POST as $key => $value) {
            // Gallery fields (pa_model multi-image, comma-separated IDs)
            if (strpos($key, self::META_KEY_GALLERY) === 0) {
                $processed_keys[] = $key;
                $ids = array_values(array_filter(array_map('absint', explode(',', $value))));
                if (!empty($ids)) {
                    update_post_meta($post_id, $key, implode(',', $ids));
                } else {
                    delete_post_meta($post_id, $key);
                }
            }
            // Order type fields (pa_model only)
            elseif (strpos($key, self::META_KEY_ORDER_TYPE) === 0) {
                $processed_keys[] = $key;
                $allowed   = ['editable', 'requestable', 'requestable_with_variants', 'buyable'];
                $sanitized = in_array($value, $allowed, true) ? $value : 'editable';
                update_post_meta($post_id, $key, $sanitized);
            }
            // Image fields
            elseif (strpos($key, self::META_KEY_IMAGE) === 0) {
                $processed_keys[] = $key;
                $image_id = absint($value);
                if ($image_id > 0) {
                    update_post_meta($post_id, $key, $image_id);
                } else {
                    delete_post_meta($post_id, $key);
                }
            }
            // Tooltip fields
            elseif (strpos($key, self::META_KEY_TOOLTIP) === 0) {
                $processed_keys[] = $key;
                $tooltip = sanitize_text_field($value);
                if (!empty($tooltip)) {
                    update_post_meta($post_id, $key, $tooltip);
                } else {
                    delete_post_meta($post_id, $key);
                }
            }
            // Modal content fields
            elseif (strpos($key, self::META_KEY_MODAL) === 0) {
                $processed_keys[] = $key;
                $modal = wp_kses_post($value);
                if (!empty($modal)) {
                    update_post_meta($post_id, $key, $modal);
                } else {
                    delete_post_meta($post_id, $key);
                }
            }
            // Hide image flags
            elseif (strpos($key, self::META_KEY_HIDE_IMAGE) === 0) {
                $processed_keys[] = $key;
                if ($value === '1') {
                    update_post_meta($post_id, $key, '1');
                } else {
                    delete_post_meta($post_id, $key);
                }
            }
            // Hide tooltip flags
            elseif (strpos($key, self::META_KEY_HIDE_TOOLTIP) === 0) {
                $processed_keys[] = $key;
                if ($value === '1') {
                    update_post_meta($post_id, $key, '1');
                } else {
                    delete_post_meta($post_id, $key);
                }
            }
            // Hide modal flags
            elseif (strpos($key, self::META_KEY_HIDE_MODAL) === 0) {
                $processed_keys[] = $key;
                if ($value === '1') {
                    update_post_meta($post_id, $key, '1');
                } else {
                    delete_post_meta($post_id, $key);
                }
            }
        }

        // Handle unchecked checkboxes (they won't be in $_POST)
        // We need to delete hide flags that were unchecked
        $existing_meta = get_post_meta($post_id);
        foreach ($existing_meta as $key => $values) {
            if (strpos($key, self::META_KEY_HIDE_IMAGE) === 0 ||
                strpos($key, self::META_KEY_HIDE_TOOLTIP) === 0 ||
                strpos($key, self::META_KEY_HIDE_MODAL) === 0) {
                if (!isset($_POST[$key])) {
                    delete_post_meta($post_id, $key);
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Admin assets
    // -------------------------------------------------------------------------

    /**
     * Enqueue admin assets and pass variation data for the Default Variation tab
     */
    public function enqueueAdminAssets(string $hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'product') {
            return;
        }

        wp_enqueue_media();

        $asset_url = INFOPLUS_SUITE_URL . 'src/Modules/ProductDetailSuite/Assets/';
        $version   = \Infoplus\Suite\Plugin::VERSION . '.7'; // Bump for cache busting

        // Enqueue CSS
        wp_enqueue_style(
            'infoplus-product-attribute-images-admin',
            $asset_url . 'admin-product-attribute-images.css',
            [],
            $version
        );

        // Enqueue JS
        wp_enqueue_script(
            'infoplus-product-attribute-images-admin',
            $asset_url . 'admin-product-attribute-images.js',
            ['jquery', 'jquery-ui-sortable', 'media-upload', 'media-views'],
            $version,
            true
        );

        // Pass variation data for the Default Variation tab cascading dropdowns
        $post_id = get_the_ID();
        if ($post_id) {
            $product        = wc_get_product($post_id);
            $variations_data = [];

            if ($product && $product->is_type('variable')) {
                foreach ($product->get_children() as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    if (!$variation) continue;
                    $variations_data[] = [
                        'id'         => $variation_id,
                        'sku'        => $variation->get_sku(),
                        'attributes' => $variation->get_variation_attributes(), // ['attribute_pa_model' => 'slug', ...]
                        'in_stock'   => $variation->is_in_stock(),
                    ];
                }
            }

            $model_defaults_raw = get_post_meta($post_id, self::META_KEY_MODEL_DEFAULT_VARIATIONS, true);
            $model_defaults     = $model_defaults_raw ? json_decode($model_defaults_raw, true) : [];

            wp_localize_script('infoplus-product-attribute-images-admin', 'infoplusAdminData', [
                'variations'             => $variations_data,
                'defaultVariationId'     => (int) get_post_meta($post_id, self::META_KEY_DEFAULT_VARIATION, true),
                'modelDefaultVariations' => is_array($model_defaults) ? $model_defaults : (object) [],
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Static utility methods (used by widgets)
    // -------------------------------------------------------------------------

    /**
     * Get image URL for a term on a specific product
     *
     * This is the main method to call from widgets to get the correct image.
     * It checks: 1) hide flag, 2) product-level override, 3) global.
     *
     * @param int $product_id The product ID
     * @param string $taxonomy The attribute taxonomy (e.g., 'pa_model')
     * @param string $term_slug The term slug
     * @return string Image URL or empty string
     */
    public static function getTermImageUrl(int $product_id, string $taxonomy, string $term_slug): string
    {
        $term_key_suffix = str_replace('pa_', '', $taxonomy) . '_' . $term_slug;

        // Check if hidden for this product
        $hide_key = self::META_KEY_HIDE_IMAGE . $term_key_suffix;
        if (get_post_meta($product_id, $hide_key, true) === '1') {
            return ''; // Explicitly hidden
        }

        // Check for product-level override
        $meta_key        = self::META_KEY_IMAGE . $term_key_suffix;
        $product_image_id = get_post_meta($product_id, $meta_key, true);

        if ($product_image_id) {
            $url = wp_get_attachment_url($product_image_id);
            if ($url) {
                return $url;
            }
        }

        // Fall back to global term image
        $term = get_term_by('slug', $term_slug, $taxonomy);
        if ($term) {
            $global_image_id = get_term_meta($term->term_id, '_infoplus_term_image_id', true);
            if ($global_image_id) {
                $url = wp_get_attachment_url($global_image_id);
                if ($url) {
                    return $url;
                }
            }
        }

        return '';
    }

    /**
     * Get all gallery image data for a model term on a specific product.
     *
     * Priority:
     *  1. Per-product gallery (_infoplus_attr_gallery_*)  → all images
     *  2. Per-product single image (_infoplus_attr_image_*) → single-item gallery
     *  3. Global term image (_infoplus_term_image_id) → single-item gallery
     *  4. Hidden or nothing → empty array
     *
     * @param int $product_id The product ID
     * @param string $taxonomy The attribute taxonomy (e.g., 'pa_model')
     * @param string $term_slug The term slug
     * @return array  [ ['id' => int, 'url' => string, 'thumbnail_url' => string], … ]
     */
    public static function getTermGalleryUrls(int $product_id, string $taxonomy, string $term_slug): array
    {
        $suffix = str_replace('pa_', '', $taxonomy) . '_' . $term_slug;

        // Respect the hide flag (same flag as single image)
        $hide_key = self::META_KEY_HIDE_IMAGE . $suffix;
        if (get_post_meta($product_id, $hide_key, true) === '1') {
            return [];
        }

        // Priority 1: per-product gallery (comma-separated IDs)
        $gallery_str = get_post_meta($product_id, self::META_KEY_GALLERY . $suffix, true);
        if ($gallery_str) {
            $ids = array_values(array_filter(array_map('absint', explode(',', $gallery_str))));
            if (!empty($ids)) {
                $images = [];
                foreach ($ids as $id) {
                    $url = wp_get_attachment_url($id);
                    if ($url) {
                        $thumb    = wp_get_attachment_image_url($id, 'thumbnail');
                        $images[] = ['id' => $id, 'url' => $url, 'thumbnail_url' => $thumb ?: $url];
                    }
                }
                if (!empty($images)) {
                    return $images;
                }
            }
        }

        // Priority 2: per-product single image
        $single_id = (int) get_post_meta($product_id, self::META_KEY_IMAGE . $suffix, true);
        if ($single_id > 0) {
            $url = wp_get_attachment_url($single_id);
            if ($url) {
                $thumb = wp_get_attachment_image_url($single_id, 'thumbnail');
                return [['id' => $single_id, 'url' => $url, 'thumbnail_url' => $thumb ?: $url]];
            }
        }

        // Priority 3: global term image
        $term = get_term_by('slug', $term_slug, $taxonomy);
        if ($term) {
            $global_id = (int) get_term_meta($term->term_id, '_infoplus_term_image_id', true);
            if ($global_id > 0) {
                $url = wp_get_attachment_url($global_id);
                if ($url) {
                    $thumb = wp_get_attachment_image_url($global_id, 'thumbnail');
                    return [['id' => $global_id, 'url' => $url, 'thumbnail_url' => $thumb ?: $url]];
                }
            }
        }

        return [];
    }

    /**
     * Get the order type for a model term on a specific product.
     *
     * @param int    $product_id The product ID
     * @param string $taxonomy   The attribute taxonomy (e.g., 'pa_model')
     * @param string $term_slug  The term slug
     * @return string 'editable' (default), 'requestable', 'requestable_with_variants', or 'buyable'
     */
    public static function getTermOrderType(int $product_id, string $taxonomy, string $term_slug): string
    {
        $suffix = str_replace('pa_', '', $taxonomy) . '_' . $term_slug;
        $value  = get_post_meta($product_id, self::META_KEY_ORDER_TYPE . $suffix, true);
        $allowed = ['editable', 'requestable', 'requestable_with_variants', 'buyable'];
        return in_array($value, $allowed, true) ? $value : 'editable';
    }

    /**
     * Get image ID for a term on a specific product
     *
     * @param int $product_id The product ID
     * @param string $taxonomy The attribute taxonomy (e.g., 'pa_model')
     * @param string $term_slug The term slug
     * @return int Image ID or 0
     */
    public static function getTermImageId(int $product_id, string $taxonomy, string $term_slug): int
    {
        $term_key_suffix = str_replace('pa_', '', $taxonomy) . '_' . $term_slug;

        // Check if hidden for this product
        $hide_key = self::META_KEY_HIDE_IMAGE . $term_key_suffix;
        if (get_post_meta($product_id, $hide_key, true) === '1') {
            return 0; // Explicitly hidden
        }

        // Check for product-level override
        $meta_key         = self::META_KEY_IMAGE . $term_key_suffix;
        $product_image_id = get_post_meta($product_id, $meta_key, true);

        if ($product_image_id) {
            return (int) $product_image_id;
        }

        // Fall back to global term image
        $term = get_term_by('slug', $term_slug, $taxonomy);
        if ($term) {
            $global_image_id = get_term_meta($term->term_id, '_infoplus_term_image_id', true);
            if ($global_image_id) {
                return (int) $global_image_id;
            }
        }

        return 0;
    }

    /**
     * Get tooltip for a term on a specific product
     *
     * @param int $product_id The product ID
     * @param string $taxonomy The attribute taxonomy (e.g., 'pa_model')
     * @param string $term_slug The term slug
     * @return string Tooltip text or empty string
     */
    public static function getTermTooltip(int $product_id, string $taxonomy, string $term_slug): string
    {
        $term_key_suffix = str_replace('pa_', '', $taxonomy) . '_' . $term_slug;

        // Check if hidden for this product
        $hide_key = self::META_KEY_HIDE_TOOLTIP . $term_key_suffix;
        if (get_post_meta($product_id, $hide_key, true) === '1') {
            return ''; // Explicitly hidden
        }

        // Check for product-level override
        $meta_key       = self::META_KEY_TOOLTIP . $term_key_suffix;
        $product_tooltip = get_post_meta($product_id, $meta_key, true);

        if ($product_tooltip) {
            return $product_tooltip;
        }

        // Fall back to global term tooltip
        $term = get_term_by('slug', $term_slug, $taxonomy);
        if ($term) {
            $global_tooltip = get_term_meta($term->term_id, '_infoplus_term_tooltip', true);
            if ($global_tooltip) {
                return $global_tooltip;
            }
        }

        return '';
    }

    /**
     * Get modal content for a term on a specific product
     *
     * @param int $product_id The product ID
     * @param string $taxonomy The attribute taxonomy (e.g., 'pa_model')
     * @param string $term_slug The term slug
     * @return string Modal content or empty string
     */
    public static function getTermModalContent(int $product_id, string $taxonomy, string $term_slug): string
    {
        $term_key_suffix = str_replace('pa_', '', $taxonomy) . '_' . $term_slug;

        // Check if hidden for this product
        $hide_key = self::META_KEY_HIDE_MODAL . $term_key_suffix;
        if (get_post_meta($product_id, $hide_key, true) === '1') {
            return ''; // Explicitly hidden
        }

        // Check for product-level override
        $meta_key      = self::META_KEY_MODAL . $term_key_suffix;
        $product_modal = get_post_meta($product_id, $meta_key, true);

        if ($product_modal) {
            return $product_modal;
        }

        // Fall back to global term modal content, then description
        $term = get_term_by('slug', $term_slug, $taxonomy);
        if ($term) {
            $global_modal = get_term_meta($term->term_id, '_infoplus_term_modal_content', true);
            if ($global_modal) {
                return $global_modal;
            }

            // Fallback to term description
            if ($term->description) {
                return $term->description;
            }
        }

        return '';
    }
}
