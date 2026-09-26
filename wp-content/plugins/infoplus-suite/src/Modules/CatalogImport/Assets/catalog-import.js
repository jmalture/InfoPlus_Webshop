/**
 * Katalog-Import admin page: upload → dry-run summary → apply in steps with progress.
 * Server side: Infoplus\Suite\Modules\CatalogImport\Module (AJAX infoplus_catalog_*).
 */
(function ($, cfg) {
    'use strict';
    if (!cfg) return;

    var $summary = $('#ipci-summary');
    var $apply = $('#ipci-apply');
    var $run = $('#ipci-run');
    var $log = $apply.find('.ipci-log');
    var $progress = $apply.find('.ipci-progress');
    var running = false;

    var PHASE_LABELS = {
        attributes: 'Attribute', terms: 'Attributwerte', parents: 'Produkte',
        variations: 'Varianten anlegen / aktualisieren', deletes: 'Varianten löschen',
        rules: 'Staffelpreise', finalize: 'Abschluss (MwSt., Standardvarianten, Cache)'
    };

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function renderSummary(s) {
        var rows = [
            ['Zeilen in der CSV', s.rows],
            ['Produkte', s.products],
            ['Varianten neu', s.create],
            ['Varianten aktualisiert', s.update],
            ['Varianten verschoben (anderes Produkt)', s.move],
            ['Varianten gelöscht', s.delete + (s.delete_without_sku ? ' (davon ohne SKU: ' + s.delete_without_sku + ')' : '')],
            ['Neue Attribute', s.new_taxonomies + (s.new_taxonomy_names && s.new_taxonomy_names.length ? ' (' + s.new_taxonomy_names.join(', ') + ')' : '')],
            ['Neue Attributwerte', s.new_terms],
            ['Staffelpreis-Regeln ersetzt', s.rules_delete],
            ['Staffelpreis-Regeln neu', s.rules_create],
            ['Preisregeln unberührt', s.rules_kept],
            ['MwSt.-Einstellung jetzt', s.prices_include_tax === 'yes' ? 'Preise inkl. MwSt. erfasst → wird auf netto umgestellt' : 'Preise netto erfasst']
        ];
        var html = '<h2>Probelauf vom ' + esc(s.created_at) + '</h2><table class="widefat striped ipci-table"><tbody>';
        rows.forEach(function (r) { html += '<tr><th>' + esc(r[0]) + '</th><td>' + esc(r[1]) + '</td></tr>'; });
        html += '</tbody></table>';

        html += '<h3>Produkte</h3><table class="widefat striped ipci-table"><thead><tr><th>ID</th><th>Produkt</th><th>Status</th><th>Varianten laut CSV</th><th>zu löschen</th></tr></thead><tbody>';
        (s.products_list || []).forEach(function (p) {
            html += '<tr><td>' + esc(p.id) + '</td><td>' + esc(p.title) + '</td><td>' + esc(p.status) + '</td><td>' + esc(p.rows) + '</td><td>' + esc(p.deletes) + '</td></tr>';
        });
        html += '</tbody></table>';

        var terms = s.new_term_names || {};
        if (Object.keys(terms).length) {
            html += '<h3>Neue Attributwerte</h3><ul class="ipci-list">';
            Object.keys(terms).forEach(function (tax) {
                html += '<li><strong>' + esc(tax) + ':</strong> ' + terms[tax].map(esc).join(', ') + '</li>';
            });
            html += '</ul>';
        }
        if (s.rules_kept_list && s.rules_kept_list.length) {
            html += '<h3>Preisregeln, die bleiben</h3><ul class="ipci-list">' + s.rules_kept_list.map(function (r) {
                return '<li>#' + esc(r.id) + ' ' + esc(r.title) + '</li>';
            }).join('') + '</ul>';
        }
        html += '<h3>Beispiele</h3><p><strong>Neu:</strong> ' + (s.sample_creates || []).map(esc).join(', ') +
            '<br><strong>Gelöscht:</strong> ' + (s.sample_deletes || []).map(esc).join(', ') + '</p>';

        if (s.result) {
            html += '<h3>Ergebnis</h3><p>Fertig am ' + esc(s.result.finished) + ', Fehler: ' + esc(s.result.errors) + '</p>';
        }
        $summary.html(html).prop('hidden', false);
        $apply.prop('hidden', false);
    }

    function renderState(st) {
        if (!st || !st.hasPlan) return;
        if (st.summary) renderSummary(st.summary);
        if (st.started || st.done) {
            $progress.prop('hidden', false);
            $progress.find('.ipci-bar span').css('width', st.percent + '%');
            $progress.find('.ipci-phase').text(st.done ? 'Fertig.' : st.percent + ' % – ' + (PHASE_LABELS[st.phase] || st.phase));
            var lines = (st.log || []).slice();
            if (st.errors && st.errors.length) {
                lines.push('', 'Fehler (' + st.errors.length + '):');
                lines = lines.concat(st.errors);
            }
            $log.text(lines.join('\n')).prop('hidden', false);
            $log.scrollTop($log[0].scrollHeight);
        }
        if (st.done) {
            $run.prop('disabled', true).text('Import abgeschlossen');
        } else if (st.started && !running) {
            $run.text('Import fortsetzen');
        }
    }

    function showErrors(errors) {
        $summary.html('<div class="notice notice-error inline"><p>' + (errors || ['Unbekannter Fehler']).map(esc).join('<br>') + '</p></div>')
            .prop('hidden', false);
    }

    $('#ipci-plan').on('click', function () {
        var file = $('#ipci-file')[0].files[0];
        if (!file) { showErrors(['Bitte zuerst eine CSV-Datei wählen.']); return; }
        var fd = new FormData();
        fd.append('action', 'infoplus_catalog_plan');
        fd.append('nonce', cfg.nonce);
        fd.append('csv', file);
        $('#ipci-plan-spinner').addClass('is-active');
        $.ajax({ url: cfg.ajaxUrl, method: 'POST', data: fd, processData: false, contentType: false })
            .done(function (res) {
                if (res.success) {
                    $progress.prop('hidden', true); $log.prop('hidden', true);
                    $run.text('Import ausführen');
                    renderSummary(res.data.summary);
                } else {
                    showErrors(res.data && res.data.errors);
                }
            })
            .fail(function (xhr) { showErrors(xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.errors); })
            .always(function () { $('#ipci-plan-spinner').removeClass('is-active'); });
    });

    $('#ipci-backup').on('change', function () { $run.prop('disabled', !this.checked); });

    function step() {
        $.post(cfg.ajaxUrl, { action: 'infoplus_catalog_step', nonce: cfg.nonce })
            .done(function (res) {
                var st = res.data && res.data.state;
                renderState(st);
                if (res.success && st && !st.done) { step(); return; }
                running = false;
                if (!res.success) $run.prop('disabled', false).text('Import fortsetzen');
            })
            .fail(function (xhr) {
                running = false;
                var data = xhr.responseJSON && xhr.responseJSON.data;
                if (data && data.state) renderState(data.state);
                $log.prop('hidden', false).text($log.text() + '\n\nAbgebrochen: ' + ((data && data.errors) || [xhr.statusText]).join(', ') +
                    '\nDer Import kann mit „Import fortsetzen“ an derselben Stelle weiterlaufen.');
                $run.prop('disabled', false).text('Import fortsetzen');
            });
    }

    $run.on('click', function () {
        if (running || !$('#ipci-backup').prop('checked')) return;
        running = true;
        $run.prop('disabled', true).text('Läuft … Tab bitte offen lassen');
        $progress.prop('hidden', false);
        step();
    });

    $('#ipci-reset').on('click', function () {
        if (running) return;
        $.post(cfg.ajaxUrl, { action: 'infoplus_catalog_reset', nonce: cfg.nonce }).done(function () {
            $summary.prop('hidden', true).empty();
            $apply.prop('hidden', true);
            $progress.prop('hidden', true);
            $log.prop('hidden', true).text('');
            $run.text('Import ausführen');
        });
    });

    renderState(cfg.state);
})(jQuery, window.infoplusCatalogImport);
