<?php
/**
 * Template: variation dropdowns (component "variation-dropdowns").
 *
 * Override in a theme: copy to {theme}/infoplus-suite/variation-dropdowns.php.
 * Rendered by Infoplus\Suite\View::render(), called from the VariationDropdowns
 * Elementor widget and from infoplus_suite_variation_dropdowns(). Keep the
 * infoplus-* classes, data-* attributes and the hidden native selects:
 * variation-dropdowns.js and product-state.js depend on them.
 *
 * $args (built by Components::variationDropdowns()):
 *   context               string  'elementor' or 'theme'
 *   attributes_to_show    array   Attribute taxonomies, empty = all
 *   exclude_model         bool    Skip pa_model
 *   show_info_button      bool
 *   info_button_behavior  string  'tooltip' or 'modal'
 *   placeholder_text      string
 *   product               WC_Product|null
 *   has_product           bool    true for a variable product
 *   dropdowns             array   attribute, label, slug, has_images, terms
 *                                 (terms: slug, name, image_url, tooltip, modal_content)
 *
 * Restyle hooks: root .ip-c-variation-dropdowns[data-ip-component="variation-dropdowns"],
 * parts .ip-c-variation-dropdowns__row (one attribute), __label, __trigger,
 * __options (option list), __option, __image (option image).
 *
 * CSS custom properties (theme contract, defaults are the widget control
 * defaults; the plugin CSS does not read them, the theme maps them onto the
 * classes above). The plugin CSS already reads --infoplus-dropdown-image-height
 * (32px) and --infoplus-dropdown-image-width (42px); set those for image size.
 *   --ip-vd-gap                  16px     gap between dropdowns
 *   --ip-vd-bg                   #ffffff  trigger and option list background
 *   --ip-vd-radius               6px
 *   --ip-vd-padding              10px 14px
 *   --ip-vd-label-color          #666666
 *   --ip-vd-option-color         #333333
 *   --ip-vd-option-hover-bg      #f5f5f5
 *
 * Known quirk kept on purpose: the former widget never printed data-tooltip on
 * the option info buttons ($info_behavior was undefined in renderDropdowns()).
 * variation-dropdowns.js falls back to the option's own data-tooltip.
 * $option_info_tooltip_attr reproduces that; a theme override may set it to
 * ($args['info_button_behavior'] === 'tooltip').
 *
 * Whitespace below mirrors the former VariationDropdowns::render() output byte for byte.
 *
 * @package Infoplus\Suite
 */

defined('ABSPATH') || exit;

$show_info                = $args['show_info_button'];
$placeholder              = $args['placeholder_text'];
$option_info_tooltip_attr = false;

?>
        <div class="infoplus-variation-dropdowns ip-c-variation-dropdowns" data-ip-component="variation-dropdowns"
            data-show-info="<?php echo esc_attr($show_info ? 'yes' : 'no'); ?>"
            data-info-behavior="<?php echo esc_attr($args['info_button_behavior']); ?>"
            data-placeholder="<?php echo esc_attr($placeholder); ?>">

            <?php if ($args['has_product']): ?>
                <?php
        foreach ($args['dropdowns'] as $dropdown) {
            $terms_data = $dropdown['terms'];
            $has_images = $dropdown['has_images'];
        ?>
            <div class="infoplus-dropdown ip-c-variation-dropdowns__row"
                 data-attribute="<?php echo esc_attr($dropdown['attribute']); ?>"
                 data-has-images="<?php echo $has_images ? 'yes' : 'no'; ?>">
                <label class="infoplus-dropdown__label ip-c-variation-dropdowns__label"><?php echo esc_html($dropdown['label']); ?></label>

                <div class="infoplus-dropdown__trigger ip-c-variation-dropdowns__trigger" tabindex="0" role="combobox" aria-expanded="false">
                    <span class="infoplus-dropdown__selected">
                        <img class="infoplus-dropdown__selected-image litespeed-no-lazy" src="" alt="" data-no-lazy="1" loading="eager" />
                        <span class="infoplus-dropdown__selected-text"><?php echo esc_html($placeholder); ?></span>
                        <?php if ($show_info): ?>
                            <button class="infoplus-dropdown__selected-info" type="button" aria-label="<?php esc_attr_e('More info', 'infoplus-suite'); ?>">
                                <span>i</span>
                            </button>
                        <?php endif; ?>
                    </span>
                    <span class="infoplus-dropdown__arrow">
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                            <path d="M2.5 4.5L6 8L9.5 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                </div>

                <div class="infoplus-dropdown__options ip-c-variation-dropdowns__options" role="listbox" style="display: none;">
                    <?php foreach ($terms_data as $term): ?>
                        <div class="infoplus-dropdown__option ip-c-variation-dropdowns__option"
                            data-value="<?php echo esc_attr($term['slug']); ?>"
                            data-image="<?php echo esc_url($term['image_url']); ?>"
                            data-tooltip="<?php echo esc_attr($term['tooltip']); ?>"
                            data-modal-content="<?php echo esc_attr($term['modal_content']); ?>"
                            role="option">

                            <?php if ($term['image_url']): ?>
                                <img src="<?php echo esc_url($term['image_url']); ?>"
                                    alt="<?php echo esc_attr($term['name']); ?>"
                                    class="infoplus-dropdown__option-image ip-c-variation-dropdowns__image" />
                            <?php endif; ?>

                            <span class="infoplus-dropdown__option-text"><?php echo esc_html($term['name']); ?></span>

                            <?php
                            $has_info = !empty(trim($term['tooltip'])) || !empty(trim($term['modal_content']));
                            if ($show_info && $has_info):
                            ?>
                                <button class="infoplus-dropdown__option-info"
                                    type="button"
                                    <?php if ($option_info_tooltip_attr && !empty(trim($term['tooltip']))): ?>
                                    data-tooltip="<?php echo esc_attr($term['tooltip']); ?>"
                                    <?php endif; ?>
                                    aria-label="<?php esc_attr_e('More info', 'infoplus-suite'); ?>">
                                    <span>i</span>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Hidden native select for form submission -->
                <select name="attribute_<?php echo esc_attr($dropdown['slug']); ?>"
                    class="infoplus-dropdown__native"
                    style="display: none !important;">
                    <option value=""><?php echo esc_html($placeholder); ?></option>
                    <?php foreach ($terms_data as $term): ?>
                        <option value="<?php echo esc_attr($term['slug']); ?>"><?php echo esc_html($term['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php
        }
        ?>
            <?php else: ?>
                <div class="infoplus-variation-dropdowns__placeholder">
                    <p><?php esc_html_e('This widget requires a variable product.', 'infoplus-suite'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
