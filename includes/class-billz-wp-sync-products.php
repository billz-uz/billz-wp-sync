<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://billz.uz
 * @since      1.0.0
 *
 * @package    Billz_Wp_Sync
 * @subpackage Billz_Wp_Sync/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Billz_Wp_Sync
 * @subpackage Billz_Wp_Sync/public
 * @author     Kharbiyanov Marat <kharbiyanov@gmail.com>
 */
class Billz_Wp_Sync_Products {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	private $wpdb;
	private $products_table_name;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of the plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		global $wpdb;
		$this->plugin_name         = $plugin_name;
		$this->version             = $version;
		$this->wpdb                = $wpdb;
		$this->products_table_name = $wpdb->prefix . BILLZ_WP_SYNC_PRODUCTS_TABLE;
		$this->run();
	}

	private function run() {
		$products = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM $this->products_table_name WHERE state = %d", 0 ), ARRAY_A );

		if ( $products ) {
			foreach ( $products as $product ) {
				$product_id = $this->get_product_id_by( '_billz_grouping_value', $product['grouping_value'], 'product' );

				$product['categories'] = ! empty( $product['categories'] ) ? unserialize( $product['categories'] ) : '';
				$product['images']     = ! empty( $product['images'] ) ? unserialize( $product['images'] ) : '';
				$product['attributes'] = ! empty( $product['attributes'] ) ? unserialize( $product['attributes'] ) : '';
				$product['variations'] = ! empty( $product['variations'] ) ? unserialize( $product['variations'] ) : '';
				$product['taxonomies'] = ! empty( $product['taxonomies'] ) ? unserialize( $product['taxonomies'] ) : '';

				if ( $product_id ) {
					$product_id = $this->update_product( $product_id, $product );
				} else {
					$product_id = $this->create_product( $product );
				}
				$this->wpdb->update(
					$this->products_table_name,
					array(
						'state'    => 1,
						'imported' => current_time( 'mysql' ),
					),
					array( 'ID' => $product['ID'] )
				);
			}
			$this->tg_notify( 'Синхронизовано: ' . count( $products ) . ' товаров.' );
		}
	}

	private function tg_notify( $msg ) {
		wp_remote_get( 'https://api.telegram.org/bot1491973566:AAGWeL5_tDvUn6-iCuZVfYbgeAHTf3Ppsjc/sendMessage?chat_id=2498928&text=' . $msg );
	}

	private function get_product_id_by( $by, $value ) {
		$product_id = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT p.ID FROM {$this->wpdb->posts} p LEFT JOIN {$this->wpdb->postmeta} m on(p.id = m.post_id) WHERE m.meta_key='%s' AND m.meta_value='%s' AND p.post_type = '%s' AND (p.post_status = 'publish' or p.post_status = 'draft') LIMIT 1", $by, $value, 'product' ) );
		if ( $product_id ) {
			return $product_id;
		} else {
			return false;
		}
	}

	private function get_variation_id_by( $by, $value, $parent_id ) {
		$product_id = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT p.ID FROM {$this->wpdb->posts} p LEFT JOIN {$this->wpdb->postmeta} m on(p.id = m.post_id) WHERE m.meta_key='%s' AND m.meta_value='%s' AND p.post_type = '%s' AND p.post_parent = '%s' LIMIT 1", $by, $value, 'product_variation', $parent_id ) );
		if ( $product_id ) {
			return $product_id;
		} else {
			return false;
		}
	}

	private function update_product( $product_id, $args ) {
		$product = wc_get_product( $product_id );

		if ( $args['type'] == 'variable' ) {
			if ( $args['variations'] ) {
				foreach ( $args['variations'] as $variation ) {
					$variation_id = $this->get_variation_id_by( '_remote_product_id', $variation['remote_product_id'], $product_id );

					if ( $variation_id ) {
						$obj_variation   = wc_get_product( $variation_id );
						$variation_exist = true;
					} else {
						$obj_variation   = new WC_Product_Variation();
						$variation_exist = false;
					}

					$obj_variation->set_parent_id( $product_id );
					$obj_variation->set_regular_price( $variation['regular_price'] );
					$obj_variation->set_sale_price( isset( $variation['sale_price'] ) ? $variation['sale_price'] : '' );
					$obj_variation->set_price( isset( $variation['sale_price'] ) ? $variation['sale_price'] : $variation['regular_price'] );

					if ( ! apply_filters( 'billz_wp_sync_disable_updating_sku', false ) && isset( $variation['sku'] ) && $variation['sku'] ) {
						$obj_variation->set_sku( $variation['sku'] );
					}

					$obj_variation->set_manage_stock( true );
					$obj_variation->set_stock_quantity( $variation['qty'] );
					$obj_variation->set_stock_status( 'instock' );
					$disable_updating_attributes = apply_filters( 'billz_wp_sync_disable_updating_attributes', false );
					if ( ( $disable_updating_attributes && ! $variation_exist ) || ! $disable_updating_attributes ) {
						$var_attributes = array();
						foreach ( $variation['attributes'] as $vattribute ) {
							$taxonomy                    = 'pa_' . $vattribute['name'];
							$attr_val_slug               = wc_sanitize_taxonomy_name( stripslashes( $vattribute['option'] ) );
							$var_attributes[ $taxonomy ] = $attr_val_slug;
						}
						$obj_variation->set_attributes( $var_attributes );
					}

					$variation_id = $obj_variation->save();

					if ( ! apply_filters( 'billz_wp_sync_disable_updating_images', false ) && $variation['images'] ) {
						update_post_meta( $variation_id, '_thumbnail_id', $variation['images'][0] );
						array_shift( $variation['images'] );
						update_post_meta( $variation_id, 'rtwpvg_images', $variation['images'] );
					}

					if ( isset( $variation['remote_product_id'] ) ) {
						update_post_meta( $variation_id, '_remote_product_id', $variation['remote_product_id'] );
					}

					if ( $variation['meta'] ) {
						foreach ( $variation['meta'] as $meta_key => $meta_value ) {
							update_post_meta( $variation_id, $meta_key, $meta_value );
						}
					}
				}
			}
		}

		if ( ! apply_filters( 'billz_wp_sync_disable_updating_name', false ) ) {
			$product->set_name( $args['name'] );
		}
		if ( ! apply_filters( 'billz_wp_sync_disable_updating_description', false ) ) {
			$product->set_description( $args['description'] );
		}
		if ( ! apply_filters( 'billz_wp_sync_disable_updating_short_description', false ) ) {
			$product->set_short_description( $args['short_description'] );
		}
		$product->set_status( isset( $args['status'] ) ? $args['status'] : 'publish' );
		$product->set_catalog_visibility( isset( $args['visibility'] ) ? $args['visibility'] : 'visible' );

		if ( $args['type'] == 'simple' ) {
			$product->set_regular_price( $args['regular_price'] );
			$product->set_sale_price( isset( $args['sale_price'] ) ? $args['sale_price'] : '' );
			$product->set_price( isset( $args['sale_price'] ) ? $args['sale_price'] : $args['regular_price'] );
			$product->set_weight( isset( $args['weight'] ) ? $args['weight'] : '' );
			$product->set_length( isset( $args['length'] ) ? $args['length'] : '' );
			$product->set_width( isset( $args['width'] ) ? $args['width'] : '' );
			$product->set_height( isset( $args['height'] ) ? $args['height'] : '' );
		}

		if ( ! apply_filters( 'billz_wp_sync_disable_updating_attributes', false ) && isset( $args['attributes'] ) ) {
			$product->set_attributes( $this->get_attribute_ids( $args['attributes'] ) );
		}

		if ( isset( $args['default_attributes'] ) ) {
				$product->set_default_attributes( $args['default_attributes'] );
		}

		if ( isset( $args['menu_order'] ) ) {
				$product->set_menu_order( $args['menu_order'] );
		}

		if ( ! apply_filters( 'billz_wp_sync_disable_updating_categories', false ) && isset( $args['categories'] ) ) {
			$product->set_category_ids( $args['categories'] );
		}

		if ( ! apply_filters( 'billz_wp_sync_disable_updating_images', false ) && $args['images'] ) {
			$product->set_image_id( $args['images'][0] );
			array_shift( $args['images'] );
			if ( $image_ids ) {
				$product->set_gallery_image_ids( $args['images'] );
			}
		}

		$product_id = $product->save();

		if ( $args['meta'] ) {
			foreach ( $args['meta'] as $meta_key => $meta_value ) {
				update_post_meta( $product_id, $meta_key, $meta_value );
			}
		}

		if ( ! apply_filters( 'billz_wp_sync_disable_updating_taxonomies', false ) && $args['taxonomies'] ) {
			foreach ( $args['taxonomies'] as $tax => $terms ) {
				wp_set_post_terms( $product_id, $terms, $tax, true );
			}
		}

		if ( isset( $args['remote_product_id'] ) ) {
					update_post_meta( $product_id, '_remote_product_id', $args['remote_product_id'] );
		}

		if ( isset( $args['grouping_value'] ) ) {
			update_post_meta( $product_id, '_billz_grouping_value', $args['grouping_value'] );
		}

			return $product_id;
	}

	private function create_product( $args ) {
		if ( $args['type'] == 'variable' ) {
			$product = new WC_Product_Variable();
		} else {
			$product = new WC_Product();
			if ( isset( $args['sku'] ) ) {
				$product->set_sku( $args['sku'] );
			}
		}

		$product->set_name( $args['name'] );
		$product->set_description( $args['description'] );
		$product->set_short_description( $args['short_description'] );
		$product->set_status( isset( $args['status'] ) ? $args['status'] : 'publish' );
		$product->set_catalog_visibility( isset( $args['visibility'] ) ? $args['visibility'] : 'visible' );

		if ( $args['type'] == 'simple' ) {
			$product->set_regular_price( $args['regular_price'] );
			$product->set_sale_price( isset( $args['sale_price'] ) ? $args['sale_price'] : '' );
			$product->set_price( isset( $args['sale_price'] ) ? $args['sale_price'] : $args['regular_price'] );
			$product->set_weight( isset( $args['weight'] ) ? $args['weight'] : '' );
			$product->set_length( isset( $args['length'] ) ? $args['length'] : '' );
			$product->set_width( isset( $args['width'] ) ? $args['width'] : '' );
			$product->set_height( isset( $args['height'] ) ? $args['height'] : '' );
			$product->set_manage_stock( true );
			$product->set_stock_quantity( $args['qty'] );
			$product->set_stock_status( 'instock' );
		}

		if ( isset( $args['attributes'] ) ) {
			$product->set_attributes( $this->get_attribute_ids( $args['attributes'] ) );
		}

		if ( isset( $args['default_attributes'] ) ) {
			$product->set_default_attributes( $args['default_attributes'] );
		}

		if ( isset( $args['menu_order'] ) ) {
			$product->set_menu_order( $args['menu_order'] );
		}

		if ( isset( $args['categories'] ) ) {
			$product->set_category_ids( $args['categories'] );
		}

		if ( $args['images'] ) {
			$product->set_image_id( $args['images'][0] );
			array_shift( $args['images'] );
			if ( $image_ids ) {
				$product->set_gallery_image_ids( $args['images'] );
			}
		}

		$product_id = $product->save();

		if ( ! empty( $args['user_id'] ) ) {
			wp_update_post(
				array(
					'ID'          => $product_id,
					'post_author' => $args['user_id'],
				)
			);
		}

		if ( ! empty( $args['meta'] ) ) {
			foreach ( $args['meta'] as $meta_key => $meta_value ) {
				update_post_meta( $product_id, $meta_key, $meta_value );
			}
		}

		if ( ! empty( $args['taxonomies'] ) ) {
			foreach ( $args['taxonomies'] as $tax => $terms ) {
				wp_set_post_terms( $product_id, $terms, $tax, true );
			}
		}

		if ( ! empty( $args['variations'] ) ) {
			foreach ( $args['variations'] as $variation ) {
				$obj_variation = new WC_Product_Variation();
				$obj_variation->set_parent_id( $product_id );

				$obj_variation->set_regular_price( $variation['regular_price'] );
				$obj_variation->set_sale_price( isset( $variation['sale_price'] ) ? $variation['sale_price'] : '' );
				$obj_variation->set_price( isset( $variation['sale_price'] ) ? $variation['sale_price'] : $variation['regular_price'] );

				if ( isset( $variation['sku'] ) && $variation['sku'] ) {
					$obj_variation->set_sku( $variation['sku'] );
				}

				$obj_variation->set_manage_stock( true );
				$obj_variation->set_stock_quantity( $variation['qty'] );
				$obj_variation->set_stock_status( 'instock' );
				$var_attributes = array();
				foreach ( $variation['attributes'] as $vattribute ) {
					$taxonomy                    = 'pa_' . wc_sanitize_taxonomy_name( stripslashes( $vattribute['name'] ) );
					$attr_val_slug               = wc_sanitize_taxonomy_name( stripslashes( $vattribute['option'] ) );
					$var_attributes[ $taxonomy ] = $attr_val_slug;
				}
				$obj_variation->set_attributes( $var_attributes );
				$variation_id = $obj_variation->save();

				if ( $variation['images'] ) {
					update_post_meta( $variation_id, '_thumbnail_id', $variation['images'][0] );
					array_shift( $variation['images'] );
					update_post_meta( $variation_id, 'rtwpvg_images', $variation['images'] );
				}

				if ( isset( $variation['remote_product_id'] ) ) {
					update_post_meta( $variation_id, '_remote_product_id', $variation['remote_product_id'] );
				}

				if ( $variation['meta'] ) {
					foreach ( $variation['meta'] as $meta_key => $meta_value ) {
						update_post_meta( $variation_id, $meta_key, $meta_value );
					}
				}
			}
		}

		if ( isset( $args['remote_product_id'] ) ) {
			update_post_meta( $product_id, '_remote_product_id', $args['remote_product_id'] );
		}

		if ( isset( $args['grouping_value'] ) ) {
			update_post_meta( $product_id, '_billz_grouping_value', $args['grouping_value'] );
		}

		return $product_id;
	}

	private function get_attribute_ids( $attributes ) {
		$data     = array();
		$position = 0;

		foreach ( $attributes as $taxonomy => $values ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$attribute = new WC_Product_Attribute();

			$term_ids = array();

			foreach ( $values['term_names'] as $term_name ) {
				if ( term_exists( $term_name, $taxonomy ) ) {
					$term_ids[] = get_term_by( 'name', $term_name, $taxonomy )->term_id;
				} else {
					$term       = wp_insert_term( $term_name, $taxonomy, array( 'slug' => sanitize_title( $attr ) ) );
					$term_ids[] = $term['term_id'];
				}
			}

			$taxonomy_id = wc_attribute_taxonomy_id_by_name( $taxonomy );

			$attribute->set_id( $taxonomy_id );
			$attribute->set_name( $taxonomy );
			$attribute->set_options( $term_ids );
			$attribute->set_position( $position );
			$attribute->set_visible( $values['is_visible'] );
			$attribute->set_variation( $values['for_variation'] );

			$data[ $taxonomy ] = $attribute;

			$position++;
		}
		return $data;
	}

}
