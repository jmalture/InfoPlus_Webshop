jQuery(document).ready(function($) {
  // If there is no variations form or no variations array, bail early.
  if (!$('.variations_form').length || !Array.isArray(window.allWooVariations)) {
    return;
  }

  // Early exit if no variations data
  if (window.allWooVariations.length === 0) {
    // Could be a product without variations or variations without prices
    return;
  }

  // Helper to get all valid options for an attribute, given filters
  function getValid(attr, filters) {
    return Array.from(new Set(
      window.allWooVariations
        .filter(v => {
          // Check each filter
          return Object.entries(filters).every(([k, val]) => {
            // If filter value is empty, don't filter on this attribute
            if (!val || val === '') return true;
            // Otherwise, the variation must match
            return v[k] === val;
          });
        })
        .map(v => v[attr])
        .filter(v => v && v !== '') // Remove empty values
    ));
  }

  // Map your select names/IDs
  const $model        = $('select[name^="attribute_pa_model"]');
  const $format       = $('select[name^="attribute_pa_format"]');
  const $befestigung  = $('select[name^="attribute_pa_befestigung"]');
  const $oberflaeche  = $('select[name^="attribute_pa_oberflache"]');

  function updateDropdowns() {
    let modelVal = $model.val();

    // Get valid formats based on selected model
    let validFormats = getValid('format', {model: modelVal});
    
    // Show/hide format options
    $format.find('option').each(function() {
      let val = $(this).val();
      
      // Always show the placeholder option
      if (val === '' || val === 'Choose an option') {
        $(this).show();
        return;
      }
      
      // Show if valid, hide otherwise
      if (validFormats.includes(val)) {
        $(this).show();
      } else {
        $(this).hide();
      }
    });
    
    // If the selected value is not valid, clear it
    let selectedVal = $format.val();
    if (selectedVal && selectedVal !== '' && !validFormats.includes(selectedVal)) {
      $format.val('').trigger('change');
    }
  }

  // Update dropdowns when model changes
  $model.on('change', updateDropdowns);
  
  // Initial update on page load
  updateDropdowns();
});