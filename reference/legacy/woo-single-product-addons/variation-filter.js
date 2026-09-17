jQuery(document).ready(function($) {
    // Helper to get all valid options for an attribute, given filters
    function getValid(attr, filters) {
        return Array.from(new Set(
            window.allWooVariations
                .filter(v =>
                    Object.entries(filters).every(([k, val]) => !val || v[k] === val)
                )
                .map(v => v[attr])
        ));
    }

    // Map your select names/IDs
    const $model        = $('select[name^="attribute_pa_model"]');
    const $format       = $('select[name^="attribute_pa_format"]');
    const $befestigung  = $('select[name^="attribute_pa_befestigung"]');
    const $oberflaeche  = $('select[name^="attribute_pa_oberflache"]');

    function updateDropdowns() {
        let modelVal = $model.val();
        let validFormats = getValid('format', {model: modelVal});
        $format.find('option').each(function() {
            let val = $(this).val();
            if (val === "" || validFormats.includes(val)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        // If the selected value is not valid, clear it. Otherwise, keep the default selection.
        let selectedVal = $format.val();
        if (selectedVal && !validFormats.includes(selectedVal)) {
            $format.val('');
        }
    }

    $model.on('change', updateDropdowns);
    updateDropdowns();
});
