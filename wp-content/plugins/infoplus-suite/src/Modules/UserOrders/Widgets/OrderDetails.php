<?php
namespace Infoplus\Suite\Modules\UserOrders\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Box_Shadow;

if (!defined('ABSPATH')) exit;

/**
 * Order Details Widget
 * 
 * Displays detailed information about a single WooCommerce order.
 * Reads order ID from URL (e.g., /order-3202/)
 */
class OrderDetails extends Widget_Base
{
    public function get_name(): string
    {
        return 'infoplus-order-details';
    }

    public function get_title(): string
    {
        return __('Order Details', 'infoplus-suite');
    }

    public function get_icon(): string
    {
        return 'eicon-single-post';
    }

    public function get_categories(): array
    {
        return ['general', 'woocommerce-elements'];
    }

    public function get_keywords(): array
    {
        return ['order', 'details', 'woocommerce', 'invoice', 'infoplus'];
    }

    protected function register_controls(): void
    {
        // Content Section
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Content', 'infoplus-suite'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'order_id_source',
            [
                'label' => __('Order ID Source', 'infoplus-suite'),
                'type' => Controls_Manager::SELECT,
                'default' => 'url',
                'options' => [
                    'url' => __('From URL (/order/{id}/)', 'infoplus-suite'),
                    'query' => __('From Query Parameter (?order_id=)', 'infoplus-suite'),
                    'manual' => __('Manual (for testing)', 'infoplus-suite'),
                ],
            ]
        );

        $this->add_control(
            'manual_order_id',
            [
                'label' => __('Order ID', 'infoplus-suite'),
                'type' => Controls_Manager::NUMBER,
                'default' => '',
                'condition' => ['order_id_source' => 'manual'],
            ]
        );

        // Invoice button control — hidden for now, keeping for future use
        // $this->add_control(
        //     'show_invoice_button',
        //     [
        //         'label' => __('Show Invoice Button', 'infoplus-suite'),
        //         'type' => Controls_Manager::SWITCHER,
        //         'default' => 'yes',
        //     ]
        // );

        $this->add_control(
            'show_back_button',
            [
                'label' => __('Show Back Button', 'infoplus-suite'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'back_button_text',
            [
                'label' => __('Back Button Text', 'infoplus-suite'),
                'type' => Controls_Manager::TEXT,
                'default' => '← Zurück zur Übersicht',
                'condition' => ['show_back_button' => 'yes'],
            ]
        );

        $this->add_control(
            'back_button_url',
            [
                'label' => __('Back Button URL', 'infoplus-suite'),
                'type' => Controls_Manager::TEXT,
                'default' => '/account/',
                'condition' => ['show_back_button' => 'yes'],
            ]
        );

        $this->add_control(
            'show_email_button',
            [
                'label' => __('Show Email Button', 'infoplus-suite'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_print_button',
            [
                'label' => __('Show Print Button', 'infoplus-suite'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        // Invoice button text control — hidden for now, keeping for future use
        // $this->add_control(
        //     'invoice_button_text',
        //     [
        //         'label' => __('Invoice Button Text', 'infoplus-suite'),
        //         'type' => Controls_Manager::TEXT,
        //         'default' => 'Rechnung herunterladen',
        //         'condition' => ['show_invoice_button' => 'yes'],
        //     ]
        // );

        $this->add_control(
            'email_button_text',
            [
                'label' => __('Email Button Text', 'infoplus-suite'),
                'type' => Controls_Manager::TEXT,
                'default' => 'E-Mail-Kunde',
                'condition' => ['show_email_button' => 'yes'],
            ]
        );

        $this->add_control(
            'print_button_text',
            [
                'label' => __('Print Button Text', 'infoplus-suite'),
                'type' => Controls_Manager::TEXT,
                'default' => 'Druckauftrag',
                'condition' => ['show_print_button' => 'yes'],
            ]
        );

        $this->add_control(
            'not_found_message',
            [
                'label' => __('Order Not Found Message', 'infoplus-suite'),
                'type' => Controls_Manager::TEXT,
                'default' => 'Bestellung nicht gefunden.',
            ]
        );

        $this->add_control(
            'not_logged_in_message',
            [
                'label' => __('Not Logged In Message', 'infoplus-suite'),
                'type' => Controls_Manager::TEXT,
                'default' => 'Bitte melden Sie sich an, um Ihre Bestellung zu sehen.',
            ]
        );

        $this->end_controls_section();

        // Labels Section
        $this->start_controls_section(
            'section_labels',
            [
                'label' => __('Labels', 'infoplus-suite'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'label_date',
            [
                'label' => __('Date Label', 'infoplus-suite'),
                'type' => Controls_Manager::TEXT,
                'default' => 'Datum:',
            ]
        );

        $this->add_control(
            'label_payment',
            [
                'label' => __('Payment Label', 'infoplus-suite'),
                'type' => Controls_Manager::TEXT,
                'default' => 'Zahlung:',
            ]
        );

        $this->add_control(
            'label_customer',
            [
                'label' => __('Customer Section Title', 'infoplus-suite'),
                'type' => Controls_Manager::TEXT,
                'default' => 'Kunde',
            ]
        );

        $this->add_control(
            'label_shipping',
            [
                'label' => __('Shipping Address Title', 'infoplus-suite'),
                'type' => Controls_Manager::TEXT,
                'default' => 'Versandadresse',
            ]
        );

        $this->add_control(
            'label_billing',
            [
                'label' => __('Billing Address Title', 'infoplus-suite'),
                'type' => Controls_Manager::TEXT,
                'default' => 'Rechnungsadresse',
            ]
        );

        $this->add_control(
            'label_items',
            [
                'label' => __('Items Section Title', 'infoplus-suite'),
                'type' => Controls_Manager::TEXT,
                'default' => 'Artikel bestellen',
            ]
        );

        $this->add_control(
            'label_subtotal',
            [
                'label' => __('Subtotal Label', 'infoplus-suite'),
                'type' => Controls_Manager::TEXT,
                'default' => 'Zwischensumme:',
            ]
        );

        $this->add_control(
            'label_shipping_cost',
            [
                'label' => __('Shipping Cost Label', 'infoplus-suite'),
                'type' => Controls_Manager::TEXT,
                'default' => 'Versand:',
            ]
        );

        $this->add_control(
            'label_total',
            [
                'label' => __('Total Label', 'infoplus-suite'),
                'type' => Controls_Manager::TEXT,
                'default' => 'Total',
            ]
        );

        $this->end_controls_section();

        // Header Style Section
        $this->start_controls_section(
            'section_header_style',
            [
                'label' => __('Header Style', 'infoplus-suite'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __('Title Color', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => [
                    '{{WRAPPER}} .infoplus-order-title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'label' => __('Title Typography', 'infoplus-suite'),
                'selector' => '{{WRAPPER}} .infoplus-order-title',
            ]
        );

        $this->add_control(
            'status_completed_color',
            [
                'label' => __('Status: Completed', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#22c55e',
            ]
        );

        $this->add_control(
            'status_processing_color',
            [
                'label' => __('Status: Processing', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#3b82f6',
            ]
        );

        $this->add_control(
            'status_pending_color',
            [
                'label' => __('Status: Pending/On-Hold', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#f59e0b',
            ]
        );

        $this->add_control(
            'status_cancelled_color',
            [
                'label' => __('Status: Cancelled/Failed', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ef4444',
            ]
        );

        $this->end_controls_section();

        // Buttons Style Section
        $this->start_controls_section(
            'section_buttons_style',
            [
                'label' => __('Buttons Style', 'infoplus-suite'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'primary_btn_bg',
            [
                'label' => __('Primary Button Background', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#e91e8c',
                'selectors' => [
                    '{{WRAPPER}} .infoplus-btn-primary' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'primary_btn_color',
            [
                'label' => __('Primary Button Text', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .infoplus-btn-primary' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'secondary_btn_color',
            [
                'label' => __('Secondary Button Color', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#e91e8c',
                'selectors' => [
                    '{{WRAPPER}} .infoplus-btn-secondary' => 'color: {{VALUE}}; border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'btn_border_radius',
            [
                'label' => __('Button Border Radius', 'infoplus-suite'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 30,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 6,
                ],
                'selectors' => [
                    '{{WRAPPER}} .infoplus-btn' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Items Style Section
        $this->start_controls_section(
            'section_items_style',
            [
                'label' => __('Items Style', 'infoplus-suite'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'item_background',
            [
                'label' => __('Item Background', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#f8f8f8',
                'selectors' => [
                    '{{WRAPPER}} .infoplus-order-item' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'item_border_radius',
            [
                'label' => __('Item Border Radius', 'infoplus-suite'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 20,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 8,
                ],
                'selectors' => [
                    '{{WRAPPER}} .infoplus-order-item' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'item_price_color',
            [
                'label' => __('Item Price Color', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#e91e8c',
                'selectors' => [
                    '{{WRAPPER}} .infoplus-item-price' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'total_price_color',
            [
                'label' => __('Total Price Color', 'infoplus-suite'),
                'type' => Controls_Manager::COLOR,
                'default' => '#e91e8c',
                'selectors' => [
                    '{{WRAPPER}} .infoplus-order-grand-total' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Get the order ID from various sources
     */
    private function getOrderId(): ?int
    {
        $settings = $this->get_settings_for_display();
        $source = $settings['order_id_source'];

        switch ($source) {
            case 'manual':
                return !empty($settings['manual_order_id']) ? (int) $settings['manual_order_id'] : null;

            case 'query':
                return isset($_GET['order_id']) ? (int) $_GET['order_id'] : null;

            case 'url':
            default:
                // First try the custom query var (from rewrite rule)
                $order_id = get_query_var('infoplus_order_id');
                if (!empty($order_id)) {
                    return (int) $order_id;
                }
                
                // Fallback: parse URL like /order/3202/
                $uri = $_SERVER['REQUEST_URI'] ?? '';
                if (preg_match('/\/order\/(\d+)/i', $uri, $matches)) {
                    return (int) $matches[1];
                }
                return null;
        }
    }

    /**
     * Get the invoice PDF URL with proper nonce for WP Overnight PDF plugin
     */
    private function getInvoiceUrl(\WC_Order $order): string
    {
        $order_id = $order->get_id();
        
        // Check if WooCommerce PDF Invoices & Packing Slips plugin is active (WP Overnight)
        if (function_exists('wcpdf_get_document')) {
            $document = wcpdf_get_document('invoice', $order);
            
            if ($document && $document->exists()) {
                // Try the proper method first
                if (method_exists($document, 'get_pdf_link')) {
                    $link = $document->get_pdf_link();
                    if (!empty($link)) {
                        return $link;
                    }
                }
            }
            
            // Use the My Account endpoint with nonce (this is the proper way for frontend)
            // This creates a link that works for logged-in users
            $pdf_url = add_query_arg([
                'action' => 'generate_wpo_wcpdf',
                'document_type' => 'invoice',
                'order_ids' => $order_id,
                'my_account_pdf' => 'true',
                '_wpnonce' => wp_create_nonce('generate_wpo_wcpdf'),
            ], admin_url('admin-ajax.php'));
            
            return $pdf_url;
        }

        // Alternative fallback: WooCommerce's built-in order view URL
        return $order->get_view_order_url();
    }

    /**
     * Get payment method label in German
     */
    private function getPaymentMethodLabel(string $method): string
    {
        $labels = [
            'bacs' => 'Überweisung',
            'cod' => 'Nachnahme',
            'cheque' => 'Scheck',
            'paypal' => 'PayPal',
            'stripe' => 'Kreditkarte',
            'klarna' => 'Klarna',
        ];

        return $labels[$method] ?? ucfirst($method);
    }

    /**
     * Get status label in German
     */
    private function getStatusLabel(string $status): string
    {
        $labels = [
            'pending' => 'Ausstehend',
            'processing' => 'In Bearbeitung',
            'on-hold' => 'In Wartestellung',
            'completed' => 'Abgeschlossen',
            'cancelled' => 'Storniert',
            'refunded' => 'Erstattet',
            'failed' => 'Fehlgeschlagen',
        ];

        return $labels[$status] ?? ucfirst($status);
    }

    /**
     * Get status color based on settings
     */
    private function getStatusColor(string $status, array $settings): string
    {
        switch ($status) {
            case 'completed':
                return $settings['status_completed_color'];
            case 'processing':
                return $settings['status_processing_color'];
            case 'pending':
            case 'on-hold':
                return $settings['status_pending_color'];
            case 'cancelled':
            case 'failed':
            case 'refunded':
                return $settings['status_cancelled_color'];
            default:
                return '#666666';
        }
    }

    /**
     * Format address array to display string
     */
    private function formatAddress(array $address): array
    {
        $lines = [];
        
        $name = trim(($address['first_name'] ?? '') . ' ' . ($address['last_name'] ?? ''));
        if ($name) $lines[] = $name;
        
        if (!empty($address['company'])) $lines[] = $address['company'];
        if (!empty($address['address_1'])) $lines[] = $address['address_1'];
        if (!empty($address['address_2'])) $lines[] = $address['address_2'];
        
        $city_line = trim(($address['city'] ?? '') . ', ' . ($address['postcode'] ?? ''));
        $city_line = trim($city_line, ', ');
        if ($city_line) $lines[] = $city_line;
        
        if (!empty($address['country'])) {
            $country = WC()->countries->countries[$address['country']] ?? $address['country'];
            $lines[] = $country;
        }
        
        return $lines;
    }

    /**
     * Get product variation/model description
     */
    private function getProductModelDescription($item): string
    {
        $meta_data = $item->get_meta_data();
        $parts = [];

        foreach ($meta_data as $meta) {
            $key = $meta->key;
            $value = $meta->value;

            // Skip internal meta keys
            if (strpos($key, '_') === 0) continue;
            if (in_array($key, ['_reduced_stock', '_restock_refunded_items'])) continue;

            // Skip if value is not a string or is too long
            if (!is_string($value) || strlen($value) > 200) continue;

            // Format the key nicely
            $label = wc_attribute_label($key);
            $parts[] = "{$label}: {$value}";
        }

        return implode(', ', $parts);
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        // Check if user is logged in
        if (!is_user_logged_in()) {
            echo '<div class="infoplus-order-not-found">';
            echo '<p>' . esc_html($settings['not_logged_in_message']) . '</p>';
            echo '</div>';
            return;
        }

        // Get order ID
        $order_id = $this->getOrderId();

        if (!$order_id) {
            echo '<div class="infoplus-order-not-found">';
            echo '<p>' . esc_html($settings['not_found_message']) . '</p>';
            echo '</div>';
            return;
        }

        // Get the order
        $order = wc_get_order($order_id);

        if (!$order) {
            echo '<div class="infoplus-order-not-found">';
            echo '<p>' . esc_html($settings['not_found_message']) . '</p>';
            echo '</div>';
            return;
        }

        // Get order data
        $order_number = $order->get_order_number();
        $order_date = $order->get_date_created();
        $status = $order->get_status();
        $status_label = $this->getStatusLabel($status);
        $status_color = $this->getStatusColor($status, $settings);
        $payment_method = $this->getPaymentMethodLabel($order->get_payment_method());
        
        // Customer info
        $billing = $order->get_address('billing');
        $shipping = $order->get_address('shipping');
        $customer_name = trim($billing['first_name'] . ' ' . $billing['last_name']);
        $customer_email = $order->get_billing_email();
        $customer_phone = $order->get_billing_phone();
        
        // Get formatted addresses
        $billing_lines = $this->formatAddress($billing);
        $shipping_lines = $this->formatAddress($shipping);
        
        // URLs
        $invoice_url = $this->getInvoiceUrl($order);
        $mailto_url = 'mailto:' . $customer_email;

        // Items
        $items = $order->get_items();
        ?>

        <div class="infoplus-order-details" data-order-id="<?php echo esc_attr($order_id); ?>">

            <?php if ($settings['show_back_button'] === 'yes'): ?>
            <div class="infoplus-order-back">
                <a href="<?php echo esc_url($settings['back_button_url']); ?>" class="infoplus-back-btn">
                    <?php echo esc_html($settings['back_button_text']); ?>
                </a>
            </div>
            <?php endif; ?>

            <!-- Header -->
            <div class="infoplus-order-header">
                <div class="infoplus-order-header-left">
                    <h1 class="infoplus-order-title">Bestellung #<?php echo esc_html($order_number); ?></h1>
                    <div class="infoplus-order-meta">
                        <span class="infoplus-order-date">
                            <strong><?php echo esc_html($settings['label_date']); ?></strong> 
                            <?php echo esc_html($order_date->date_i18n('j. F Y, H:i')); ?> Uhr
                        </span>
                        <span class="infoplus-order-payment">
                            <strong><?php echo esc_html($settings['label_payment']); ?></strong> 
                            <?php echo esc_html($payment_method); ?>
                        </span>
                    </div>
                    
                    <div class="infoplus-order-actions">
                        <?php /* Invoice button — hidden for now, keeping for future use
                        <?php if ($settings['show_invoice_button'] === 'yes'): ?>
                        <a href="<?php echo esc_url($invoice_url); ?>"
                           class="infoplus-btn infoplus-btn-primary"
                           target="_blank"
                           download>
                            <?php echo esc_html($settings['invoice_button_text']); ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left: 8px;">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="7 10 12 15 17 10"/>
                                <line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                        </a>
                        <?php endif; ?>
                        */ ?>
                        
                        <?php if ($settings['show_email_button'] === 'yes'): ?>
                        <a href="<?php echo esc_url($mailto_url); ?>" class="infoplus-btn infoplus-btn-secondary">
                            <?php echo esc_html($settings['email_button_text']); ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left: 8px;">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($settings['show_print_button'] === 'yes'): ?>
                        <button type="button" class="infoplus-btn infoplus-btn-secondary infoplus-print-btn">
                            <?php echo esc_html($settings['print_button_text']); ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left: 8px;">
                                <polyline points="6 9 6 2 18 2 18 9"/>
                                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                                <rect x="6" y="14" width="12" height="8"/>
                            </svg>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="infoplus-order-header-right">
                    <span class="infoplus-order-status" style="color: <?php echo esc_attr($status_color); ?>">
                        <?php echo esc_html($status_label); ?>
                    </span>
                </div>
            </div>

            <!-- Customer Information -->
            <div class="infoplus-order-customer">
                <h2 class="infoplus-section-title"><?php echo esc_html($settings['label_customer']); ?></h2>
                
                <div class="infoplus-customer-grid">
                    <div class="infoplus-customer-info">
                        <h3 class="infoplus-customer-name"><?php echo esc_html($customer_name); ?></h3>
                        <?php if ($customer_email): ?>
                        <p><strong>E-Mail:</strong> <a href="mailto:<?php echo esc_attr($customer_email); ?>"><?php echo esc_html($customer_email); ?></a></p>
                        <?php endif; ?>
                        <?php if ($customer_phone): ?>
                        <p><strong>Telefon:</strong> <a href="tel:<?php echo esc_attr($customer_phone); ?>"><?php echo esc_html($customer_phone); ?></a></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="infoplus-shipping-address">
                        <h3><?php echo esc_html($settings['label_shipping']); ?></h3>
                        <?php foreach ($shipping_lines as $line): ?>
                        <p><?php echo esc_html($line); ?></p>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="infoplus-billing-address">
                        <h3><?php echo esc_html($settings['label_billing']); ?></h3>
                        <?php foreach ($billing_lines as $line): ?>
                        <p><?php echo esc_html($line); ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="infoplus-order-items-section">
                <h2 class="infoplus-section-title"><?php echo esc_html($settings['label_items']); ?></h2>
                
                <div class="infoplus-order-items">
                    <?php foreach ($items as $item_id => $item): 
                        $product = $item->get_product();
                        $product_id = $item->get_product_id();
                        $product_name = $item->get_name();
                        $quantity = $item->get_quantity();
                        $total = $order->get_formatted_line_subtotal($item);
                        $sku = $product ? $product->get_sku() : '';
                        $model_desc = $this->getProductModelDescription($item);
                        
                        // Get product image (prefer model image from order meta)
                        $image_url = $item->get_meta('_infoplus_model_image_url');
                        if (!$image_url && $product) {
                            $image_id = $product->get_image_id();
                            if ($image_id) {
                                $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                            }
                        }
                        
                        // Get product URL
                        $product_url = $product ? get_permalink($product->get_id()) : '#';
                    ?>
                    <a href="<?php echo esc_url($product_url); ?>" class="infoplus-order-item" target="_blank">
                        <?php if ($image_url): ?>
                        <div class="infoplus-item-image">
                            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($product_name); ?>">
                        </div>
                        <?php endif; ?>
                        
                        <div class="infoplus-item-details">
                            <h4 class="infoplus-item-name"><?php echo esc_html($product_name); ?> <?php echo esc_html($quantity); ?>x</h4>
                            
                            <?php if ($sku): ?>
                            <p class="infoplus-item-sku"><strong>Sku:</strong> <?php echo esc_html($sku); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($model_desc): ?>
                            <p class="infoplus-item-model"><strong>Model:</strong> <?php echo esc_html($model_desc); ?></p>
                            <?php endif; ?>
                            
                            <p class="infoplus-item-qty"><strong>Menge:</strong> <?php echo esc_html($quantity); ?></p>
                        </div>
                        
                        <div class="infoplus-item-price">
                            <?php echo wp_kses_post($total); ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Order Totals -->
            <div class="infoplus-order-totals">
                <div class="infoplus-totals-row">
                    <span class="infoplus-totals-label"><?php echo esc_html($settings['label_subtotal']); ?></span>
                    <span class="infoplus-totals-value"><?php echo wp_kses_post($order->get_subtotal_to_display()); ?></span>
                </div>
                <div class="infoplus-totals-row">
                    <span class="infoplus-totals-label"><?php echo esc_html($settings['label_shipping_cost']); ?></span>
                    <span class="infoplus-totals-value"><?php echo wp_kses_post($order->get_shipping_to_display()); ?></span>
                </div>
                <div class="infoplus-totals-row infoplus-totals-total">
                    <span class="infoplus-totals-label"><?php echo esc_html($settings['label_total']); ?></span>
                    <span class="infoplus-totals-value infoplus-order-grand-total"><?php echo wp_kses_post($order->get_formatted_order_total()); ?></span>
                </div>
            </div>
        </div>

        <style>
        .infoplus-order-details {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Back Button */
        .infoplus-order-back {
            margin-bottom: 1.5rem;
        }

        .infoplus-back-btn {
            display: inline-flex;
            align-items: center;
            font-size: 0.95rem;
            font-weight: 500;
            color: #e91e8c;
            text-decoration: none;
            transition: opacity 0.2s;
        }

        .infoplus-back-btn:hover {
            opacity: 0.75;
            color: #e91e8c;
        }

        /* Header */
        .infoplus-order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .infoplus-order-title {
            font-size: 2rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
            color: #333;
        }

        .infoplus-order-meta {
            display: flex;
            gap: 2rem;
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .infoplus-order-status {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .infoplus-order-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .infoplus-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.25rem;
            font-size: 0.95rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            border-radius: 6px;
        }

        .infoplus-btn-primary {
            background-color: #e91e8c;
            color: white;
            border: none;
        }

        .infoplus-btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-1px);
            color: white;
        }

        .infoplus-btn-secondary {
            background: transparent;
            color: #e91e8c;
            border: 1px solid #e91e8c;
        }

        .infoplus-btn-secondary:hover {
            background: rgba(233, 30, 140, 0.05);
            color: #e91e8c;
        }

        /* Customer Section */
        .infoplus-section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0 0 1rem 0;
            color: #333;
        }

        .infoplus-customer-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
        }

        .infoplus-customer-info h3,
        .infoplus-shipping-address h3,
        .infoplus-billing-address h3 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0 0 0.5rem 0;
            color: #333;
        }

        .infoplus-customer-info p,
        .infoplus-shipping-address p,
        .infoplus-billing-address p {
            margin: 0.25rem 0;
            color: #666;
            font-size: 0.95rem;
        }

        .infoplus-customer-info a,
        .infoplus-shipping-address a,
        .infoplus-billing-address a {
            color: inherit;
            text-decoration: none;
        }

        .infoplus-customer-info a:hover {
            color: #e91e8c;
        }

        /* Order Items */
        .infoplus-order-items-section {
            margin-top: 2rem;
        }

        .infoplus-order-items {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .infoplus-order-item {
            display: flex;
            align-items: flex-start;
            gap: 1.25rem;
            padding: 1.25rem;
            background: #f8f8f8;
            border-radius: 8px;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s ease;
        }

        .infoplus-order-item:hover {
            background: #f0f0f0;
            transform: translateX(4px);
        }

        .infoplus-item-image {
            flex-shrink: 0;
            width: 70px;
            height: 70px;
        }

        .infoplus-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 6px;
        }

        .infoplus-item-details {
            flex: 1;
            min-width: 0;
        }

        .infoplus-item-name {
            font-size: 1rem;
            font-weight: 600;
            margin: 0 0 0.5rem 0;
            color: #333;
        }

        .infoplus-item-sku,
        .infoplus-item-model,
        .infoplus-item-qty {
            font-size: 0.9rem;
            color: #666;
            margin: 0.25rem 0;
        }

        .infoplus-item-price {
            font-size: 1.1rem;
            font-weight: 600;
            color: #e91e8c;
            white-space: nowrap;
        }

        /* Order Totals */
        .infoplus-order-totals {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e0e0e0;
        }

        .infoplus-totals-row {
            display: flex;
            justify-content: flex-end;
            gap: 2rem;
            padding: 0.5rem 0;
        }

        .infoplus-totals-label {
            color: #666;
            font-size: 0.95rem;
        }

        .infoplus-totals-value {
            min-width: 100px;
            text-align: right;
            font-size: 0.95rem;
        }

        .infoplus-totals-total {
            padding-top: 1rem;
            margin-top: 0.5rem;
        }

        .infoplus-totals-total .infoplus-totals-label {
            font-weight: 700;
            color: #333;
            font-size: 1rem;
        }

        .infoplus-order-grand-total {
            font-size: 1.5rem;
            font-weight: 700;
            color: #e91e8c;
        }

        /* Not Found */
        .infoplus-order-not-found {
            text-align: center;
            padding: 4rem 2rem;
            color: #666;
        }

        /* Print Styles */
        @media print {
            .infoplus-order-actions {
                display: none !important;
            }
            
            .infoplus-order-item:hover {
                transform: none;
                background: #f8f8f8;
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .infoplus-order-header {
                flex-direction: column;
            }

            .infoplus-order-header-right {
                order: -1;
            }

            .infoplus-customer-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .infoplus-order-meta {
                flex-direction: column;
                gap: 0.5rem;
            }

            .infoplus-order-actions {
                flex-direction: column;
            }

            .infoplus-btn {
                width: 100%;
                justify-content: center;
            }

            .infoplus-order-item {
                flex-direction: column;
            }

            .infoplus-item-image {
                width: 100%;
                height: auto;
                aspect-ratio: 1;
                max-width: 120px;
            }

            .infoplus-item-price {
                margin-top: 0.5rem;
            }
        }
        </style>

        <script>
        (function() {
            // Print button functionality
            document.querySelectorAll('.infoplus-print-btn').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.print();
                });
            });
        })();
        </script>
        <?php
    }
}
