<?php
/**
 * Fired during plugin activation
 *
 * @link       https://billz.uz
 * @since      1.0.0
 *
 * @package    Billz_Wp_Sync
 * @subpackage Billz_Wp_Sync/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Billz_Wp_Sync
 * @subpackage Billz_Wp_Sync/includes
 * @author     Kharbiyanov Marat <kharbiyanov@gmail.com>
 */
class Billz_Wp_Sync_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		global $wpdb;

		$table_name = $wpdb->prefix . BILLZ_WP_SYNC_PRODUCTS_TABLE;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			ID bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			created datetime NOT NULL,
			imported datetime NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'publish',
			user_id bigint(20) NOT NULL,
			remote_product_id text NOT NULL,
			type varchar(255) NOT NULL,
			name text NOT NULL,
			sku text NOT NULL,
			description longtext NOT NULL,
			short_description text NOT NULL,
			regular_price float NOT NULL,
			sale_price float NOT NULL,
			qty bigint(20) NOT NULL,
			grouping_value varchar(255) NOT NULL,
			categories longtext NOT NULL,
			images longtext NOT NULL,
			taxonomies longtext NOT NULL,
			attributes longtext NOT NULL,
			variations longtext NOT NULL,
			state smallint(6) NOT NULL DEFAULT '0'
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		add_option( 'billz_wp_sync_version', BILLZ_WP_SYNC_VERSION );
	}

}
