<?php
/**
 * Abstract base repository.
 *
 * @package TMASD\Signals\Dispatch\Database
 */

declare(strict_types=1);

namespace TMASD\Signals\Dispatch\Database;

use TMASD\Signals\Dispatch\Contracts\RepositoryInterface;
use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract base class for database repositories.
 *
 * Provides common database operations and enforces the Repository pattern.
 * Child classes must define their table name and default data structure.
 *
 * @abstract
 */
abstract class AbstractRepository implements RepositoryInterface {

	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	protected wpdb $wpdb;

	/**
	 * Table name suffix (without prefix).
	 *
	 * @var string
	 */
	protected string $table_suffix = '';

	/**
	 * Constructor.
	 *
	 * @param wpdb|null $wpdb WordPress database instance.
	 */
	public function __construct( ?wpdb $wpdb = null ) {
		$this->wpdb = $wpdb ?? $GLOBALS['wpdb'];
	}

	/**
	 * Get the full table name with prefix.
	 *
	 * @return string Full table name.
	 */
	public function get_table_name(): string {
		return $this->wpdb->prefix . $this->table_suffix;
	}

	/**
	 * Get default values for new records.
	 *
	 * @return array<string, mixed> Default values.
	 */
	abstract protected function get_defaults(): array;

	/**
	 * Find a record by ID.
	 *
	 * @param int $id Record ID.
	 * @return array<string, mixed>|null Record data or null.
	 */
	public function find( int $id ): ?array {
		$table = $this->get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
		$sql = $this->wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared above.
		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get all records ordered by ID descending.
	 *
	 * @return array<int, array<string, mixed>> Array of records.
	 */
	public function all(): array {
		$table = $this->get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is safe.
		$results = $this->wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Insert a new record.
	 *
	 * @param array<string, mixed> $data Record data.
	 * @return int Inserted record ID.
	 */
	public function insert( array $data ): int {
		$data = wp_parse_args( $data, $this->get_defaults() );
		$this->wpdb->insert( $this->get_table_name(), $data );

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update an existing record.
	 *
	 * @param int                  $id   Record ID.
	 * @param array<string, mixed> $data Data to update.
	 * @return bool True on success.
	 */
	public function update( int $id, array $data ): bool {
		$data['updated_at'] = current_time( 'mysql' );

		return (bool) $this->wpdb->update(
			$this->get_table_name(),
			$data,
			array( 'id' => $id )
		);
	}

	/**
	 * Delete a record by ID.
	 *
	 * @param int $id Record ID.
	 * @return bool True on success.
	 */
	public function delete( int $id ): bool {
		return (bool) $this->wpdb->delete(
			$this->get_table_name(),
			array( 'id' => $id )
		);
	}

	/**
	 * Count total records.
	 *
	 * @return int Total count.
	 */
	public function count(): int {
		$table = $this->get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is safe.
		return (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}
}
