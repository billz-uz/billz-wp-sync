<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://billz.uz
 * @since             1.0.0
 * @package           Billz_Wp_Sync
 *
 * @wordpress-plugin
 * Plugin Name:       BILLZ WP Sync
 * Plugin URI:        https://github.com/billz-uz/billz-wp-sync
 * Description:
 * Version:           1.1.0
 * Author:            Kharbiyanov Marat
 * Author URI:        https://billz.uz
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       billz-wp-sync
 * GitHub Plugin URI: https://github.com/billz-uz/billz-wp-sync
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'BILLZ_WP_SYNC_VERSION', '1.1.0' );
define( 'BILLZ_WP_SYNC_PRODUCTS_TABLE', 'billz_sync_products' );
define( 'BILLZ_WP_SYNC_DATE_FORMAT', 'Y-m-d\TH:i:s\Z' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-billz-wp-sync-activator.php
 */
function activate_billz_wp_sync() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-billz-wp-sync-activator.php';
	Billz_Wp_Sync_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-billz-wp-sync-deactivator.php
 */
function deactivate_billz_wp_sync() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-billz-wp-sync-deactivator.php';
	Billz_Wp_Sync_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_billz_wp_sync' );
register_deactivation_hook( __FILE__, 'deactivate_billz_wp_sync' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-billz-wp-sync.php';

require plugin_dir_path( __FILE__ ) . 'includes/vendor/plugin-update-checker/plugin-update-checker.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_billz_wp_sync() {

	$plugin = new Billz_Wp_Sync();
	$plugin->run();

	$update_checker = Puc_v4_Factory::buildUpdateChecker(
		'https://github.com/billz-uz/billz-wp-sync/',
		__FILE__,
		'billz-wp-sync'
	);

	$update_checker->getVcsApi()->enableReleaseAssets();
}
run_billz_wp_sync();
