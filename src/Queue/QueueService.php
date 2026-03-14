<?php
/**
 * Queue service for message scheduling.
 *
 * @package TMASD\Signals\Dispatch\Queue
 */

declare(strict_types=1);

namespace TMASD\Signals\Dispatch\Queue;

use TMASD\Signals\Dispatch\Contracts\ApiClientInterface;
use TMASD\Signals\Dispatch\Contracts\QueueInterface;
use TMASD\Signals\Dispatch\Contracts\TemplateMapperInterface;
use TMASD\Signals\Dispatch\Core\AbstractService;
use TMASD\Signals\Dispatch\Database\LogRepository;
use TMASD\Signals\Dispatch\Database\MappingRepository;
use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queue service for scheduling and executing template message jobs.
 *
 * Handles WooCommerce order status changes and schedules WhatsApp
 * template messages using Action Scheduler.
 *
 * @final
 */
final class QueueService extends AbstractService implements QueueInterface {

	/**
	 * Maximum retry attempts.
	 *
	 * @var int
	 */
	private const MAX_RETRY_ATTEMPTS = 2;

	/**
	 * Retry delay in seconds.
	 *
	 * @var int
	 */
	private const RETRY_DELAY_SECONDS = 10;

	/**
	 * Log repository.
	 *
	 * @var LogRepository
	 */
	private LogRepository $log_repo;

	/**
	 * Mapping repository.
	 *
	 * @var MappingRepository
	 */
	private MappingRepository $mapping_repo;

	/**
	 * API client service.
	 *
	 * @var ApiClientInterface
	 */
	private ApiClientInterface $api_client;

	/**
	 * Template mapper service.
	 *
	 * @var TemplateMapperInterface
	 */
	private TemplateMapperInterface $template_mapper;

	/**
	 * Constructor.
	 *
	 * @param LogRepository           $log_repo        Log repository.
	 * @param MappingRepository       $mapping_repo    Mapping repository.
	 * @param ApiClientInterface      $api_client      API client service.
	 * @param TemplateMapperInterface $template_mapper Template mapper service.
	 */
	public function __construct(
		LogRepository $log_repo,
		MappingRepository $mapping_repo,
		ApiClientInterface $api_client,
		TemplateMapperInterface $template_mapper
	) {
		$this->log_repo        = $log_repo;
		$this->mapping_repo    = $mapping_repo;
		$this->api_client      = $api_client;
		$this->template_mapper = $template_mapper;
	}

	/**
	 * Boot the service and register hooks.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_status_changed' ), 10, 4 );
		add_action( \TMASD_ACTION_SEND_TEMPLATE, array( $this, 'handle_send_template_message' ), 10, 3 );
	}

	/**
	 * Handle order status change.
	 *
	 * @param int      $order_id   Order ID.
	 * @param string   $old_status Previous status.
	 * @param string   $new_status New status.
	 * @param WC_Order $order      Order object.
	 * @return void
	 */
	public function handle_order_status_changed(
		$order_id,
		string $old_status,
		string $new_status,
		WC_Order $order
	): void {
		$order_id = (int) $order_id;

		$event_key = $this->map_status_to_event( $new_status );

		if ( '' === $event_key ) {
			return;
		}

		$mapping = $this->mapping_repo->find_by_event( $event_key );

		if ( null === $mapping ) {
			return;
		}

		$this->schedule_send( $order_id, $event_key, 0 );
	}

	/**
	 * Schedule a template message.
	 *
	 * @param int    $order_id  Order ID.
	 * @param string $event_key Event key.
	 * @param int    $attempts  Retry count.
	 * @return void
	 */
	public function schedule_send( int $order_id, string $event_key, int $attempts = 0 ): void {
		$args = array( $order_id, $event_key, $attempts );

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( \TMASD_ACTION_SEND_TEMPLATE, $args, 'tmasd' );
			return;
		}

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				time() + self::RETRY_DELAY_SECONDS,
				\TMASD_ACTION_SEND_TEMPLATE,
				$args,
				'tmasd'
			);
			return;
		}
	}

	/**
	 * Handle sending a template message.
	 *
	 * @param int    $order_id  Order ID.
	 * @param string $event_key Event key.
	 * @param int    $attempts  Retry count.
	 * @return void
	 */
	public function handle_send_template_message( $order_id, $event_key, $attempts = 0 ): void {
		$order_id  = (int) $order_id;
		$event_key = (string) $event_key;
		$attempts  = (int) $attempts;

		if ( ! $this->validate_send_request( $order_id, $event_key ) ) {
			$this->log_skipped( $order_id, $event_key, 'Invalid request: missing order ID or event key.' );
			return;
		}

		$mapping = $this->mapping_repo->find_by_event( $event_key );

		if ( null === $mapping ) {
			$this->log_skipped( $order_id, $event_key, 'No enabled dispatch rule found for event: ' . $event_key );
			return;
		}

		$payload = $this->template_mapper->build_from_order( $order_id, $mapping );

		if ( $this->is_payload_invalid( $payload ) ) {
			$this->log_skipped(
				$order_id,
				$event_key,
				'Order has no valid billing phone number.',
				$payload['template_name'] ?? ''
			);
			return;
		}

		$log_id = $this->create_log_entry( $order_id, $payload );
		$result = $this->send_message( $payload );

		$this->update_log_with_result( $log_id, $result, $order_id, $event_key, $attempts );
	}

	/**
	 * Validate send request parameters.
	 *
	 * @param int    $order_id  Order ID.
	 * @param string $event_key Event key.
	 * @return bool True if valid.
	 */
	private function validate_send_request( int $order_id, string $event_key ): bool {
		return $order_id > 0 && '' !== $event_key;
	}

	/**
	 * Check if payload is invalid.
	 *
	 * @param array<string, mixed> $payload Payload array.
	 * @return bool True if invalid.
	 */
	private function is_payload_invalid( array $payload ): bool {
		return empty( $payload['phone_e164'] );
	}

	/**
	 * Create initial log entry.
	 *
	 * @param int                  $order_id Order ID.
	 * @param array<string, mixed> $payload  Payload data.
	 * @return int Log ID.
	 */
	private function create_log_entry( int $order_id, array $payload ): int {
		$payload_json = wp_json_encode( $payload );

		return $this->log_repo->insert(
			array(
				'order_id'      => $order_id,
				'phone_e164'    => $payload['phone_e164'],
				'template_name' => $payload['template_name'],
				'payload_json'  => $payload_json ? $payload_json : '{}',
				'response_json' => '{}',
				'status'        => 'queued',
			)
		);
	}

	/**
	 * Log a skipped dispatch attempt so the admin can see why a message was not sent.
	 *
	 * @param int    $order_id      Order ID.
	 * @param string $event_key     Event key.
	 * @param string $reason        Human-readable reason the message was skipped.
	 * @param string $template_name Template name if known.
	 * @return void
	 */
	private function log_skipped( int $order_id, string $event_key, string $reason, string $template_name = '' ): void {
		$this->log_repo->insert(
			array(
				'order_id'      => $order_id > 0 ? $order_id : 0,
				'phone_e164'    => '',
				'template_name' => $template_name,
				'payload_json'  => wp_json_encode( array( 'event_key' => $event_key ) ),
				'response_json' => '{}',
				'status'        => 'failed',
				'error_message' => $reason,
			)
		);
	}

	/**
	 * Send the template message.
	 *
	 * @param array<string, mixed> $payload Message payload.
	 * @return array<string, mixed> API result.
	 */
	private function send_message( array $payload ): array {
		return $this->api_client->send_template_message(
			$payload['phone_e164'],
			$payload['template_name'],
			$payload['language'],
			$payload['variables']
		);
	}

	/**
	 * Update log with API result.
	 *
	 * @param int                  $log_id    Log ID.
	 * @param array<string, mixed> $result    API result.
	 * @param int                  $order_id  Order ID.
	 * @param string               $event_key Event key.
	 * @param int                  $attempts  Attempt count.
	 * @return void
	 */
	private function update_log_with_result(
		int $log_id,
		array $result,
		int $order_id,
		string $event_key,
		int $attempts
	): void {
		$update = $this->build_log_update( $result, $order_id, $event_key, $attempts );
		$this->log_repo->update( $log_id, $update );
	}

	/**
	 * Build log update data from result.
	 *
	 * @param array<string, mixed> $result    API result.
	 * @param int                  $order_id  Order ID.
	 * @param string               $event_key Event key.
	 * @param int                  $attempts  Attempt count.
	 * @return array<string, mixed> Update data.
	 */
	private function build_log_update(
		array $result,
		int $order_id,
		string $event_key,
		int $attempts
	): array {
		$payload_json  = wp_json_encode( isset( $result['payload'] ) ? $result['payload'] : array() );
		$response_json = wp_json_encode( isset( $result['response'] ) ? $result['response'] : array() );

		$update = array(
			'payload_json'  => $payload_json ? $payload_json : '{}',
			'response_json' => $response_json ? $response_json : '{}',
		);

		if ( ! empty( $result['success'] ) ) {
			$update['status']        = 'sent';
			$update['wa_message_id'] = $result['response']['messages'][0]['id'] ?? null;
		} else {
			$update['status']        = 'failed';
			$update['error_message'] = $result['error'] ?? 'Unknown error';
			$update['error_code']    = $result['response']['error']['code'] ?? null;

			if ( $attempts < self::MAX_RETRY_ATTEMPTS ) {
				$this->schedule_send( $order_id, $event_key, $attempts + 1 );
			}
		}

		return $update;
	}

	/**
	 * Map order status to event key.
	 *
	 * @param string $status WooCommerce status.
	 * @return string Event key or empty.
	 */
	private function map_status_to_event( string $status ): string {
		$map = array(
			'processing' => 'order_status_processing',
			'completed'  => 'order_status_completed',
			'on-hold'    => 'order_status_on_hold',
			'cancelled'  => 'order_status_cancelled',
		);

		return $map[ $status ] ?? '';
	}
}
