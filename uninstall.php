<?php
/**
 * Plugin uninstall routine.
 *
 * Runs when the plugin is deleted (not just deactivated) from the
 * WordPress plugins screen.  Removes all plugin options and, when the
 * admin has opted in, drops the custom database tables.
 *
 * @package TMASD\Signals\Dispatch
 */

// Only allow this file to be called from the WordPress uninstall process.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// -------------------------------------------------------------------
// 1. Delete plugin options.
// -------------------------------------------------------------------
$options = array(
	'tmasd_phone_number_id',
	'tmasd_waba_id',
	'tmasd_access_token',
	'tmasd_webhook_verify_token',
	'tmasd_app_secret',
	'tmasd_require_consent',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// -------------------------------------------------------------------
// 2. Drop custom tables.
//
// Table removal is unconditional on uninstall because the user has
// explicitly chosen to delete the plugin and its data.  If you want
// to make this conditional (e.g. a "remove data on uninstall" setting)
// uncomment the get_option() guard below.
// -------------------------------------------------------------------

// Uncomment to make table removal opt-in:
// if ( ! get_option( 'tmasd_remove_data_on_uninstall' ) ) {
//     return;
// }

$tables = array(
	$wpdb->prefix . 'tmasd_logs',
	$wpdb->prefix . 'tmasd_template_map',
	$wpdb->prefix . 'tmasd_optins',
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are built from safe internal values.
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}
