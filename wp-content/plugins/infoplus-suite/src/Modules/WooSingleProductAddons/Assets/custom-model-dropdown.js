jQuery(function($) {
    const $modelSelect = $('select[name^="attribute_pa_model"]');
    if ($modelSelect.length === 0) return;

    // Hide the original select
    $modelSelect.hide();

    // Get options and build custom dropdown
    const options = [];
    $modelSelect.find('option').each(function() {
        options.push({
            value: $(this).val(),
            text: $(this).text(),
            selected: $(this).is(':selected')
        });
    });

    // Use the product image for all options (can be extended for per-option images)
    const imgUrl = window.currentProductImage;

    // Find the selected option before building the HTML
    const selectedOption = options.find(o => o.selected) || options[0];

    // Build custom dropdown HTML
    let dropdownHtml = '<div class="custom-model-dropdown">';
    dropdownHtml += '<div class="custom-model-selected" style="position:relative;">';
    if (imgUrl) {
        dropdownHtml += '<img src="' + imgUrl + '" class="model-image-preview" style="max-width:40px;vertical-align:middle;margin-right:8px;">';
    }
    dropdownHtml += '<span class="custom-model-text">' + (selectedOption ? selectedOption.text : '') + '</span>';
    // Add a dedicated arrow container with SVG image
    dropdownHtml += '<span class="custom-model-arrow"><img src="' + window.arrowSvgUrl + '" alt="arrow" style="width:18px;height:18px;display:block;"></span>';
    dropdownHtml += '</div>';
    dropdownHtml += '<div class="custom-model-options" style="display:none;position:absolute;z-index:10;background:#fff;border:1px solid #ccc;">';
    options.forEach(function(opt) {
        dropdownHtml += '<div class="custom-model-option" data-value="' + opt.value + '">';
        if (imgUrl) {
            dropdownHtml += '<img src="' + imgUrl + '" class="model-image-preview" style="max-width:40px;vertical-align:middle;margin-right:8px;">';
        }
        dropdownHtml += '<span>' + opt.text + '</span>';
        dropdownHtml += '</div>';
    });
    dropdownHtml += '</div>';
    dropdownHtml += '</div>';

    // Remove any previous image preview below the select
    $modelSelect.siblings('.model-image-preview').remove();
    // Insert after the select
    $modelSelect.after(dropdownHtml);
    const $dropdown = $modelSelect.next('.custom-model-dropdown');
    const $selected = $dropdown.find('.custom-model-selected');
    const $options = $dropdown.find('.custom-model-options');

    // Toggle options on click
    $selected.on('click', function(e) {
        e.stopPropagation();
        $options.toggle();
        $dropdown.toggleClass('open', $options.is(':visible'));
    });
    // Option click
    $options.on('click', '.custom-model-option', function() {
        const value = $(this).data('value');
        const text = $(this).find('span').text();
        $modelSelect.val(value).trigger('change');
        $selected.find('.custom-model-text').text(text); // Only update the text span
        $options.hide();
    });
    // Hide on outside click
    $(document).on('click', function() {
        $options.hide();
    });
    // Mark form as ready for CSS fade-in
    $('body').addClass('variations-form-ready');
});
