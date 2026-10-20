<?php

namespace Infoplus\Suite\Admin;

use Infoplus\Suite\Contracts\ModuleInterface;

final class Dashboard
{
    /** @var ModuleInterface[] */
    private array $modules;

    public function __construct(array $modules)
    {
        $this->modules = $modules;
    }

    public function register(): void
    {
        add_action('admin_menu', function () {
            // Get the plugin URL for the icon
            $plugin_url = plugin_dir_url(dirname(dirname(__FILE__)));
            $icon_png = $plugin_url . 'assets/ikona-icon.png';

            add_menu_page(
                __('IKONA IFE', 'infoplus-suite'), // Page title
                __('IKONA IFE', 'infoplus-suite'), // Menu title
                'manage_options',
                'infoplus-suite',
                [$this, 'render'],
                 $icon_png,
                56
            );
        });

          // Global
    add_action('admin_head', [$this, 'addGlobalMenuIconStyles']);

    // Page-specific styles only on IKONA IFE screens
    add_action('admin_head', [$this, 'addAdminStyles']);
    }
	
	public function addGlobalMenuIconStyles(): void
{
    ?>
    <style>
        /* Ensure consistent alignment in the WP sidebar */
        #adminmenu li#toplevel_page_infoplus-suite .wp-menu-image { padding:0 !important; width:20px; }
        #adminmenu li#toplevel_page_infoplus-suite .wp-menu-image img {
            display:block; width:20px; height:20px; margin: -2px 0px 0px 8px; object-fit:contain;
        }
        #adminmenu li#toplevel_page_infoplus-suite:hover .wp-menu-image img,
        #adminmenu li#toplevel_page_infoplus-suite.current .wp-menu-image img,
        #adminmenu li#toplevel_page_infoplus-suite.wp-has-current-submenu .wp-menu-image img { opacity:1; }
    </style>
    <?php
}
	

public function addAdminStyles(): void
{
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'infoplus-suite') === false) return;
    ?>
    <style>
        .infoplus-suite-header {
            display:flex;align-items:center;gap:12px;margin-bottom:20px
        }
        .infoplus-suite-header-icon {
            width:32px;height:32px;object-fit:contain;margin: 4px 0px -4px 8px;
        }
        .infoplus-suite-header h1 {
            margin:0;line-height:1
        }

        /* Tab icons */
        .nav-tab-wrapper .nav-tab-icon {
            font-size:16px;
            vertical-align:middle;
            margin-right:4px;
            position:relative;
            top:1px;
            opacity:0.8;
        }
        .nav-tab-wrapper .nav-tab-active .nav-tab-icon {
            opacity:1;
        }
    </style>
    <?php
}


public function render(): void
{
    $plugin_url = plugin_dir_url(dirname(dirname(__FILE__)));
    $active = $_GET['tab'] ?? ($this->modules[0]->slug() ?? 'overview');

    echo '<div class="wrap">';
    echo '<div class="infoplus-suite-header">';
    echo '<img src="' . esc_url($plugin_url . 'assets/ikona-icon.ico') . '" alt="Ikona IFE" class="infoplus-suite-header-icon" />';
    echo '<h1>Ikona IFE</h1>';
    echo '</div>';

    echo '<h2 class="nav-tab-wrapper">';
    foreach ($this->modules as $m) {
        $slug  = $m->slug();
        $label = esc_html($m->label());
        $class = 'nav-tab ' . ($active === $slug ? 'nav-tab-active' : '');
        $url   = admin_url('admin.php?page=infoplus-suite&tab=' . $slug);

        // choose Dashicon
        $icon_html = '';
        if ($slug === 'editor-link-settings') {
            $icon_html = '<span class="dashicons dashicons-admin-settings nav-tab-icon"></span>'; 
        } elseif ($slug === 'ikona-ife') {
            $icon_html = '<span class="dashicons dashicons-update nav-tab-icon"></span>';
        } elseif ($slug === 'inquiry') {
            $icon_html = '<span class="dashicons dashicons-email nav-tab-icon"></span>';
        }

        echo '<a class="' . $class . '" href="' . esc_url($url) . '">' . $icon_html . $label . '</a>';
    }
    echo '</h2>';

    echo '<div class="infoplus-module-settings">';
    do_action('infoplus/module/settings/' . $active);
    echo '</div></div>';
}
}
