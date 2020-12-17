<?php

function billz_wp_sync_get_category_ids( $cats ) {
	$cat_ids         = array();
	$product_cat_tax = 'product_cat';

	foreach ( $cats as $cat ) {
		$category_title_1 = isset( $cat['category_1'] ) ? $cat['category_1'] : '';
		$category_title_2 = isset( $cat['category_2'] ) ? $cat['category_2'] : '';
		$category_title_3 = isset( $cat['category_3'] ) ? $cat['category_3'] : '';
		$category_title_4 = isset( $cat['category_4'] ) ? $cat['category_4'] : '';
		$category_title_5 = isset( $cat['category_5'] ) ? $cat['category_5'] : '';

		if ( $category_title_1 ) {
			$category_1 = term_exists( $category_title_1, $product_cat_tax );
			if ( ! $category_1 ) {
				$category_1 = wp_insert_term( $category_title_1, $product_cat_tax );
			}

			$cat_ids[] = $category_1['term_id'];

			if ( $category_title_2 ) {
				$category_2 = term_exists( $category_title_2, $product_cat_tax, $category_1['term_id'] );
				if ( ! $category_2 ) {
					$category_2 = wp_insert_term( $category_title_2, $product_cat_tax, array( 'parent' => $category_1['term_id'] ) );
				}
				$cat_ids[] = $category_2['term_id'];

				if ( $category_title_3 ) {
					$category_3 = term_exists( $category_title_3, $product_cat_tax, $category_2['term_id'] );
					if ( ! $category_3 ) {
						$category_3 = wp_insert_term( $category_title_3, $product_cat_tax, array( 'parent' => $category_2['term_id'] ) );
					}
					$cat_ids[] = $category_3['term_id'];

					if ( $category_title_4 ) {
						$category_4 = term_exists( $category_title_4, $product_cat_tax, $category_3['term_id'] );
						if ( ! $category_4 ) {
							$category_4 = wp_insert_term( $category_title_4, $product_cat_tax, array( 'parent' => $category_3['term_id'] ) );
						}
						$cat_ids[] = $category_4['term_id'];

						if ( $category_title_5 ) {
							$category_5 = term_exists( $category_title_5, $product_cat_tax, $category_4['term_id'] );
							if ( ! $category_5 ) {
								$category_5 = wp_insert_term( $category_title_5, $product_cat_tax, array( 'parent' => $category_4['term_id'] ) );
							}
							$cat_ids[] = $category_5['term_id'];
						}
					}
				}
			}
		}
	}

	return $cat_ids;
}

function billz_wp_sync_get_image_ids( $images ) {
	global $wpdb;

	$image_ids = array();

	foreach ( $images as $url ) {
		$image_url     = str_replace( '_square', '', $url );
		$image_name    = basename( current( explode( '?', $image_url ) ) );
		$image_name    = pathinfo( $image_name, PATHINFO_FILENAME );
		$image_name    = str_replace( array( '(', ')' ), '', $image_name );
		$sql           = "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value like '%$image_name%'";
		$attachment_id = $wpdb->get_var( $sql );
		if ( is_null( $attachment_id ) ) {
			$upload = wc_rest_upload_image_from_url( $image_url );
			if ( is_wp_error( $upload ) ) {
				$logger = wc_get_logger();
				$logger->info( $upload->get_error_message(), array( 'source' => 'billz-failed-sync-products' ) );
				continue;
			} else {
				$attachment_id = wc_rest_set_uploaded_image_as_attachment( $upload );
			}
		}
		$image_ids[] = $attachment_id;
	}

	return $image_ids;
}

function billz_wp_sync_get_term_ids( $data ) {
	$result = array();

	foreach ( $data as $tax => $attrs ) {
		if ( $attrs ) {
			$tax_terms = array();
			foreach ( $attrs as $attr ) {
				$term = term_exists( $attr, $tax );
				if ( ! $term ) {
					$term = wp_insert_term( $attr, $tax );
				}
				$tax_terms[] = $term['term_id'];
			}
			$result[ $tax ] = $tax_terms;
		}
	}

	return $result;
}
