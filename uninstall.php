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
$tmasd_options = array(
	'tmasd_phone_number_id',
	'tmasd_waba_id',
	'tmasd_access_token',
	'tmasd_webhook_verify_token',
	'tmasd_app_secret',
	'tmasd_require_consent',
	// v1.1 options.
	'tmasd_setup_meta_app_confirmed',
	'tmasd_display_phone_number',
	'tmasd_last_api_connection_test_at',
	'tmasd_last_api_connection_test_status',
	'tmasd_last_api_connection_test_error',
	'tmasd_last_webhook_received_at',
	'tmasd_last_webhook_status_update_at',
	'tmasd_setup_completed_at',
);

foreach ( $tmasd_options as $tmasd_option ) {
	delete_option( $tmasd_option );
}

// -------------------------------------------------------------------
// 2. Drop custom tables.
//
// Table removal is unconditional on uninstall because the user has
// explicitly chosen to delete the plugin and its data.  If you want
// to make this conditional (e.g. a "remove data on uninstall" setting)
// uncomment the get_option() guard below.
// -------------------------------------------------------------------

// Uncomment to make table removal opt-in.
// phpcs:disable
// if ( ! get_option( 'tmasd_remove_data_on_uninstall' ) ) {
// return;
// }
// phpcs:enable

$tmasd_tables = array(
	$wpdb->prefix . 'tmasd_logs',
	$wpdb->prefix . 'tmasd_template_map',
	$wpdb->prefix . 'tmasd_optins',
);

foreach ( $tmasd_tables as $tmasd_table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Intentional table removal during uninstall.
	$wpdb->query( "DROP TABLE IF EXISTS {$tmasd_table}" );
}
