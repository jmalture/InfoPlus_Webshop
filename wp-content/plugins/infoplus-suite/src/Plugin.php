<?php
namespace Infoplus\Suite;

use Infoplus\Suite\Admin\Dashboard;
use Infoplus\Suite\Contracts\ModuleInterface;
use Infoplus\Suite\Modules\WooSingleProductAddons\Module as AddonsModule;
use Infoplus\Suite\Modules\WooProductVariationFields\Module as VariationsModule;
use Infoplus\Suite\Modules\EditorLinkSettings\Module as EditorLinkSettingsModule;
use Infoplus\Suite\Modules\IkonaIFE\Module as IkonaIFEModule;
use Infoplus\Suite\Modules\ElementorUserAvatar\Module as ElementorUserAvatarModule;
use Infoplus\Suite\Modules\UserDashboard\Module as UserDashboardModule;
use Infoplus\Suite\Modules\UserOrders\Module as UserOrdersModule;
use Infoplus\Suite\Modules\ProductDetailSuite\Module as ProductDetailSuiteModule;
use Infoplus\Suite\Modules\Custom404\Module as Custom404Module;
use Infoplus\Suite\Modules\CatalogImport\Module as CatalogImportModule;
use Infoplus\Suite\Modules\Inquiry\Module as InquiryModule;

final class Plugin
{
    public const VERSION = '0.10.31';

    private string $file;
    private array $modules = [];
    private array $visibleModules = [];

    public function __construct(string $file) { $this->file = $file; }

    public function boot(): void
    {
        $this->defineConstants();
        require_once INFOPLUS_SUITE_PATH . 'src/template-functions.php';
        $this->registerModules();
        $this->registerAdmin();
        $this->registerHooks();
    }

    private function defineConstants(): void
    {
        define('INFOPLUS_SUITE_FILE', $this->file);
        define('INFOPLUS_SUITE_PATH', plugin_dir_path($this->file));
        define('INFOPLUS_SUITE_URL',  plugin_dir_url($this->file));
    }

    private function registerModules(): void
    {
        // Register ALL modules (they all need to run)
   $this->modules = [
        new AddonsModule(),              // Frontend functionality (runs in background)
        new VariationsModule(),          // Product fields (runs in background)
        new EditorLinkSettingsModule(),  // Editor settings (visible tab)
        new IkonaIFEModule(),            // Sync functionality (visible tab)
        new ElementorUserAvatarModule(), // Elementor user avatar integration (runs in background)
        new UserDashboardModule(),       //Backed functionality for the user dashboard
         new UserOrdersModule(),        // Orders display and management (runs in background)
        new ProductDetailSuiteModule(), // Product detail page widgets (runs in background)
        new Custom404Module(),          // Custom branded 404 page (runs in background)
        new CatalogImportModule(),      // WooCommerce → Katalog-Import (CSV catalog import)
        new InquiryModule(),            // Inquiry form "Individuelle Anfrage": REST, CPT Anfragen, mails (settings tab)
    ];

        // Only these modules should have visible tabs in the dashboard
        $this->visibleModules = [
            'editor-link-settings',  // Editor Link Settings tab
            'ikona-ife',             // Ikona IFE tab
            'inquiry',               // Anfragen tab (inquiry form settings)
        ];

        // Register all modules (hooks, functionality, etc.)
        foreach ($this->modules as $module) {
            if ($module instanceof ModuleInterface) {
                $module->register();
            }
        }
    }

    private function registerAdmin(): void
    {
        // Pass only visible modules to Dashboard
        $visibleModuleObjects = array_filter($this->modules, function($module) {
            return in_array($module->slug(), $this->visibleModules);
        });

        // CRITICAL FIX: Reindex array to start from 0
        $visibleModuleObjects = array_values($visibleModuleObjects);

        (new Dashboard($visibleModuleObjects))->register();
    }

    private function registerHooks(): void
    {
        // Shared hooks, i18n, etc.
        add_action('init', function() {
            load_plugin_textdomain('infoplus-suite', false, dirname(plugin_basename(INFOPLUS_SUITE_FILE)) . '/languages');
        });
    }
}