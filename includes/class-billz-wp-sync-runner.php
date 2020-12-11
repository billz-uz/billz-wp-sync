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
class Billz_Wp_Sync_Runner {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	private $job_name;
	private $job_repeate_time;
	private $job_group;

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
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version, $job_name, $job_repeate_time, $job_group ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->job_name = $job_name;
		$this->job_repeate_time = $job_repeate_time;
		$this->job_group = $job_group;

	}

	public function disable_unique_sku() {
		return false;
	}

	public function disable_default_runner() {
		if ( class_exists( 'ActionScheduler' ) ) {
			remove_action( 'action_scheduler_run_queue', array( ActionScheduler::runner(), 'run' ) );
		}
	}

	public function init_sync_job() {
		if ( false === as_next_scheduled_action( $this->job_name ) ) {
			as_schedule_recurring_action( strtotime( '+ 1 minute' ), $this->job_repeate_time, $this->job_name, array(), $this->job_group );
		}
	}

	public function run_sync_job() {
		new Billz_Wp_Sync_Products($this->plugin_name, $this->version);
	}

}
