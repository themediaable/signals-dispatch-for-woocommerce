<?php
/**
 * Opt-in repository for customer consent.
 *
 * @package TMASD\Signals\Dispatch\Database
 */

declare(strict_types=1);

namespace TMASD\Signals\Dispatch\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repository for customer opt-in records.
 *
 * Handles CRUD operations for the customer opt-in consent table.
 * Tracks WhatsApp messaging consent per phone number.
 *
 * @final
 */
final class OptinRepository extends AbstractRepository {

	/**
	 * Table suffix for this repository.
	 *
	 * @var string
	 */
	protected string $table_suffix = 'tmasd_optins';

	/**
	 * Get default values for new opt-in records.
	 *
	 * @return array<string, mixed> Default values.
	 */
	protected function get_defaults(): array {
		return array(
			'user_id'        => null,
			'order_id'       => null,
			'phone_e164'     => '',
			'consent'        => 0,
			'consent_source' => 'checkout',
			'consent_at'     => current_time( 'mysql' ),
		);
	}

	/**
	 * Find opt-in record by phone number.
	 *
	 * @param string $phone_e164 Phone number in E.164 format.
	 * @return array<string, mixed>|null Opt-in record or null.
	 */
	public function find_by_phone( string $phone_e164 ): ?array {
		$table = $this->get_table_name();
		$sql   = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe internal value.
			"SELECT * FROM {$table} WHERE phone_e164 = %s ORDER BY id DESC LIMIT 1",
			$phone_e164
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared above.
		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Check if phone number has valid consent.
	 *
	 * @param string $phone_e164 Phone number in E.164 format.
	 * @return bool True if consent is given.
	 */
	public function has_consent( string $phone_e164 ): bool {
		$record = $this->find_by_phone( $phone_e164 );

		if ( null === $record ) {
			return false;
		}

		return (bool) $record['consent'];
	}

	/**
	 * Record customer consent.
	 *
	 * @param string   $phone_e164 Phone number in E.164 format.
	 * @param bool     $consent    Consent status.
	 * @param string   $source     Consent source (checkout, admin, etc.).
	 * @param int|null $user_id    Optional user ID.
	 * @param int|null $order_id   Optional order ID.
	 * @return int Inserted record ID.
	 */
	public function record_consent(
		string $phone_e164,
		bool $consent,
		string $source = 'checkout',
		?int $user_id = null,
		?int $order_id = null
	): int {
		return $this->insert(
			array(
				'phone_e164'     => $phone_e164,
				'consent'        => $consent ? 1 : 0,
				'consent_source' => $source,
				'user_id'        => $user_id,
				'order_id'       => $order_id,
				'consent_at'     => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Get consent statistics.
	 *
	 * @return array{total: int, opted_in: int, opted_out: int} Statistics.
	 */
	public function get_statistics(): array {
		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
		$sql = "SELECT 
			COUNT(*) as total,
			SUM(CASE WHEN consent = 1 THEN 1 ELSE 0 END) as opted_in,
			SUM(CASE WHEN consent = 0 THEN 1 ELSE 0 END) as opted_out
			FROM {$table}";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL has no user input.
		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		if ( ! is_array( $row ) ) {
			return array(
				'total'     => 0,
				'opted_in'  => 0,
				'opted_out' => 0,
			);
		}

		return array(
			'total'     => (int) $row['total'],
			'opted_in'  => (int) $row['opted_in'],
			'opted_out' => (int) $row['opted_out'],
		);
	}

	/**
	 * Find consent records by WordPress user ID.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<int, array<string, mixed>> Consent records.
	 */
	public function find_by_user_id( int $user_id ): array {
		$table = $this->get_table_name();
		$sql   = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
			"SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC",
			$user_id
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared above.
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Find consent record by order ID.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array<string, mixed>|null Consent record or null.
	 */
	public function find_by_order_id( int $order_id ): ?array {
		$table = $this->get_table_name();
		$sql   = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe internal value.
			"SELECT * FROM {$table} WHERE order_id = %d ORDER BY id DESC LIMIT 1",
			$order_id
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared above.
		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Delete consent records for a given WordPress user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return int Number of rows deleted.
	 */
	public function delete_by_user_id( int $user_id ): int {
		$result = $this->wpdb->delete(
			$this->get_table_name(),
			array( 'user_id' => $user_id ),
			array( '%d' )
		);

		return is_int( $result ) ? $result : 0;
	}

	/**
	 * Find consent records by customer email.
	 *
	 * Combines user-based and order-based lookup for HPOS compatibility
	 * and guest customer support.
	 *
	 * @param string $email Customer email address.
	 * @return array<int, array<string, mixed>> Consent records.
	 */
	public function find_by_email( string $email ): array {
		$seen_ids = array();
		$records  = array();

		// Registered user lookup.
		$user = get_user_by( 'email', $email );
		if ( $user ) {
			foreach ( $this->find_by_user_id( $user->ID ) as $row ) {
				$seen_ids[ (int) $row['id'] ] = true;
				$records[]                    = $row;
			}
		}

		// Order-based lookup (catches guests + HPOS).
		if ( function_exists( 'wc_get_orders' ) ) {
			$order_ids = wc_get_orders(
				array(
					'customer' => $email,
					'return'   => 'ids',
					'limit'    => -1,
				)
			);

			if ( ! empty( $order_ids ) ) {
				$table        = $this->get_table_name();
				$placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );

				$sql = $this->wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table name and placeholders are safe.
					"SELECT * FROM {$table} WHERE order_id IN ({$placeholders}) ORDER BY id DESC",
					$order_ids
				);

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared above.
				$order_rows = $this->wpdb->get_results( $sql, ARRAY_A );

				if ( is_array( $order_rows ) ) {
					foreach ( $order_rows as $row ) {
						if ( ! isset( $seen_ids[ (int) $row['id'] ] ) ) {
							$seen_ids[ (int) $row['id'] ] = true;
							$records[]                    = $row;
						}
					}
				}
			}
		}

		return $records;
	}

	/**
	 * Delete consent records by customer email.
	 *
	 * Combines user-based and order-based deletion for HPOS compatibility
	 * and guest customer support.
	 *
	 * @param string $email Customer email address.
	 * @return int Number of rows deleted.
	 */
	public function delete_by_email( string $email ): int {
		$total = 0;

		// Delete by user ID for registered users.
		$user = get_user_by( 'email', $email );
		if ( $user ) {
			$total += $this->delete_by_user_id( $user->ID );
		}

		// Delete by order IDs (catches guests + HPOS).
		if ( function_exists( 'wc_get_orders' ) ) {
			$order_ids = wc_get_orders(
				array(
					'customer' => $email,
					'return'   => 'ids',
					'limit'    => -1,
				)
			);

			if ( ! empty( $order_ids ) ) {
				$table        = $this->get_table_name();
				$placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );

				$sql = $this->wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table name and placeholders are safe.
					"DELETE FROM {$table} WHERE order_id IN ({$placeholders})",
					$order_ids
				);

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared above.
				$result = $this->wpdb->query( $sql );
				$total += is_int( $result ) ? $result : 0;
			}
		}

		return $total;
	}
}
