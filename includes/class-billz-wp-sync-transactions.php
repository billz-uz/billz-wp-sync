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
class Billz_Wp_Sync_Transactions {

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

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of the plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	public function add_meta_boxes() {
		add_meta_box( 'billz-wp-sync-order-section', 'BILLZ', array( $this, 'billz_wp_sync_order_section_view' ), 'shop_order', 'side', 'low' );
	}

	public function billz_wp_sync_order_section_view( $post ) {
		$transaction_ids      = get_post_meta( $post->ID, '_billz_wp_sync_transaction_ids', true );
		$transaction_finished = get_post_meta( $post->ID, '_billz_wp_sync_transaction_finished', true );
		?>
			<div id="billz-form">
				<p class="form-field">
					<button class="button button-primary" style="width: 100%;" id="billz-sync-products">
						Синхронизировать остатки
					</button>
				</p>
				<p class="form-field">
					<button class="button button-primary"
									style="width: 100%;"
									id="billz-create-transaction"
					<?php disabled( true, $transaction_finished ); ?>
					>
					<?php if ( $transaction_finished ) : ?>
							Товары проданы
						<?php elseif ( $transaction_ids && ! $transaction_finished ) : ?>
							Продать отложенные товары
						<?php else : ?>
							Продать товары
						<?php endif; ?>
					</button>
				</p>
				<button class="button button-primary" style="width: 100%;"
								id="billz-park-transaction" <?php disabled( true, ! empty( $transaction_ids ) ); ?>>
				<?php if ( $transaction_ids && $transaction_finished ) : ?>
						Товары проданы
					<?php elseif ( $transaction_ids && ! $transaction_finished ) : ?>
						Товары отложены
					<?php else : ?>
						Отложить товары
					<?php endif; ?>
				</button>
			</div>
			<script type="text/javascript">
						(function ($) {
								let orderID = <?php echo $post->ID; ?>;
								$('#billz-create-transaction').on('click', function (e) {
										e.preventDefault();
										$.ajax({
												url: ajaxurl,
												data: {
														action: 'billz_wp_sync_create_transaction',
														order_id: orderID
												},
												method: 'POST',
												context: $(this),
												dataType: 'json',
												success: function (res) {
														if (res.success) {
																alert('Товары успешно проданы');
																$(this).attr('disabled', 'disabled');
																$(this).text('Товары проданы');
																$('#billz-park-transaction').text('Товары проданы').attr('disabled', 'disabled');
														} else {
																alert(res.data);
														}
												}
										});
								});
								$('#billz-park-transaction').on('click', function (e) {
										e.preventDefault();
										$.ajax({
												url: ajaxurl,
												data: {
														action: 'billz_wp_sync_create_transaction',
														order_id: orderID,
														parked: true
												},
												method: 'POST',
												context: $(this),
												dataType: 'json',
												success: function (res) {
														if (res.success) {
																alert('Отложка успешно создана');
																$(this).text('Товары отложены');
																$('#billz-create-transaction').text('Продать отложенные товары');
																$(this).attr('disabled', 'disabled');
														} else {
																alert(res.data);
														}
												}
										});
								});
								// $('#billz-sync-products').on('click', function (e) {
								// 		e.preventDefault();
								// 		$.ajax({
								// 				url: ajaxurl,
								// 				data: {
								// 						action: 'billz_wp_sync_sync_products',
								// 				},
								// 				method: 'POST',
								// 				context: $(this),
								// 				success: function (res) {
								// 						window.location.reload(true);
								// 				}
								// 		});
								// });
						})(jQuery);
			</script>
		<?php
	}

	public function create_transaction_ajax() {
		if ( is_plugin_active( 'woocommerce-currency-switcher/index.php' ) ) {
			$is_woocs_enabled = true;
		} else {
			$is_woocs_enabled = false;
		}
		$order_id        = ! empty( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
		$parked          = ! empty( $_POST['parked'] ) ? true : false;
		$transaction_ids = get_post_meta( $order_id, '_billz_wp_sync_transaction_ids', true );
		$order           = wc_get_order( $order_id );
		$products        = array();
		$jwt_token       = apply_filters( 'billz_wp_sync_jwt_token', false );

		if ( ! $jwt_token ) {
			wp_send_json_error( 'Добавьте JWT токен через фильтр billz_wp_sync_jwt_token' );
		}

		if ( $transaction_ids ) {
			$request_body = array(
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => 'orders.apply',
				'params'  => array(
					'transactionID' => $transaction_ids,
				),
			);

			$response = wp_remote_post(
				'https://api.billz.uz/v1/',
				array(
					'headers' => array(
						'Content-Type'  => 'application/json',
						'Cache-Control' => 'no-cache',
						'Authorization' => 'Bearer ' . $jwt_token,
					),
					'timeout' => 300,
					'body'    => json_encode( $request_body ),
				)
			);

			$response_body = wp_remote_retrieve_body( $response );
			$response_body = json_decode( $response_body, true );

			if ( ! empty( $response_body['result'] ) ) {
				update_post_meta( $order_id, '_billz_wp_sync_transaction_finished', true );
				wp_send_json_success( $response_body['result'] );
			} else {
				wp_send_json_error( 'Заказ не отправлен. Пожалуйста свяжитесь с администрацией BILLZ. ID заказа: ' . $order_id );
			}
		}

		$date_created       = $order->get_date_created();
		$date_created_value = $date_created->date( BILLZ_WP_SYNC_DATE_FORMAT );
		$date_paid          = $order->get_date_paid();
		if ( $date_paid ) {
			$date_paid_value = $date_paid->date( BILLZ_WP_SYNC_DATE_FORMAT );
		} else {
			$date_paid_value = $date_created->date( BILLZ_WP_SYNC_DATE_FORMAT );
		}

		if ( $order->get_items() ) {
			foreach ( $order->get_items() as $order_item_product ) {
				$order_item_product_id = $order_item_product->get_id();
				$product_id            = $order_item_product->get_product_id();
				$variation_id          = $order_item_product->get_variation_id();
				$billz_shop_id         = wc_get_order_item_meta( $order_item_product_id, '_billz_wp_sync_office_id', true );
				$product_id_for_meta   = ( $variation_id ) ? $variation_id : $product_id;

				$sub_total_price = floatval( $order_item_product->get_subtotal() );
				$total_price     = floatval( $order_item_product->get_total() );
				$discount_amount = floatval( $sub_total_price - $total_price );

				$products[] = array(
					'billzOfficeID'  => intval( $billz_shop_id ),
					'billzProductID' => intval( get_post_meta( $product_id_for_meta, '_remote_product_id', true ) ),
					'productID'      => ( $variation_id ) ? $variation_id : $product_id,
					'name'           => $order_item_product->get_name(),
					'sku'            => get_post_meta( $product_id, '_sku', true ),
					'barCode'        => get_post_meta( $product_id_for_meta, '_barcode', true ),
					'qty'            => $order_item_product->get_quantity(),
					'subTotalPrice'  => $is_woocs_enabled ? intval( get_post_meta( $product_id_for_meta, '_woocs_regular_price_UZS', true ) ) : $sub_total_price,
					'discountAmount' => $discount_amount,
					'totalPrice'     => $is_woocs_enabled ? intval( get_post_meta( $product_id_for_meta, '_woocs_regular_price_UZS', true ) ) : $total_price,
				);

				if ( ! $billz_shop_id ) {
					wp_send_json_error( "1) Выберите магазины отгрузки\n2) Нажмите на кнопку обновить\n3) Попробуйте заново" );
				}
			}
		}
		if ( $products ) {
			$request_body = array(
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => 'orders.create',
				'params'  => array(
					'orderID'        => $order_id,
					'dateCreated'    => $date_created_value,
					'datePaid'       => $date_paid_value,
					'paymentMethod'  => $order->get_payment_method(),
					'subTotalPrice'  => floatval( $order->get_subtotal() ),
					'discountAmount' => floatval( $order->get_total_discount() ),
					'totalPrice'     => floatval( $order->get_total() ),
					'products'       => $products,
					'parked'         => $parked,
				),
			);

			$response = wp_remote_post(
				'https://api.billz.uz/v1/',
				array(
					'headers' => array(
						'Content-Type'  => 'application/json',
						'Cache-Control' => 'no-cache',
						'Authorization' => 'Bearer ' . $jwt_token,
					),
					'timeout' => 300,
					'body'    => json_encode( $request_body ),
				)
			);

			$response_body = wp_remote_retrieve_body( $response );
			$response_body = json_decode( $response_body, true );

			if ( ! empty( $response_body['result'] ) ) {
				update_post_meta( $order_id, '_billz_wp_sync_transaction_ids', $response_body['result'] );
				if ( ! $parked ) {
					update_post_meta( $order_id, '_billz_wp_sync_transaction_finished', true );
				}
				wp_send_json_success( $response_body['result'] );
			} else {
				wp_send_json_error( 'Заказ не отправлен. Пожалуйста свяжитесь с администрацией BILLZ. ID заказа: ' . $order_id );
			}
		}
	}

	public function woocommerce_admin_order_item_headers() {
		echo '<th class="item_shop">Магазин отгрузки</th>';
	}

	public function woocommerce_admin_order_item_values( $product, $item, $item_id ) {
		if ( is_null( $product ) ) {
			echo '<td class="shop"></td>';
		} else {
			$product_id       = $product->get_id();
			$billz_shop_stock = get_post_meta( $product_id, '_billz_wp_sync_offices', true );
			$billz_shop_id    = wc_get_order_item_meta( $item_id, '_billz_wp_sync_office_id', true );
			?>
			<td class="shop">
				<select name="billz_office[<?php echo esc_attr( $item_id ); ?>]">
					<option value="">Выберите магазин</option>
					<?php if ( $billz_shop_stock ) : ?>
						<?php foreach ( $billz_shop_stock as $shop ) : ?>
								<option value="<?php echo esc_attr( $shop['officeID'] ); ?>" <?php selected( $billz_shop_id, $shop['officeID'] ); ?>>
									<?php echo esc_attr( $shop['officeName'] ); ?>
									(<?php echo esc_attr( $shop['qty'] ); ?> шт.)
								</option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</td>
			<?php
		}
	}

	public function woocommerce_hidden_order_itemmeta( $hidden_order_itemmeta ) {
		$hidden_order_itemmeta[] = '_billz_wp_sync_office_id';
		return $hidden_order_itemmeta;
	}

	public function save_post_shop_order( $order_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $order_id;
		}
		if ( ! current_user_can( 'edit_post', $order_id ) ) {
			return $order_id;
		}

		if ( ! empty( $_POST['billz_office'] ) ) {
			foreach ( $_POST['billz_office'] as $item_id => $shop_id ) {
				wc_update_order_item_meta( $item_id, '_billz_wp_sync_office_id', $shop_id );
			}
		}
	}

	public function pre_post_update( $order_id ) {
		global $post;

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $order_id;
		}
		if ( ! current_user_can( 'edit_post', $order_id ) ) {
			return $order_id;
		}

		if ( empty( $post->post_type ) || 'shop_order' !== $post->post_type ) {
			return $order_id;
		}

		if ( 'wc-completed' === $_POST['order_status'] ) {
			foreach ( $_POST['billz_office'] as $item_id => $shop_id ) {
				if ( ! $shop_id ) {
					wp_die( 'Выберите магазин отправки' );
				}
			}
		}
	}

}
