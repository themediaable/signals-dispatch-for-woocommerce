<?php
/**
 * Database schema manager.
 *
 * @package TMASD\Signals\Dispatch\Database
 */

declare(strict_types=1);

namespace TMASD\Signals\Dispatch\Database;

use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database schema manager.
 *
 * Handles database table creation and migrations.
 * Single Responsibility: Only manages schema, not data operations.
 *
 * @final
 */
final class SchemaManager {

	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Log repository for table name.
	 *
	 * @var LogRepository
	 */
	private LogRepository $log_repo;

	/**
	 * Mapping repository for table name.
	 *
	 * @var MappingRepository
	 */
	private MappingRepository $mapping_repo;

	/**
	 * Opt-in repository for table name.
	 *
	 * @var OptinRepository
	 */
	private OptinRepository $optin_repo;

	/**
	 * Constructor.
	 *
	 * @param wpdb|null              $wpdb         WordPress database instance.
	 * @param LogRepository|null     $log_repo     Log repository.
	 * @param MappingRepository|null $mapping_repo Mapping repository.
	 * @param OptinRepository|null   $optin_repo   Opt-in repository.
	 */
	public function __construct(
		?wpdb $wpdb = null,
		?LogRepository $log_repo = null,
		?MappingRepository $mapping_repo = null,
		?OptinRepository $optin_repo = null
	) {
		$this->wpdb         = $wpdb ?? $GLOBALS['wpdb'];
		$this->log_repo     = $log_repo ?? new LogRepository( $this->wpdb );
		$this->mapping_repo = $mapping_repo ?? new MappingRepository( $this->wpdb );
		$this->optin_repo   = $optin_repo ?? new OptinRepository( $this->wpdb );
	}

	/**
	 * Create all plugin tables.
	 *
	 * @return void
	 */
	public function create_tables(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$this->create_logs_table();
		$this->create_mappings_table();
		$this->create_optins_table();
	}

	/**
	 * Create the logs table.
	 *
	 * @return void
	 */
	private function create_logs_table(): void {
		$table           = $this->log_repo->get_table_name();
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NULL,
			phone_e164 varchar(32) NOT NULL,
			template_name varchar(191) NOT NULL,
			payload_json longtext NOT NULL,
			response_json longtext NOT NULL,
			status varchar(20) NOT NULL,
			wa_message_id varchar(191) NULL,
			error_code varchar(191) NULL,
			error_message text NULL,
			trigger_source varchar(50) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY wa_message_id (wa_message_id),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Create the template mappings table.
	 *
	 * @return void
	 */
	private function create_mappings_table(): void {
		$table           = $this->mapping_repo->get_table_name();
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_key varchar(191) NOT NULL,
			template_name varchar(191) NOT NULL,
			language varchar(20) NOT NULL DEFAULT 'en_US',
			mapping_json longtext NOT NULL,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY event_key (event_key),
			KEY enabled (enabled)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Create the opt-ins table.
	 *
	 * @return void
	 */
	private function create_optins_table(): void {
		$table           = $this->optin_repo->get_table_name();
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NULL,
			order_id bigint(20) unsigned NULL,
			phone_e164 varchar(32) NOT NULL,
			consent tinyint(1) NOT NULL DEFAULT 0,
			consent_source varchar(20) NOT NULL,
			consent_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY phone_e164 (phone_e164),
			KEY consent (consent)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Drop all plugin tables.
	 *
	 * @return void
	 */
	public function drop_tables(): void {
		$tables = array(
			$this->log_repo->get_table_name(),
			$this->mapping_repo->get_table_name(),
			$this->optin_repo->get_table_name(),
		);

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is from internal source.
			$this->wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}
	}
}
