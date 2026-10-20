<?php
/**
 * Attribute Term Fields Handler
 * 
 * Adds custom fields to WooCommerce attribute terms (global level):
 * - Term image
 * - Tooltip text
 * - Modal content (extended description)
 * 
 * These fields are shared across all products using the attribute.
 * 
 * @package Infoplus\Suite\Modules\ProductDetailSuite\Fields
 * @since 1.0.0
 */

namespace Infoplus\Suite\Modules\ProductDetailSuite\Fields;

if (!defined('ABSPATH')) exit;

class AttributeTermFields
{
    /**
     * List of product attribute taxonomies to add fields to
     */
    private array $targetTaxonomies = [
        'pa_model',
        'pa_format',
        'pa_befestigung',
        'pa_oberflache',
        'pa_farbe',
        'pa_flachenfarbe',
        'pa_holzart',
        'pa_tragerfarbe',
    ];

    /**
     * Register hooks for attribute term fields
     */
    /** Term meta: optional swatch colour, "#rrggbb" (sanitize_hex_color). Since 0.10.31. */
    public const META_COLOR = '_infoplus_term_color';

    /** The swatch colour of a term, '' when none is set. */
    public static function termColor(int $term_id): string
    {
        $color = sanitize_hex_color((string) get_term_meta($term_id, self::META_COLOR, true));
        return $color ? (string) $color : '';
    }

    public function register(): void
    {
        // Add fields to term add/edit forms for each taxonomy
        foreach ($this->targetTaxonomies as $taxonomy) {
            // Add form fields
            add_action("{$taxonomy}_add_form_fields", [$this, 'addTermFields'], 10);
            add_action("{$taxonomy}_edit_form_fields", [$this, 'editTermFields'], 10, 2);
            
            // Save fields
            add_action("created_{$taxonomy}", [$this, 'saveTermFields'], 10, 2);
            add_action("edited_{$taxonomy}", [$this, 'saveTermFields'], 10, 2);
            
            // Add custom column to term list
            add_filter("manage_edit-{$taxonomy}_columns", [$this, 'addTermColumns']);
            add_filter("manage_{$taxonomy}_custom_column", [$this, 'renderTermColumn'], 10, 3);
        }

        // Enqueue admin scripts for media uploader
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
    }

    /**
     * Add fields to "Add New Term" form
     */
    public function addTermFields($taxonomy): void
    {
        ?>
        <div class="form-field infoplus-term-image-field">
            <label for="infoplus_term_image"><?php esc_html_e('Term Image', 'infoplus-suite'); ?></label>
            <div class="infoplus-term-image-wrapper">
                <input type="hidden" name="infoplus_term_image_id" id="infoplus_term_image_id" value="" />
                <div class="infoplus-term-image-preview" style="margin-bottom: 10px; display: none;">
                    <img src="" style="max-width: 150px; max-height: 150px; border: 1px solid #ddd; border-radius: 4px;" />
                </div>
                <button type="button" class="button infoplus-upload-term-image-btn">
                    <?php esc_html_e('Upload Image', 'infoplus-suite'); ?>
                </button>
                <button type="button" class="button infoplus-remove-term-image-btn" style="display: none; color: #a00;">
                    <?php esc_html_e('Remove', 'infoplus-suite'); ?>
                </button>
            </div>
            <p class="description"><?php esc_html_e('This image will appear in dropdown selectors on the product page.', 'infoplus-suite'); ?></p>
        </div>

        <div class="form-field infoplus-term-color-field">
            <label for="infoplus_term_color"><?php esc_html_e('Farbe (Hex)', 'infoplus-suite'); ?></label>
            <input type="text" name="infoplus_term_color" id="infoplus_term_color" value="" class="infoplus-term-color" data-default-color="" />
            <p class="description"><?php esc_html_e('Optional. Colour swatch before the option name, e.g. #1F3A93. A term image wins over it.', 'infoplus-suite'); ?></p>
        </div>

        <div class="form-field infoplus-term-tooltip-field">
            <label for="infoplus_term_tooltip"><?php esc_html_e('Tooltip Text', 'infoplus-suite'); ?></label>
            <input type="text" name="infoplus_term_tooltip" id="infoplus_term_tooltip" value="" />
            <p class="description"><?php esc_html_e('Short text shown on hover over the info icon.', 'infoplus-suite'); ?></p>
        </div>

        <div class="form-field infoplus-term-modal-field">
            <label for="infoplus_term_modal_content"><?php esc_html_e('Modal Content', 'infoplus-suite'); ?></label>
            <textarea name="infoplus_term_modal_content" id="infoplus_term_modal_content" rows="4"></textarea>
            <p class="description"><?php esc_html_e('Extended description shown when clicking the info icon. Supports basic HTML.', 'infoplus-suite'); ?></p>
        </div>
        <?php
    }

    /**
     * Add fields to "Edit Term" form
     */
    public function editTermFields($term, $taxonomy): void
    {
        $image_id = get_term_meta($term->term_id, '_infoplus_term_image_id', true);
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
        $tooltip = get_term_meta($term->term_id, '_infoplus_term_tooltip', true);
        $modal_content = get_term_meta($term->term_id, '_infoplus_term_modal_content', true);
        $color = self::termColor($term->term_id);
        ?>
        <tr class="form-field infoplus-term-image-field">
            <th scope="row">
                <label for="infoplus_term_image"><?php esc_html_e('Term Image', 'infoplus-suite'); ?></label>
            </th>
            <td>
                <div class="infoplus-term-image-wrapper">
                    <input type="hidden" name="infoplus_term_image_id" id="infoplus_term_image_id" value="<?php echo esc_attr($image_id); ?>" />
                    <div class="infoplus-term-image-preview" style="margin-bottom: 10px; <?php echo $image_url ? '' : 'display: none;'; ?>">
                        <img src="<?php echo esc_url($image_url); ?>" style="max-width: 150px; max-height: 150px; border: 1px solid #ddd; border-radius: 4px;" />
                    </div>
                    <button type="button" class="button infoplus-upload-term-image-btn">
                        <?php echo $image_url ? esc_html__('Change Image', 'infoplus-suite') : esc_html__('Upload Image', 'infoplus-suite'); ?>
                    </button>
                    <button type="button" class="button infoplus-remove-term-image-btn" style="<?php echo $image_url ? '' : 'display: none;'; ?> color: #a00;">
                        <?php esc_html_e('Remove', 'infoplus-suite'); ?>
                    </button>
                </div>
                <p class="description"><?php esc_html_e('This image will appear in dropdown selectors on the product page.', 'infoplus-suite'); ?></p>
            </td>
        </tr>

        <tr class="form-field infoplus-term-color-field">
            <th scope="row">
                <label for="infoplus_term_color"><?php esc_html_e('Farbe (Hex)', 'infoplus-suite'); ?></label>
            </th>
            <td>
                <input type="text" name="infoplus_term_color" id="infoplus_term_color" value="<?php echo esc_attr($color); ?>" class="infoplus-term-color" data-default-color="" />
                <p class="description"><?php esc_html_e('Optional. Colour swatch before the option name, e.g. #1F3A93. A term image wins over it.', 'infoplus-suite'); ?></p>
            </td>
        </tr>

        <tr class="form-field infoplus-term-tooltip-field">
            <th scope="row">
                <label for="infoplus_term_tooltip"><?php esc_html_e('Tooltip Text', 'infoplus-suite'); ?></label>
            </th>
            <td>
                <input type="text" name="infoplus_term_tooltip" id="infoplus_term_tooltip" value="<?php echo esc_attr($tooltip); ?>" style="width: 100%;" />
                <p class="description"><?php esc_html_e('Short text shown on hover over the info icon.', 'infoplus-suite'); ?></p>
            </td>
        </tr>

        <tr class="form-field infoplus-term-modal-field">
            <th scope="row">
                <label for="infoplus_term_modal_content"><?php esc_html_e('Modal Content', 'infoplus-suite'); ?></label>
            </th>
            <td>
                <textarea name="infoplus_term_modal_content" id="infoplus_term_modal_content" rows="4" style="width: 100%;"><?php echo esc_textarea($modal_content); ?></textarea>
                <p class="description"><?php esc_html_e('Extended description shown when clicking the info icon. Supports basic HTML.', 'infoplus-suite'); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Save term fields
     */
    public function saveTermFields($term_id, $taxonomy_id = null): void
    {
        // Save image ID
        if (isset($_POST['infoplus_term_image_id'])) {
            $image_id = absint($_POST['infoplus_term_image_id']);
            update_term_meta($term_id, '_infoplus_term_image_id', $image_id);
        }

        // Save tooltip
        if (isset($_POST['infoplus_term_tooltip'])) {
            $tooltip = sanitize_text_field($_POST['infoplus_term_tooltip']);
            update_term_meta($term_id, '_infoplus_term_tooltip', $tooltip);
        }

        // Save colour (hex, empty removes it)
        if (isset($_POST['infoplus_term_color'])) {
            $color = sanitize_hex_color(trim((string) wp_unslash($_POST['infoplus_term_color'])));
            if ($color) {
                update_term_meta($term_id, self::META_COLOR, $color);
            } else {
                delete_term_meta($term_id, self::META_COLOR);
            }
        }

        // Save modal content (allow basic HTML)
        if (isset($_POST['infoplus_term_modal_content'])) {
            $modal_content = wp_kses_post($_POST['infoplus_term_modal_content']);
            update_term_meta($term_id, '_infoplus_term_modal_content', $modal_content);
        }
    }

    /**
     * Add custom columns to term list table
     */
    public function addTermColumns($columns): array
    {
        $new_columns = [];
        
        foreach ($columns as $key => $value) {
            // Insert image column after checkbox
            if ($key === 'cb') {
                $new_columns[$key] = $value;
                $new_columns['infoplus_image'] = __('Image', 'infoplus-suite');
            } else {
                $new_columns[$key] = $value;
            }
        }
        
        return $new_columns;
    }

    /**
     * Render custom column content
     */
    public function renderTermColumn($content, $column_name, $term_id): string
    {
        if ($column_name === 'infoplus_image') {
            $image_id = get_term_meta($term_id, '_infoplus_term_image_id', true);
            
            if ($image_id) {
                $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                if ($image_url) {
                    return '<img src="' . esc_url($image_url) . '" style="max-width: 40px; max-height: 40px; border-radius: 4px;" />';
                }
            }
            
            return '<span class="dashicons dashicons-format-image" style="color: #ddd; font-size: 24px;"></span>';
        }
        
        return $content;
    }

    /**
     * Enqueue admin scripts for media uploader
     */
    public function enqueueAdminScripts(string $hook): void
    {
        // Only on taxonomy pages
        if (!in_array($hook, ['edit-tags.php', 'term.php'])) {
            return;
        }

        // Check if we're on a product attribute taxonomy
        $taxonomy = $_GET['taxonomy'] ?? '';
        if (!in_array($taxonomy, $this->targetTaxonomies)) {
            return;
        }

        wp_enqueue_media();
        // Colour picker for the "Farbe (Hex)" field.
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_add_inline_script('wp-color-picker', "jQuery(function($){ $('.infoplus-term-color').wpColorPicker(); });");

        // Register and enqueue our script
        wp_register_script(
            'infoplus-term-image-upload',
            '', // No file, inline script
            ['jquery', 'media-upload', 'media-views'],
            '1.0.0',
            true
        );
        
        wp_add_inline_script('infoplus-term-image-upload', $this->getUploaderScript());
        wp_enqueue_script('infoplus-term-image-upload');
    }

    /**
     * Get JavaScript for media uploader
     */
    private function getUploaderScript(): string
    {
        return "
        jQuery(document).ready(function($) {
            var mediaFrame;
            
            // Upload button click
            $(document).on('click', '.infoplus-upload-term-image-btn', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var wrapper = button.closest('.infoplus-term-image-wrapper');
                var imageInput = wrapper.find('#infoplus_term_image_id');
                var preview = wrapper.find('.infoplus-term-image-preview');
                var removeBtn = wrapper.find('.infoplus-remove-term-image-btn');
                
                // Create media frame
                mediaFrame = wp.media({
                    title: '" . esc_js(__('Select Term Image', 'infoplus-suite')) . "',
                    button: {
                        text: '" . esc_js(__('Use this image', 'infoplus-suite')) . "'
                    },
                    multiple: false
                });
                
                // On select
                mediaFrame.on('select', function() {
                    var attachment = mediaFrame.state().get('selection').first().toJSON();
                    
                    imageInput.val(attachment.id);
                    preview.find('img').attr('src', attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url);
                    preview.show();
                    button.text('" . esc_js(__('Change Image', 'infoplus-suite')) . "');
                    removeBtn.show();
                });
                
                mediaFrame.open();
            });
            
            // Remove button click
            $(document).on('click', '.infoplus-remove-term-image-btn', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var wrapper = button.closest('.infoplus-term-image-wrapper');
                var imageInput = wrapper.find('#infoplus_term_image_id');
                var preview = wrapper.find('.infoplus-term-image-preview');
                var uploadBtn = wrapper.find('.infoplus-upload-term-image-btn');
                
                imageInput.val('');
                preview.hide().find('img').attr('src', '');
                uploadBtn.text('" . esc_js(__('Upload Image', 'infoplus-suite')) . "');
                button.hide();
            });
            
            // Reset form on add term (after AJAX save)
            $(document).ajaxComplete(function(event, xhr, settings) {
                if (settings.data && settings.data.indexOf('action=add-tag') !== -1) {
                    var form = $('#addtag');
                    form.find('#infoplus_term_image_id').val('');
                    form.find('.infoplus-term-image-preview').hide().find('img').attr('src', '');
                    form.find('.infoplus-upload-term-image-btn').text('" . esc_js(__('Upload Image', 'infoplus-suite')) . "');
                    form.find('.infoplus-remove-term-image-btn').hide();
                    form.find('#infoplus_term_tooltip').val('');
                    form.find('#infoplus_term_modal_content').val('');
                }
            });
        });
        ";
    }
}
