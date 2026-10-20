<?php
/**
 * Template: bulk price table, Staffelpreise (component "bulk-price-table").
 *
 * Override in a theme: copy to {theme}/infoplus-suite/bulk-price-table.php.
 * Rendered by Infoplus\Suite\View::render(), called from the BulkPriceTable
 * Elementor widget and from infoplus_suite_bulk_price_table(). The price grid
 * is filled by bulk-price-table.js (REST /infoplus/v1/products/prices per
 * variation SKU); it reads data-columns, data-ab-label and data-stueck-label and
 * needs .ibpt-skeleton and .ibpt-grid inside the root. Cells built by the JS
 * carry only the ibpt-* classes, not the ip-c-* ones.
 *
 * $args (built by Components::bulkPriceTable()):
 *   context        string  'elementor' or 'theme'
 *   columns        int     1 to 4
 *   skeleton_rows  int     1 to 6
 *   ab_label       string  Prefix label, default "ab"
 *   stueck_label   string  Unit label, default "Stück"
 *   is_preview     bool    Static demo tiers (Elementor editor) instead of the skeleton
 *   product        WC_Product|null  Not used by the default template
 *
 * Restyle hooks: root .ip-c-bulk-price-table[data-ip-component="bulk-price-table"],
 * parts .ip-c-bulk-price-table__skeleton, __table (price grid), and in the
 * preview only __cell, __label, __qty, __price.
 *
 * CSS custom properties (theme contract, defaults are the widget control
 * defaults; the plugin CSS does not read them, the theme maps them onto the
 * classes above, or onto .ibpt-* for the JS built cells).
 *   --ip-bt-columns            2        grid-template-columns: repeat(n, 1fr)
 *   --ip-bt-col-gap            10px
 *   --ip-bt-row-gap            8px
 *   --ip-bt-cell-bg            (none)
 *   --ip-bt-cell-padding       10px 14px
 *   --ip-bt-cell-radius        6px
 *   --ip-bt-label-color        #666666
 *   --ip-bt-qty-color          #222222
 *   --ip-bt-price-color        #e91e8c
 *   --ip-bt-active-bg          #f7f7f7
 *   --ip-bt-active-qty-color   #e91e8c
 *   --ip-bt-active-price-color #e91e8c
 *
 * Whitespace below mirrors the former BulkPriceTable::render() output byte for byte.
 *
 * @package Infoplus\Suite
 */

defined('ABSPATH') || exit;

$columns      = $args['columns'];
$skeletonRows = $args['skeleton_rows'];
$abLabel      = $args['ab_label'];
$stueckLabel  = $args['stueck_label'];

// Static demo data shown in the Elementor editor
$demoTiers = [
    ['from' => '10',  'to' => '19',  'value' => '38.95'],
    ['from' => '20',  'to' => '49',  'value' => '31.25'],
    ['from' => '50',  'to' => '99',  'value' => '24.45'],
    ['from' => '100', 'to' => '249', 'value' => '18.05'],
    ['from' => '250', 'to' => '499', 'value' => '16.28'],
    ['from' => '500', 'to' => '',    'value' => '14.92'],
];

?>
        <div class="infoplus-bulk-price-table ip-c-bulk-price-table" data-ip-component="bulk-price-table"
             data-columns="<?php echo esc_attr($columns); ?>"
             data-ab-label="<?php echo esc_attr($abLabel); ?>"
             data-stueck-label="<?php echo esc_attr($stueckLabel); ?>">

            <?php if ($args['is_preview']): ?>

                <?php /* Editor preview — static demo grid so the designer can see the layout */ ?>
                <div class="ibpt-grid ibpt-grid--preview ip-c-bulk-price-table__table">
                    <?php foreach ($demoTiers as $i => $tier):
                        $from     = (int) $tier['from'];
                        $to       = $tier['to'] !== '' ? (int) $tier['to'] : null;
                        $price    = number_format((float) $tier['value'], 2, ',', '.');
                        $qtyText  = $to === null ? ($from . '+') : (string) $from;
                        $isActive = $i === 0;
                    ?>
                        <div class="ibpt-cell<?php echo $isActive ? ' ibpt-cell--active' : ''; ?> ip-c-bulk-price-table__cell"
                             data-from="<?php echo esc_attr($from); ?>"
                             data-to="<?php echo esc_attr($to ?? ''); ?>">
                            <span class="ibpt-cell__label ip-c-bulk-price-table__label"><?php echo esc_html($abLabel); ?></span>
                            <span class="ibpt-cell__qty ip-c-bulk-price-table__qty"><?php echo esc_html($qtyText); ?></span>
                            <span class="ibpt-cell__label ip-c-bulk-price-table__label"><?php echo esc_html($stueckLabel); ?></span>
                            <span class="ibpt-cell__price ip-c-bulk-price-table__price"><?php echo esc_html($price); ?> €</span>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>

                <?php /* Loading skeleton — JS hides this and shows ibpt-grid once prices arrive */ ?>
                <div class="ibpt-skeleton ip-c-bulk-price-table__skeleton">
                    <?php for ($i = 0; $i < ($columns * $skeletonRows); $i++): ?>
                        <div class="ibpt-skeleton__cell"></div>
                    <?php endfor; ?>
                </div>

                <?php /* Price grid — populated entirely by bulk-price-table.js */ ?>
                <div class="ibpt-grid ip-c-bulk-price-table__table" style="display:none;"></div>

            <?php endif; ?>

        </div>
        <?php
