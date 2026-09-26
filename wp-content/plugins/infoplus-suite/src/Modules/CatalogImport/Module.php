<?php
namespace Infoplus\Suite\Modules\CatalogImport;

use Infoplus\Suite\Contracts\ModuleInterface;

if (!defined('ABSPATH')) exit;

/**
 * Catalog import: WooCommerce → Katalog-Import.
 *
 * 1. Upload the CSV from import-mapping/build_import.py → dry run (Planner), nothing written.
 * 2. Apply → Runner works through the plan in small AJAX steps with a progress bar.
 *
 * The plan is stored as JSON in uploads/infoplus-catalog-import/ (not web readable),
 * progress and the dry-run summary in options, so a closed tab can resume.
 */
final class Module implements ModuleInterface
{
    private const CAP = 'manage_woocommerce';
    private const NONCE = 'infoplus_catalog_import';
    private const OPT_STATE = 'infoplus_catalog_import_state';
    private const OPT_SUMMARY = 'infoplus_catalog_import_summary';
    private const PAGE = 'infoplus-catalog-import';

    public function slug(): string  { return 'catalog-import'; }
    public function label(): string { return __('Katalog-Import', 'infoplus-suite'); }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu'], 99);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_ajax_infoplus_catalog_plan', [$this, 'ajaxPlan']);
        add_action('wp_ajax_infoplus_catalog_step', [$this, 'ajaxStep']);
        add_action('wp_ajax_infoplus_catalog_reset', [$this, 'ajaxReset']);
    }

    public function addMenu(): void
    {
        add_submenu_page('woocommerce', $this->label(), $this->label(), self::CAP, self::PAGE, [$this, 'render']);
    }

    public function enqueue(string $hook): void
    {
        if (strpos($hook, self::PAGE) === false) {
            return;
        }
        $url = plugin_dir_url(__FILE__) . 'Assets/';
        $ver = \Infoplus\Suite\Plugin::VERSION;
        wp_enqueue_style('infoplus-catalog-import', $url . 'catalog-import.css', [], $ver);
        wp_enqueue_script('infoplus-catalog-import', $url . 'catalog-import.js', ['jquery'], $ver, true);
        wp_localize_script('infoplus-catalog-import', 'infoplusCatalogImport', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(self::NONCE),
            'state'   => $this->publicState(),
        ]);
    }

    public function render(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Keine Berechtigung.', 'infoplus-suite'));
        }
        ?>
        <div class="wrap ipci">
            <h1><?php echo esc_html($this->label()); ?></h1>
            <p class="ipci-intro">
                Importiert den Katalog aus <code>import.csv</code> (erzeugt von <code>import-mapping/build_import.py</code>).
                Die CSV ist die Quelle der Wahrheit: Varianten der betroffenen Produkte, die nicht in der CSV stehen, werden gelöscht.
                Die Regel „Händlerrabatt“ und alle Regeln mit Bedingungen oder Rollenrabatten bleiben unberührt.
            </p>

            <div class="ipci-card" id="ipci-upload">
                <h2>1. Probelauf</h2>
                <p>CSV hochladen. Es wird nur verglichen, auf der Seite ändert sich nichts.</p>
                <input type="file" id="ipci-file" accept=".csv,text/csv">
                <button type="button" class="button button-primary" id="ipci-plan">Probelauf starten</button>
                <span class="spinner" id="ipci-plan-spinner"></span>
            </div>

            <div class="ipci-card" id="ipci-summary" hidden></div>

            <div class="ipci-card" id="ipci-apply" hidden>
                <h2>2. Import ausführen</h2>
                <label><input type="checkbox" id="ipci-backup"> Es gibt ein aktuelles Backup der Seite (Dateien + Datenbank).</label>
                <p><button type="button" class="button button-primary" id="ipci-run" disabled>Import ausführen</button>
                   <button type="button" class="button" id="ipci-reset">Plan verwerfen</button></p>
                <div class="ipci-progress" hidden><div class="ipci-bar"><span></span></div><p class="ipci-phase"></p></div>
                <pre class="ipci-log" hidden></pre>
            </div>
        </div>
        <?php
    }

    // ── AJAX ─────────────────────────────────────────────────────────────

    public function ajaxPlan(): void
    {
        $this->guard();
        $state = get_option(self::OPT_STATE, []);
        if (!empty($state['running'])) {
            wp_send_json_error(['errors' => ['Ein Import läuft gerade. Erst fertig laufen lassen oder den Plan verwerfen.']], 409);
        }
        if (empty($_FILES['csv']['tmp_name']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
            wp_send_json_error(['errors' => ['Keine Datei hochgeladen.']], 400);
        }

        $dir = $this->storageDir();
        $csvPath = $dir . '/import-' . gmdate('Ymd-His') . '.csv';
        if (!move_uploaded_file($_FILES['csv']['tmp_name'], $csvPath)) {
            wp_send_json_error(['errors' => ['Datei konnte nicht gespeichert werden.']], 500);
        }

        $read = CsvReader::read($csvPath);
        if ($read['errors']) {
            wp_send_json_error(['errors' => array_slice($read['errors'], 0, 50)], 422);
        }

        wp_raise_memory_limit('admin');
        $built = Planner::build($read['rows']);
        if ($built['errors']) {
            wp_send_json_error(['errors' => array_slice($built['errors'], 0, 50)], 422);
        }

        $planPath = $dir . '/plan-' . gmdate('Ymd-His') . '.json';
        file_put_contents($planPath, wp_json_encode($built['plan']));

        $summary = $this->summaryFor($built['plan']) + ['csv' => basename($csvPath)];
        update_option(self::OPT_SUMMARY, $summary, false);
        update_option(self::OPT_STATE, ['plan_file' => basename($planPath), 'running' => false, 'phase' => 0, 'cursor' => 0, 'done' => false, 'log' => [], 'errors' => [], 'counts' => []], false);

        wp_send_json_success(['summary' => $summary]);
    }

    public function ajaxStep(): void
    {
        $this->guard();
        $state = get_option(self::OPT_STATE, []);
        $plan = $this->loadPlan($state);
        if (!$plan) {
            wp_send_json_error(['errors' => ['Kein Plan vorhanden. Bitte zuerst den Probelauf starten.']], 400);
        }

        $runner = new Runner($plan, $state);
        try {
            $state = $runner->step();
        } catch (\Throwable $e) {
            $state = $runner->state();
            $state['errors'][] = $e->getMessage();
            $state['running'] = false;
            update_option(self::OPT_STATE, $state, false);
            wp_send_json_error(['errors' => [$e->getMessage()], 'state' => $this->publicState()], 500);
        }
        $state['running'] = !$state['done'];
        update_option(self::OPT_STATE, $state, false);
        if ($state['done']) {
            $summary = get_option(self::OPT_SUMMARY, []);
            $summary['result'] = ['finished' => current_time('mysql'), 'counts' => $state['counts'], 'errors' => count($state['errors'])];
            update_option(self::OPT_SUMMARY, $summary, false);
        }

        wp_send_json_success(['state' => $this->publicState()]);
    }

    public function ajaxReset(): void
    {
        $this->guard();
        delete_option(self::OPT_STATE);
        wp_send_json_success();
    }

    // ── Internals ────────────────────────────────────────────────────────

    private function guard(): void
    {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can(self::CAP)) {
            wp_send_json_error(['errors' => ['Keine Berechtigung.']], 403);
        }
    }

    private function storageDir(): string
    {
        $dir = wp_upload_dir()['basedir'] . '/infoplus-catalog-import';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
            file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
            file_put_contents($dir . '/index.php', "<?php // Silence is golden.\n");
        }
        return $dir;
    }

    private function loadPlan(array $state): ?array
    {
        if (empty($state['plan_file'])) {
            return null;
        }
        $path = $this->storageDir() . '/' . basename($state['plan_file']);
        if (!is_readable($path)) {
            return null;
        }
        $plan = json_decode((string) file_get_contents($path), true);
        return is_array($plan) ? $plan : null;
    }

    /** What the admin page needs to show and resume. */
    private function publicState(): array
    {
        $state = get_option(self::OPT_STATE, []);
        $plan = $this->loadPlan($state);
        if (!$plan) {
            return ['hasPlan' => false];
        }
        $progress = (new Runner($plan, $state))->progress();
        return [
            'hasPlan'  => true,
            'summary'  => get_option(self::OPT_SUMMARY, []),
            'started'  => ($state['phase'] ?? 0) > 0 || ($state['cursor'] ?? 0) > 0,
            'done'     => !empty($state['done']),
            'percent'  => $progress['percent'],
            'phase'    => $progress['phase'],
            'log'      => array_slice($state['log'] ?? [], -40),
            'errors'   => array_slice($state['errors'] ?? [], -40),
            'counts'   => $state['counts'] ?? [],
        ];
    }

    private function summaryFor(array $plan): array
    {
        $products = [];
        foreach ($plan['parents'] as $p) {
            $products[] = ['id' => $p['id'], 'title' => $p['title'], 'status' => $p['status'], 'rows' => $p['rows'], 'deletes' => $p['deletes']];
        }
        usort($products, fn($a, $b) => $b['rows'] <=> $a['rows']);

        return $plan['summary'] + [
            'created_at'     => $plan['created'],
            'products_list'  => $products,
            'new_taxonomy_names' => $plan['new_taxonomies'],
            'new_term_names' => $plan['new_terms'],
            'rules_kept_list'=> array_slice($plan['rules_kept'], 0, 50),
            'sample_deletes' => array_slice(array_values(array_filter(array_column($plan['deletes'], 'sku'))), 0, 15),
            'sample_creates' => array_slice(array_column(array_filter($plan['upserts'], fn($u) => $u['action'] === 'create'), 'sku'), 0, 15),
        ];
    }
}
