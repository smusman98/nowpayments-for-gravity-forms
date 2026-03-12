<?php
/**
 * Plugin Name: NOWPayments for Gravity Forms
 * Plugin URI: https://www.coderpress.co/
 * Author: CoderPress
 * Description: Accept 300+ cryptocurrencies via NOWPayments on Gravity Forms. Simple payment and sandbox mode.
 * Version: 1.0.0
 * Author URI: https://www.coderpress.co/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nowpayments-for-gravity-forms
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package NOWPayments_GF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NOWPAYMENTS_GF_VERSION', '1.0.0' );
define( 'NOWPAYMENTS_GF_DIR', plugin_dir_path( __FILE__ ) );
define( 'NOWPAYMENTS_GF_URL', plugin_dir_url( __FILE__ ) );
define( 'NOWPAYMENTS_GF_BASENAME', plugin_basename( __FILE__ ) );
define( 'NOWPAYMENTS_GF_FILE', __FILE__ );

/**
 * Debug logger for this plugin (only logs when WP_DEBUG is true).
 *
 * @param string $message Message to log.
 * @return void
 */
function nowpayments_gf_debug_log( $message ) {
	if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
		return;
	}
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- debug-only logging.
	error_log( $message );
}

/**
 * Check if Gravity Forms is active.
 *
 * @return bool
 */
function nowpayments_gf_check_gravity_forms() {
	if ( ! class_exists( 'GFForms' ) ) {
		add_action( 'admin_notices', 'nowpayments_gf_missing_notice' );
		return false;
	}
	return true;
}

/**
 * Show notice if Gravity Forms is not active.
 */
function nowpayments_gf_missing_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'NOWPayments for Gravity Forms requires Gravity Forms to be installed and active.', 'nowpayments-for-gravity-forms' ); ?></p>
	</div>
	<?php
}

/**
 * Ensure NOWPayments capabilities exist and are assigned to Administrator.
 */
function nowpayments_gf_ensure_capabilities() {
	$role = get_role( 'administrator' );
	if ( ! $role ) {
		return;
	}
	foreach ( array( 'gravityforms_nowpayments', 'gravityforms_nowpayments_uninstall' ) as $cap ) {
		if ( ! $role->has_cap( $cap ) ) {
			$role->add_cap( $cap );
		}
	}
}

/**
 * Register addon on gform_loaded so GF includes us when it runs init_addons() (right after gform_loaded).
 * GF runs init_addons() on plugins_loaded — if we register on init, we're too late and our tab never appears.
 */
function nowpayments_gf_init() {
	static $initialized = false;
	if ( $initialized ) {
		return;
	}

	if ( ! nowpayments_gf_check_gravity_forms() ) {
		return;
	}

	if ( ! method_exists( 'GFForms', 'include_payment_addon_framework' ) ) {
		return;
	}

	GFForms::include_payment_addon_framework();

	require_once NOWPAYMENTS_GF_DIR . 'includes/class-nowpayments-gf-api.php';
	require_once NOWPAYMENTS_GF_DIR . 'includes/class-nowpayments-gf-simple-payment.php';
	require_once NOWPAYMENTS_GF_DIR . 'includes/class-nowpayments-gf-addon.php';
	// Load subscription handler if present; allows Pro features via a single file.
	$subscription_file    = apply_filters( 'nowpayments_gf_subscription_file', NOWPAYMENTS_GF_DIR . 'includes/class-nowpayments-gf-subscription.php' );
	$enable_subscriptions = apply_filters( 'nowpayments_gf_enable_subscriptions', file_exists( $subscription_file ) );
	if ( $enable_subscriptions && file_exists( $subscription_file ) ) {
		require_once $subscription_file;
		if ( class_exists( 'NowPayments_GF_Subscription' ) && method_exists( 'NowPayments_GF_Subscription', 'register_hooks' ) ) {
			NowPayments_GF_Subscription::register_hooks();
		}
	}

	GFAddOn::register( 'NowPayments_GF_AddOn' );

	// Register NOWPayments field so it appears under Pricing Fields in the form editor.
	require_once NOWPAYMENTS_GF_DIR . 'includes/class-gf-field-nowpayments.php';

	$initialized = true;
}

// Capabilities before GF runs (GF runs on plugins_loaded default 10).
add_action( 'plugins_loaded', 'nowpayments_gf_ensure_capabilities', 0 );

// Register addon when GF fires gform_loaded (before GF runs init_addons() in the same loaded() call).
add_action( 'gform_loaded', 'nowpayments_gf_init', 5 );

/**
 * Enqueue Gravity Forms admin CSS.
 *
 * @param string $hook Current admin page hook.
 * @return void
 */
function nowpayments_gf_editor_styles( $hook ) {
	unset( $hook ); // Intentionally unused.

	// Gravity Forms pages where this CSS is needed.
	if ( ! is_admin() ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page check only.
	$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
	if ( ! in_array( $page, array( 'gf_edit_forms', 'gf_entries' ), true ) ) {
		return;
	}

	// Remove existing style if already registered/enqueued.
	wp_dequeue_style( 'nowpayments-gf-editor' );
	wp_deregister_style( 'nowpayments-gf-editor' );

	$base    = rtrim( NOWPAYMENTS_GF_URL, '/' );
	$css_url = $base . '/assets/editor-nowpayments.css';

	// Fresh enqueue (NO CACHE).
	wp_enqueue_style(
		'nowpayments-gf-editor',
		$css_url,
		array(),
		NOWPAYMENTS_GF_VERSION
	);
}
add_action( 'admin_enqueue_scripts', 'nowpayments_gf_editor_styles', 99 );


register_activation_hook(
	__FILE__,
	function () {
		nowpayments_gf_ensure_capabilities();
	}
);

// (Update checker removed for free build)
