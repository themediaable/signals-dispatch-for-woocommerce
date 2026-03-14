<?php
/**
 * Webhook controller for WhatsApp callbacks.
 *
 * @package TMASD\Signals\Dispatch\API
 */

declare(strict_types=1);

namespace TMASD\Signals\Dispatch\API;

use TMASD\Signals\Dispatch\Core\AbstractService;
use TMASD\Signals\Dispatch\Database\LogRepository;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Webhook controller for WhatsApp message status callbacks.
 *
 * Handles incoming webhooks from the WhatsApp Business API
 * and updates message delivery statuses.
 *
 * @final
 */
final class WebhookController extends AbstractService {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private const API_NAMESPACE = 'signals/v1';

	/**
	 * REST API route.
	 *
	 * @var string
	 */
	private const API_ROUTE = '/webhook';

	/**
	 * Log repository.
	 *
	 * @var LogRepository
	 */
	private LogRepository $log_repo;

	/**
	 * Constructor.
	 *
	 * @param LogRepository $log_repo Log repository.
	 */
	public function __construct( LogRepository $log_repo ) {
		$this->log_repo = $log_repo;
	}

	/**
	 * Boot the service and register routes.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::API_NAMESPACE,
			self::API_ROUTE,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'handle_verify' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'handle_webhook' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Handle webhook verification (GET request).
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_verify( WP_REST_Request $request ): WP_REST_Response {
		$mode      = $request->get_param( 'hub_mode' );
		$token     = $request->get_param( 'hub_verify_token' );
		$challenge = $request->get_param( 'hub_challenge' );

		if ( ! $this->verify_token( $mode, $token ) ) {
			return new WP_REST_Response( 'Forbidden', 403 );
		}

		return new WP_REST_Response( $challenge, 200 );
	}

	/**
	 * Verify the webhook token.
	 *
	 * @param string|null $mode  Hub mode.
	 * @param string|null $token Token to verify.
	 * @return bool True if valid.
	 */
	private function verify_token( ?string $mode, ?string $token ): bool {
		if ( 'subscribe' !== $mode ) {
			return false;
		}

		$stored_token = $this->get_option( \TMASD_OPTION_WEBHOOK_VERIFY_TOKEN );

		return '' !== $stored_token && $token === $stored_token;
	}

	/**
	 * Handle incoming webhook (POST request).
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_webhook( WP_REST_Request $request ): WP_REST_Response {
		$body = $request->get_json_params();

		if ( ! $this->is_valid_webhook_body( $body ) ) {
			return new WP_REST_Response( 'OK', 200 );
		}

		$this->process_webhook_entries( $body );

		return new WP_REST_Response( 'OK', 200 );
	}

	/**
	 * Validate webhook body structure.
	 *
	 * @param array<string, mixed>|null $body Webhook body.
	 * @return bool True if valid.
	 */
	private function is_valid_webhook_body( ?array $body ): bool {
		return ! empty( $body['entry'] ) && is_array( $body['entry'] );
	}

	/**
	 * Process webhook entries.
	 *
	 * @param array<string, mixed> $body Webhook body.
	 * @return void
	 */
	private function process_webhook_entries( array $body ): void {
		foreach ( $body['entry'] as $entry ) {
			$this->process_entry( $entry );
		}
	}

	/**
	 * Process a single webhook entry.
	 *
	 * @param array<string, mixed> $entry Entry data.
	 * @return void
	 */
	private function process_entry( array $entry ): void {
		if ( empty( $entry['changes'] ) || ! is_array( $entry['changes'] ) ) {
			return;
		}

		foreach ( $entry['changes'] as $change ) {
			$this->process_change( $change );
		}
	}

	/**
	 * Process a change from webhook entry.
	 *
	 * @param array<string, mixed> $change Change data.
	 * @return void
	 */
	private function process_change( array $change ): void {
		if ( empty( $change['value']['statuses'] ) || ! is_array( $change['value']['statuses'] ) ) {
			return;
		}

		foreach ( $change['value']['statuses'] as $status ) {
			$this->process_status_update( $status );
		}
	}

	/**
	 * Process a status update.
	 *
	 * @param array<string, mixed> $status Status data.
	 * @return void
	 */
	private function process_status_update( array $status ): void {
		if ( empty( $status['id'] ) || empty( $status['status'] ) ) {
			return;
		}

		$wa_message_id = (string) $status['id'];
		$new_status    = $this->map_whatsapp_status( (string) $status['status'] );

		$this->log_repo->update_by_message_id(
			$wa_message_id,
			array( 'status' => $new_status )
		);
	}

	/**
	 * Map WhatsApp status to internal status.
	 *
	 * @param string $wa_status WhatsApp status.
	 * @return string Internal status.
	 */
	private function map_whatsapp_status( string $wa_status ): string {
		$status_map = array(
			'sent'      => 'sent',
			'delivered' => 'delivered',
			'read'      => 'read',
			'failed'    => 'failed',
		);

		return $status_map[ $wa_status ] ?? 'sent';
	}
}
