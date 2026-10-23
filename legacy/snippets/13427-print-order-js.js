<!--
Elementor Custom Code snippet (post_type elementor_snippet) exported read-only from staging
post_id: 13427
title: print order js
status: publish
location (_elementor_location): elementor_body_end
priority (_elementor_priority): 1
conditions (_elementor_conditions): ["include/singular/page/13123"]
conditions raw: a:1:{i:0;s:27:"include/singular/page/13123";}
extra options (_elementor_extra_options): []
code bytes (LENGTH(_elementor_code)): 290
Everything below the END HEADER line is the _elementor_code meta value verbatim (post_content is empty for these posts).
-->
<!-- END HEADER -->
<script>
window.addEventListener('load', function() {

const printButton = document.getElementById('print_order');
if (printButton) {
	printButton.style.cursor = 'pointer';
    printButton.addEventListener('click', function() {
        window.print();
    });
}
    });
</script>