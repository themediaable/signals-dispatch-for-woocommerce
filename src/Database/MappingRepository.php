<?php
/**
 * Template mapping repository.
 *
 * @package TMASD\Signals\Dispatch\Database
 */

declare(strict_types=1);

namespace TMASD\Signals\Dispatch\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repository for template mappings.
 *
 * Handles CRUD operations for the template mappings table.
 * Maps WooCommerce events to WhatsApp template configurations.
 *
 * @final
 */
final class MappingRepository extends AbstractRepository {

	/**
	 * Table suffix for this repository.
	 *
	 * @var string
	 */
	protected string $table_suffix = 'tmasd_template_map';

	/**
	 * Get default values for new mappings.
	 *
	 * @return array<string, mixed> Default values.
	 */
	protected function get_defaults(): array {
		$now = current_time( 'mysql' );

		return array(
			'event_key'     => '',
			'template_name' => '',
			'language'      => 'en_US',
			'mapping_json'  => '[]',
			'enabled'       => 1,
			'created_at'    => $now,
			'updated_at'    => $now,
		);
	}

	/**
	 * Find enabled mapping by event key.
	 *
	 * @param string $event_key Event key identifier.
	 * @return array<string, mixed>|null Mapping data or null.
	 */
	public function find_by_event( string $event_key ): ?array {
		$table = $this->get_table_name();
		$sql   = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe internal value.
			"SELECT * FROM {$table} WHERE event_key = %s AND enabled = 1",
			$event_key
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared above.
		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Upsert a template mapping.
	 *
	 * @param array<string, mixed> $data Mapping data.
	 * @param int                  $id   Existing mapping ID for update (0 for insert).
	 * @return int Inserted or updated mapping ID.
	 */
	public function upsert( array $data, int $id = 0 ): int {
		$now          = current_time( 'mysql' );
		$prepared     = $this->prepare_mapping_data( $data, $now );
		$prepared_arr = $prepared;

		if ( $id > 0 ) {
			$this->wpdb->update(
				$this->get_table_name(),
				$prepared_arr,
				array( 'id' => $id )
			);
			return $id;
		}

		$prepared_arr['created_at'] = $now;
		$this->wpdb->insert( $this->get_table_name(), $prepared_arr );

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Prepare mapping data for database.
	 *
	 * @param array<string, mixed> $data Mapping data.
	 * @param string               $now  Current timestamp.
	 * @return array<string, mixed> Prepared data.
	 */
	private function prepare_mapping_data( array $data, string $now ): array {
		return array(
			'event_key'     => isset( $data['event_key'] ) ? sanitize_key( $data['event_key'] ) : '',
			'template_name' => isset( $data['template_name'] ) ? sanitize_text_field( $data['template_name'] ) : '',
			'language'      => isset( $data['language'] ) ? sanitize_text_field( $data['language'] ) : 'en_US',
			'mapping_json'  => isset( $data['mapping_json'] ) ? $data['mapping_json'] : '[]',
			'enabled'       => ! empty( $data['enabled'] ) ? 1 : 0,
			'updated_at'    => $now,
		);
	}

	/**
	 * Get all available event keys with labels.
	 *
	 * @return array<string, string> Event key to label map.
	 */
	public function get_available_events(): array {
		return array(
			'order_status_processing' => __( 'Order Processing', 'signals-dispatch-for-woocommerce' ),
			'order_status_completed'  => __( 'Order Completed', 'signals-dispatch-for-woocommerce' ),
			'order_status_on_hold'    => __( 'Order On Hold', 'signals-dispatch-for-woocommerce' ),
			'order_status_cancelled'  => __( 'Order Cancelled', 'signals-dispatch-for-woocommerce' ),
		);
	}

	/**
	 * Get all available variable resolvers with labels.
	 *
	 * @return array<string, string> Resolver key to label map.
	 */
	public function get_available_variables(): array {
		return array(
			'order_id'            => __( 'Order ID', 'signals-dispatch-for-woocommerce' ),
			'order_number'        => __( 'Order Number', 'signals-dispatch-for-woocommerce' ),
			'order_total'         => __( 'Order Total', 'signals-dispatch-for-woocommerce' ),
			'order_currency'      => __( 'Currency', 'signals-dispatch-for-woocommerce' ),
			'billing_first_name'  => __( 'Billing First Name', 'signals-dispatch-for-woocommerce' ),
			'billing_last_name'   => __( 'Billing Last Name', 'signals-dispatch-for-woocommerce' ),
			'billing_phone'       => __( 'Billing Phone', 'signals-dispatch-for-woocommerce' ),
			'billing_email'       => __( 'Billing Email', 'signals-dispatch-for-woocommerce' ),
			'shipping_first_name' => __( 'Shipping First Name', 'signals-dispatch-for-woocommerce' ),
			'shipping_last_name'  => __( 'Shipping Last Name', 'signals-dispatch-for-woocommerce' ),
			'status'              => __( 'Order Status', 'signals-dispatch-for-woocommerce' ),
			'site_name'           => __( 'Site Name', 'signals-dispatch-for-woocommerce' ),
		);
	}
}
