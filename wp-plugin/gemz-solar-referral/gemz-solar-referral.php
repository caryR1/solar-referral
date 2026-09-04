<?php
/**
 * Plugin Name: Gemz Solar Referral
 * Description: Private, admin-only sub-affiliate click tracking and payout calculator for the solar referral site. Not visible to site visitors except the /go/{code} redirect itself.
 * Version: 1.0.0
 * Author: Cary Robinson
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GSR_VERSION', '1.0.0' );
define( 'GSR_DB_VERSION', '1' );
define( 'GSR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GSR_PLUGIN_FILE', __FILE__ );

require_once GSR_PLUGIN_DIR . 'includes/class-gsr-roles.php';
require_once GSR_PLUGIN_DIR . 'includes/class-gsr-db.php';
require_once GSR_PLUGIN_DIR . 'includes/class-gsr-redirect.php';
require_once GSR_PLUGIN_DIR . 'includes/class-gsr-admin.php';
require_once GSR_PLUGIN_DIR . 'includes/class-gsr-frontend.php';
require_once GSR_PLUGIN_DIR . 'includes/class-gsr-rest.php';

register_activation_hook( __FILE__, array( 'GSR_DB', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'GSR_Redirect', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'GSR_DB', 'maybe_upgrade' ) );

GSR_Redirect::init();
GSR_Admin::init();
GSR_Frontend::init();
GSR_REST::init();
