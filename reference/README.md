# reference/

Code kept for lineage and for the InfoplusAPI merge, NOT deployed from here.

## infoplus_api/
Live copy of the `InfoplusAPI` plugin (v1.00, author Vaclav Rezabek) as downloaded
from production in September 2026. Infoplus Suite's IkonaIFE module depends on its
REST namespace `infoplus/v1`. Planned to be merged into Infoplus Suite.

Two hardcoded credentials were removed before committing. The code now reads them
from wp-config.php constants:

    define('INFOPLUS_API_KEY',   '...');  // validates incoming requests to /infoplus/v1/*
    define('INFOPLUS_IFE_TOKEN', '...');  // bearer token for editor.infoplus.de/smp/infoplus/*

The production plugin still has the literal values. Rotate both after switching
production to the constants.

The nested duplicate `infoplus_api/Infoplus Suite/` that exists on the live server
was excluded; it is dead code (never loaded by WordPress).

## legacy/
The two predecessor plugins, inactive on production, functionality absorbed into
Infoplus Suite modules of the same name:
- woo-product-variation-fields (v1.7.3) -> src/Modules/WooProductVariationFields
- woo-single-product-addons (v1.9.1)    -> src/Modules/WooSingleProductAddons
