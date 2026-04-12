<?php
/**
 * Plugin Name: Signals Dispatch for WooCommerce
 * Description: Send WhatsApp order notifications to WooCommerce customers via the WhatsApp Business Cloud API with logs, queueing, and webhooks.
 * Version: 1.0.0
 * Author: TheMediaAble
 * License: GPLv2 or later
 * Text Domain: signals-dispatch-for-woocommerce
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * Requires Plugins: woocommerce
 *
 * @package TMASD\Signals\Dispatch
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// Define plugin constants in global namespace.
define( 'TMASD_VERSION', '1.0.0' );
define( 'TMASD_PLUGIN_FILE', __FILE__ );
define( 'TMASD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TMASD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TMASD_NAMESPACE', 'TMASD\\Signals\\Dispatch' );
define( 'TMASD_OPTION_PHONE_NUMBER_ID', 'tmasd_phone_number_id' );
define( 'TMASD_OPTION_WABA_ID', 'tmasd_waba_id' );
define( 'TMASD_OPTION_ACCESS_TOKEN', 'tmasd_access_token' );
define( 'TMASD_OPTION_WEBHOOK_VERIFY_TOKEN', 'tmasd_webhook_verify_token' );
define( 'TMASD_OPTION_APP_SECRET', 'tmasd_app_secret' );
define( 'TMASD_OPTION_REQUIRE_CONSENT', 'tmasd_require_consent' );
define( 'TMASD_ACTION_SEND_TEMPLATE', 'tmasd_send_template_message' );
define( 'TMASD_CAPABILITY', 'manage_woocommerce' );
define( 'TMASD_UPGRADE_URL', 'https://themediaablesignals.com/pricing' );

/**
 * Get the upgrade URL, filterable for future payment integration.
 *
 * @return string Upgrade URL.
 */
function tmasd_get_upgrade_url(): string {
	return (string) apply_filters( 'tmasd_upgrade_url', TMASD_UPGRADE_URL );
}

// Load Composer autoloader.
$tmasd_autoload = TMASD_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $tmasd_autoload ) ) {
	require_once $tmasd_autoload;
}

// Use new Container-based architecture.
use TMASD\Signals\Dispatch\Core\Container;

// Activation hook.
register_activation_hook(
	__FILE__,
	static function (): void {
		Container::get_instance()->activate();
	}
);

// Deactivation hook.
register_deactivation_hook(
	__FILE__,
	static function (): void {
		Container::get_instance()->deactivate();
	}
);

// Initialize on plugins_loaded.
add_action(
	'plugins_loaded',
	static function (): void {
		// Require WooCommerce — show a clear admin notice and abort if missing.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					echo '<div class="notice notice-error"><p>';
					echo esc_html__( 'Signals Dispatch for WooCommerce requires WooCommerce to be installed and active.', 'signals-dispatch-for-woocommerce' );
					echo '</p></div>';
				}
			);
			return;
		}

		Container::get_instance()->boot();
	}
);
