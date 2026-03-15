<?php
/**
 * Log repository for message logs.
 *
 * @package TMASD\Signals\Dispatch\Database
 */

declare(strict_types=1);

namespace TMASD\Signals\Dispatch\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repository for WhatsApp message logs.
 *
 * Handles CRUD operations for the message logs table.
 * Each log entry represents a sent or attempted WhatsApp message.
 *
 * @final
 */
final class LogRepository extends AbstractRepository {

	/**
	 * Table suffix for this repository.
	 *
	 * @var string
	 */
	protected string $table_suffix = 'tmasd_logs';

	/**
	 * Get default values for new log entries.
	 *
	 * @return array<string, mixed> Default values.
	 */
	protected function get_defaults(): array {
		$now = current_time( 'mysql' );

		return array(
			'order_id'      => null,
			'phone_e164'    => '',
			'template_name' => '',
			'payload_json'  => '{}',
			'response_json' => '{}',
			'status'        => 'queued',
			'wa_message_id' => null,
			'error_code'    => null,
			'error_message' => null,
			'created_at'    => $now,
			'updated_at'    => $now,
		);
	}

	/**
	 * Find log by WhatsApp message ID.
	 *
	 * @param string $wa_message_id WhatsApp message ID.
	 * @return array<string, mixed>|null Log entry or null.
	 */
	public function find_by_message_id( string $wa_message_id ): ?array {
		$table = $this->get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
		$sql = $this->wpdb->prepare( "SELECT * FROM {$table} WHERE wa_message_id = %s", $wa_message_id );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is prepared above.
		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Update log by WhatsApp message ID.
	 *
	 * @param string               $wa_message_id WhatsApp message ID.
	 * @param array<string, mixed> $data          Data to update.
	 * @return bool True on success.
	 */
	public function update_by_message_id( string $wa_message_id, array $data ): bool {
		$data['updated_at'] = current_time( 'mysql' );

		return (bool) $this->wpdb->update(
			$this->get_table_name(),
			$data,
			array( 'wa_message_id' => $wa_message_id )
		);
	}

	/**
	 * List logs with pagination and filtering.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array{rows: array<int, array<string, mixed>>, total: int} Paginated results.
	 */
	public function list_paginated( array $args = array() ): array {
		$defaults = array(
			'paged'    => 1,
			'per_page' => 20,
			'status'   => '',
			'search'   => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		$where  = $this->build_where_clause( $args );
		$params = $this->build_where_params( $args );

		return array(
			'rows'  => $this->execute_list_query( $where, $params, $args ),
			'total' => $this->execute_count_query( $where, $params ),
		);
	}

	/**
	 * Build WHERE clause for list query.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return string WHERE clause.
	 */
	private function build_where_clause( array $args ): string {
		$where = 'WHERE 1=1';

		if ( ! empty( $args['status'] ) ) {
			$where .= ' AND status = %s';
		}

		if ( ! empty( $args['search'] ) ) {
			$where .= ' AND (template_name LIKE %s OR phone_e164 LIKE %s OR CAST(order_id AS CHAR) LIKE %s)';
		}

		return $where;
	}

	/**
	 * Build parameters for WHERE clause.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<int, string> Parameters array.
	 */
	private function build_where_params( array $args ): array {
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$params[] = $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
			array_push( $params, $like, $like, $like );
		}

		return $params;
	}

	/**
	 * Execute the list query with pagination.
	 *
	 * @param string               $where  WHERE clause.
	 * @param array<int, string>   $params Query parameters.
	 * @param array<string, mixed> $args   Query arguments.
	 * @return array<int, array<string, mixed>> Result rows.
	 */
	private function execute_list_query( string $where, array $params, array $args ): array {
		$table     = $this->get_table_name();
		$offset    = ( $args['paged'] - 1 ) * $args['per_page'];
		$limit_sql = $this->wpdb->prepare( ' LIMIT %d OFFSET %d', $args['per_page'], $offset );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and where are safe.
		$sql = "SELECT * FROM {$table} {$where} ORDER BY id DESC";

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is dynamic with safe values.
			$sql = $this->wpdb->prepare( $sql, $params );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL and limit are prepared above.
		$rows = $this->wpdb->get_results( $sql . $limit_sql, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Execute count query for pagination.
	 *
	 * @param string             $where  WHERE clause.
	 * @param array<int, string> $params Query parameters.
	 * @return int Total count.
	 */
	private function execute_count_query( string $where, array $params ): int {
		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and where are safe.
		$sql = "SELECT COUNT(*) FROM {$table} {$where}";

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is dynamic with safe values.
			$sql = $this->wpdb->prepare( $sql, $params );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is prepared above.
		return (int) $this->wpdb->get_var( $sql );
	}

	/**
	 * Get status counts for the last 24 hours.
	 *
	 * @return array<string, int> Status counts keyed by status.
	 */
	public function get_status_counts_last_24h(): array {
		$table = $this->get_table_name();
		$since = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );

		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe internal value.
			"SELECT status, COUNT(*) as count FROM {$table} WHERE created_at >= %s GROUP BY status",
			$since
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is prepared above.
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		$counts = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$counts[ $row['status'] ] = (int) $row['count'];
			}
		}

		return $counts;
	}

	/**
	 * Delete all log entries.
	 *
	 * @return int Number of rows deleted.
	 */
	public function delete_all(): int {
		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe internal value.
		return (int) $this->wpdb->query( "DELETE FROM {$table}" );
	}

	/**
	 * Find log rows associated with WooCommerce orders belonging to a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<int, array<string, mixed>> Log rows.
	 */
	public function find_by_user_id( int $user_id ): array {
		$table = $this->get_table_name();
		$sql   = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
			"SELECT l.* FROM {$table} l
			INNER JOIN {$this->wpdb->posts} p ON p.ID = l.order_id
			WHERE p.post_author = %d AND l.order_id > 0
			ORDER BY l.id DESC",
			$user_id
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is prepared above.
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Anonymise the phone_e164 field in log rows owned by a user.
	 *
	 * The order records are retained for business/accounting purposes;
	 * only the personal phone number is anonymised.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return int Number of rows updated.
	 */
	public function anonymise_phone_by_user_id( int $user_id ): int {
		$table   = $this->get_table_name();
		$wp_posts = $this->wpdb->posts;

		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are safe internal values.
			"UPDATE {$table} l
			INNER JOIN {$wp_posts} p ON p.ID = l.order_id
			SET l.phone_e164 = 'ANONYMISED', l.updated_at = %s
			WHERE p.post_author = %d AND l.order_id > 0 AND l.phone_e164 != 'ANONYMISED'",
			current_time( 'mysql' ),
			$user_id
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is prepared above.
		$result = $this->wpdb->query( $sql );

		return is_int( $result ) ? $result : 0;
	}
}
