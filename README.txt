# billz-wp-sync

add_filter( 'billz_wp_sync_update_product_sku', '__return_true' );
add_filter( 'billz_wp_sync_update_product_images', '__return_true' );
add_filter( 'billz_wp_sync_update_product_name', '__return_true' );
add_filter( 'billz_wp_sync_update_product_description', '__return_true' );
add_filter( 'billz_wp_sync_update_product_short_description', '__return_true' );
add_filter( 'billz_wp_sync_update_product_attributes', '__return_true' );
add_filter( 'billz_wp_sync_update_product_categories', '__return_true' );
add_filter( 'billz_wp_sync_update_product_taxonomies', '__return_true' );
add_filter( 'billz_wp_sync_merge_product_categories', '__return_false' );
add_filter( 'billz_wp_sync_remove_product_images_if_empty', '__return_true' );