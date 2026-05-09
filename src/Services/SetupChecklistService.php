<?php
/**
 * Setup checklist service.
 *
 * @package TMASD\Signals\Dispatch\Services
 * @since 1.1.0
 */

declare(strict_types=1);

namespace TMASD\Signals\Dispatch\Services;

use TMASD\Signals\Dispatch\Database\MappingRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides setup checklist item statuses driven by real plugin state.
 *
 * Each item has: id, label, status (complete|incomplete|warning|error).
 *
 * @since 1.1.0
 */
final class SetupChecklistService {

	/**
	 * Mapping repository.
	 *
	 * @var MappingRepository
	 * @since 1.1.0
	 */
	private MappingRepository $mapping_repo;

	/**
	 * Constructor.
	 *
	 * @param MappingRepository $mapping_repo Mapping repository.
	 * @since 1.1.0
	 */
	public function __construct( MappingRepository $mapping_repo ) {
		$this->mapping_repo = $mapping_repo;
	}

	/**
	 * Get the full setup checklist.
	 *
	 * @return array<int, array<string, string>> List of checklist items.
	 * @since 1.1.0
	 */
	public function get_checklist(): array {
		return array(
			$this->item(
				'phone_number_id',
				__( 'Phone Number ID added', 'signals-dispatch-for-woocommerce' ),
				'' !== get_option( \TMASD_OPTION_PHONE_NUMBER_ID, '' )
			),
			$this->item(
				'waba_id',
				__( 'WABA ID added', 'signals-dispatch-for-woocommerce' ),
				'' !== get_option( \TMASD_OPTION_WABA_ID, '' )
			),
			$this->item(
				'access_token',
				__( 'Access token saved', 'signals-dispatch-for-woocommerce' ),
				'' !== get_option( \TMASD_OPTION_ACCESS_TOKEN, '' )
			),
			$this->item(
				'app_secret',
				__( 'App secret saved', 'signals-dispatch-for-woocommerce' ),
				'' !== get_option( \TMASD_OPTION_APP_SECRET, '' )
			),
			$this->item(
				'webhook_verify_token',
				__( 'Webhook verify token added', 'signals-dispatch-for-woocommerce' ),
				'' !== get_option( \TMASD_OPTION_WEBHOOK_VERIFY_TOKEN, '' )
			),
			$this->item(
				'api_connection',
				__( 'API connection tested', 'signals-dispatch-for-woocommerce' ),
				'pass' === get_option( \TMASD_OPTION_LAST_API_TEST_STATUS, '' )
			),
			$this->item(
				'webhook_received',
				__( 'Webhook verified (at least one webhook received)', 'signals-dispatch-for-woocommerce' ),
				'' !== get_option( \TMASD_OPTION_LAST_WEBHOOK_RECEIVED_AT, '' )
			),
			$this->item(
				'dispatch_rule',
				__( 'At least one dispatch rule enabled', 'signals-dispatch-for-woocommerce' ),
				$this->has_enabled_rule()
			),
		);
	}

	/**
	 * Count how many checklist items are complete.
	 *
	 * @return int Number of complete items.
	 * @since 1.1.0
	 */
	public function count_complete(): int {
		$count = 0;
		foreach ( $this->get_checklist() as $item ) {
			if ( 'complete' === $item['status'] ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Build a single checklist item array.
	 *
	 * @param string $id     Item identifier.
	 * @param string $label  Human-readable label.
	 * @param bool   $done   Whether the item is complete.
	 * @return array<string, string>
	 * @since 1.1.0
	 */
	private function item( string $id, string $label, bool $done ): array {
		return array(
			'id'     => $id,
			'label'  => $label,
			'status' => $done ? 'complete' : 'incomplete',
		);
	}

	/**
	 * Check whether at least one enabled dispatch rule exists.
	 *
	 * @return bool True if an enabled rule exists.
	 * @since 1.1.0
	 */
	private function has_enabled_rule(): bool {
		foreach ( array_keys( $this->mapping_repo->get_available_events() ) as $event_key ) {
			if ( null !== $this->mapping_repo->find_by_event( $event_key ) ) {
				return true;
			}
		}
		return false;
	}
}
