<?php
/**
 * Template: model selector (component "model-selector").
 *
 * Override in a theme: copy to {theme}/infoplus-suite/model-selector.php.
 * Rendered by Infoplus\Suite\View::render(), called from the ModelSelector
 * Elementor widget and from infoplus_suite_model_selector(). Keep the
 * infoplus-* classes and data-* attributes: model-selector.js and
 * product-state.js depend on them.
 *
 * $args (built by Components::modelSelector()):
 *   context               string  'elementor' or 'theme'
 *   title                 string  Section title
 *   show_title            bool
 *   show_price            bool    "from" price on each card
 *   show_info_button      bool
 *   info_button_behavior  string  'modal' or 'tooltip'
 *   sync_product_image    bool    Update the product image when a model is picked
 *   layout                string  'vertical' or 'horizontal'
 *   product               WC_Product|null
 *   has_product           bool    true for a variable product
 *   models                array   slug, name, image_url, tooltip, modal_content, min_price
 *
 * Restyle hooks: root .ip-c-model-selector[data-ip-component="model-selector"],
 * parts .ip-c-model-selector__title, __options (card list), __option (card),
 * __image, __info, __label (model name), __price, __modal.
 *
 * CSS custom properties (theme contract, defaults are the widget control
 * defaults; the plugin CSS does not read them, the theme maps them onto the
 * classes above). In Elementor the same values come from the widget style controls.
 *   --ip-ms-gap                      12px     gap between cards
 *   --ip-ms-max-height               500px    list height, vertical layout
 *   --ip-ms-card-padding             12px
 *   --ip-ms-card-bg                  #ffffff
 *   --ip-ms-card-bg-active           #f8f9fa
 *   --ip-ms-card-border-color        #e0e0e0
 *   --ip-ms-card-border-color-active #0066cc
 *   --ip-ms-card-radius              8px
 *   --ip-ms-image-min-height         120px
 *   --ip-ms-image-radius             6px
 *   --ip-ms-title-color              #333333
 *   --ip-ms-name-color               #333333
 *   --ip-ms-price-color              #e91e8c
 *   --ip-ms-indicator-color          #0066cc  active card bar
 *   --ip-ms-indicator-width          4px
 *
 * Whitespace below mirrors the former ModelSelector::render() output byte for byte.
 *
 * @package Infoplus\Suite
 */

defined('ABSPATH') || exit;

$wrapper_class = 'infoplus-model-selector';
$wrapper_class .= ' infoplus-model-selector--' . esc_attr($args['layout']);
$wrapper_class .= ' ip-c-model-selector';

?>
        <?php 
        $show_info = $args['show_info_button'];
        $sync_product_image = $args['sync_product_image'];
        ?>
        <div class="<?php echo esc_attr($wrapper_class); ?>" data-ip-component="model-selector" 
             data-info-behavior="<?php echo esc_attr($args['info_button_behavior']); ?>"
             data-show-price="<?php echo esc_attr($args['show_price'] ? 'yes' : ''); ?>"
             data-show-info="<?php echo esc_attr($show_info ? 'yes' : 'no'); ?>"
             data-sync-product-image="<?php echo esc_attr($sync_product_image ? 'yes' : 'no'); ?>">
            
            <?php if ($args['show_title'] && !empty($args['title'])): ?>
                <h3 class="infoplus-model-selector__title ip-c-model-selector__title"><?php echo esc_html($args['title']); ?></h3>
            <?php endif; ?>
            
            <div class="infoplus-model-selector__list ip-c-model-selector__options">
                <?php if ($args['has_product']): ?>
                    <?php
        $show_info_button = $args['show_info_button'];
        $info_behavior    = $args['info_button_behavior'];
        $first            = true;
        foreach ($args['models'] as $model) {
            $tooltip       = (string) $model['tooltip'];
            $modal_content = (string) $model['modal_content'];

            // Card classes
            $card_classes = ['infoplus-model-selector__card'];
            if ($first) {
                $card_classes[] = 'is-active';
                $first = false;
            }

            // Only add tooltip class if info button is OFF and tooltip exists
            $has_tooltip_content = !empty(trim($tooltip));
            if (!$show_info_button && $has_tooltip_content) {
                $card_classes[] = 'infoplus-show-tooltip';
            }
            $card_classes[] = 'ip-c-model-selector__option';

            ?>
            <div class="<?php echo esc_attr(implode(' ', $card_classes)); ?>" 
                 data-model="<?php echo esc_attr($model['slug']); ?>"
                 data-model-image="<?php echo esc_url($model['image_url']); ?>"
                 <?php if (!$show_info_button && $has_tooltip_content): ?>
                 data-tooltip="<?php echo esc_attr($tooltip); ?>"
                 <?php endif; ?>
                 data-modal-content="<?php echo esc_attr($modal_content); ?>">
                
                <div class="infoplus-model-selector__image ip-c-model-selector__image">
                    <img src="<?php echo esc_url($model['image_url']); ?>" 
                         alt="<?php echo esc_attr($model['name']); ?>" 
                         loading="lazy" />
                    
                    <?php if ($show_info_button && ($has_tooltip_content || !empty(trim($modal_content)))): ?>
                        <button class="infoplus-model-selector__info-btn ip-c-model-selector__info" 
                                type="button"
                                <?php if ($info_behavior === 'tooltip' && $has_tooltip_content): ?>
                                data-tooltip="<?php echo esc_attr($tooltip); ?>"
                                <?php endif; ?>
                                aria-label="<?php esc_attr_e('More information', 'infoplus-suite'); ?>">
                            <span>i</span>
                        </button>
                    <?php endif; ?>
                </div>
                
                <div class="infoplus-model-selector__content">
                    <span class="infoplus-model-selector__name ip-c-model-selector__label"><?php echo esc_html($model['name']); ?></span>
                    
                    <?php if ($args['show_price'] && $model['min_price'] < PHP_FLOAT_MAX): ?>
                        <span class="infoplus-model-selector__price ip-c-model-selector__price">
                            <?php echo esc_html__('from', 'infoplus-suite'); ?> 
                            <?php echo wc_price($model['min_price']); // wc_price() returns escaped markup. ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }
        ?>
                <?php else: ?>
                    <div class="infoplus-model-selector__placeholder">
                        <p><?php esc_html_e('This widget requires a variable product.', 'infoplus-suite'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Info Modal Template -->
        <div class="infoplus-info-modal ip-c-model-selector__modal" style="display: none;">
            <div class="infoplus-info-modal__backdrop"></div>
            <div class="infoplus-info-modal__content">
                <button class="infoplus-info-modal__close" type="button">&times;</button>
                <div class="infoplus-info-modal__image"></div>
                <h4 class="infoplus-info-modal__title"></h4>
                <div class="infoplus-info-modal__description"></div>
            </div>
        </div>
        <?php
