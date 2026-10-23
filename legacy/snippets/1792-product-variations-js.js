<!--
Elementor Custom Code snippet (post_type elementor_snippet) exported read-only from staging
post_id: 1792
title: Product variations js
status: draft
location (_elementor_location): elementor_body_end
priority (_elementor_priority): 1
conditions (_elementor_conditions): ["include/general"]
conditions raw: a:1:{i:0;s:15:"include/general";}
extra options (_elementor_extra_options): ["ensure_jquery"]
code bytes (LENGTH(_elementor_code)): 3050
Everything below the END HEADER line is the _elementor_code meta value verbatim (post_content is empty for these posts).
-->
<!-- END HEADER -->
<script>
jQuery(document).ready(function($) {
    // 1. Extract all variations from the table
    var variationsData = [];
    $('table.variations tbody tr').each(function() {
        variationsData.push({
            model: $(this).find('td[data-title="Model"]').text().trim(),
            format: $(this).find('td[data-title="Format"]').text().trim(),
            befestigung: $(this).find('td[data-title="Befestigung"]').text().trim(),
            oberflaeche: $(this).find('td[data-title="Oberfläche"]').text().trim()
        });
    });
    console.log("Extracted variations:", variationsData);

    // 2. Map for attr names in order of selects
    const attrs = ['model', 'format', 'befestigung', 'oberflaeche'];

    // 3. Find selects by their labels/order in the filters form
    let $filters = $('.filters');
    let $selects = [
        $filters.find('label:contains("Model")').next('select'),
        $filters.find('label:contains("Format")').next('select'),
        $filters.find('label:contains("Befestigung")').next('select'),
        $filters.find('label:contains("Oberfläche")').next('select')
    ];

    // Helper: get all valid values for an attribute, given filters
    function getValidOptions(attr, filters) {
        return [...new Set(
            variationsData.filter(v =>
                Object.entries(filters).every(([k, val]) => (k === attr) || !val || v[k] === val)
            ).map(v => v[attr])
        )];
    }

    // Helper: keep the "Any" option at the top, and rebuild select
    function setSelectOptions($select, validOptions, prevVal) {
        let anyOption = $select.find('option[value=""], option:contains("Any")').first().clone();
        $select.empty();
        if (anyOption.length) $select.append(anyOption); // Keep "Any" on top
        validOptions.forEach(val => {
            $select.append($('<option>', {value: val, text: val}));
        });
        // Set value to previous if valid, otherwise first valid, otherwise "Any"
        if (validOptions.includes(prevVal)) $select.val(prevVal);
        else if (validOptions.length) $select.val(validOptions[0]);
        else $select.val('');
    }

    function updateDropdowns() {
        // Track filters as we go
        let filters = {};
        // For each select, from left to right
        $selects.forEach(function($sel, i) {
            // Get filter values up to here
            for (let j = 0; j < i; j++) {
                filters[attrs[j]] = $selects[j].val();
            }
            let currentAttr = attrs[i];
            let prevVal = $sel.val();
            let validOptions = getValidOptions(currentAttr, filters);
            setSelectOptions($sel, validOptions, prevVal);
            // Set filter value for next select
            filters[currentAttr] = $sel.val();
        });
    }

    // Initial run
    updateDropdowns();

    // Change events on all selects
    $selects.forEach($sel => $sel.on('change', updateDropdowns));
});

	</script>