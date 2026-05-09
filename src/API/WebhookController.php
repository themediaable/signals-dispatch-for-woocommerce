<?php
/**
 * Webhook controller for WhatsApp callbacks.
 *
 * @package TMASD\Signals\Dispatch\API
 * @since 1.0.0
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
 * @since 1.0.0
 */
final class WebhookController extends AbstractService {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private const API_NAMESPACE = 'tmasignals/v1';

	/**
	 * REST API route.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private const API_ROUTE = '/webhook';

	/**
	 * Log repository.
	 *
	 * @var LogRepository
	 * @since 1.0.0
	 */
	private LogRepository $log_repo;

	/**
	 * Constructor.
	 *
	 * @param LogRepository $log_repo Log repository.
	 * @since 1.0.0
	 */
	public function __construct( LogRepository $log_repo ) {
		$this->log_repo = $log_repo;
	}

	/**
	 * Boot the service and register routes.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function boot(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 * @since 1.0.0
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
	 * Meta expects the raw hub.challenge value as plain text, not JSON.
	 * Using WP_REST_Response would JSON-encode the string (e.g. "12345"),
	 * which Meta rejects. We output the challenge directly and exit.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response Response object (only on failure).
	 * @since 1.0.0
	 */
	public function handle_verify( WP_REST_Request $request ): WP_REST_Response {
		$mode      = $this->get_hub_param( $request, 'mode' );
		$token     = $this->get_hub_param( $request, 'verify_token' );
		$challenge = $this->get_hub_param( $request, 'challenge' );

		if ( ! $this->verify_token( $mode, $token ) ) {
			return new WP_REST_Response( 'Forbidden', 403 );
		}

		// Return raw plain-text challenge for Meta verification.
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo esc_html( (string) $challenge );
		exit;
	}

	/**
	 * Get a hub.* parameter from the request.
	 *
	 * Meta sends query params as hub.mode, hub.verify_token, etc.
	 * PHP converts dots to underscores, so both forms are checked.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @param string          $name    Parameter name without hub prefix.
	 * @return string|null Parameter value or null.
	 * @since 1.0.0
	 */
	private function get_hub_param( WP_REST_Request $request, string $name ): ?string {
		$value = $request->get_param( 'hub_' . $name );

		if ( null === $value ) {
			$value = $request->get_param( 'hub.' . $name );
		}

		return $value;
	}

	/**
	 * Verify the webhook token.
	 *
	 * @param string|null $mode  Hub mode.
	 * @param string|null $token Token to verify.
	 * @return bool True if valid.
	 * @since 1.0.0
	 */
	private function verify_token( ?string $mode, ?string $token ): bool {
		if ( 'subscribe' !== $mode ) {
			return false;
		}

		$stored_token = $this->get_option( \TMASD_OPTION_WEBHOOK_VERIFY_TOKEN );

		return '' !== $stored_token && hash_equals( $stored_token, (string) $token );
	}

	/**
	 * Handle incoming webhook (POST request).
	 *
	 * Webhook delivery status tracking (sent, delivered, read, failed) is
	 * a free feature — no plan or tier gating is applied here.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response Response object.
	 * @since 1.0.0
	 */
	public function handle_webhook( WP_REST_Request $request ): WP_REST_Response {
		if ( ! $this->verify_signature( $request ) ) {
			return new WP_REST_Response( 'Unauthorized', 401 );
		}

		$body = $request->get_json_params();

		if ( ! $this->is_valid_webhook_body( $body ) ) {
			return new WP_REST_Response( 'OK', 200 );
		}

		// Record that we received a valid webhook.
		update_option( \TMASD_OPTION_LAST_WEBHOOK_RECEIVED_AT, current_time( 'mysql' ), false );

		$this->process_webhook_entries( $body );

		return new WP_REST_Response( 'OK', 200 );
	}

	/**
	 * Verify the X-Hub-Signature-256 header from Meta.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return bool True if signature is valid or app secret is not configured.
	 * @since 1.0.0
	 */
	private function verify_signature( WP_REST_Request $request ): bool {
		$app_secret = $this->get_option( \TMASD_OPTION_APP_SECRET );

		// Fail closed: reject all POST requests when app secret is not configured.
		if ( '' === $app_secret ) {
			return false;
		}

		$signature_header = $request->get_header( 'X-Hub-Signature-256' );

		if ( empty( $signature_header ) ) {
			return false;
		}

		$raw_body      = $request->get_body();
		$expected_hash = hash_hmac( 'sha256', $raw_body, $app_secret );
		$expected_sig  = 'sha256=' . $expected_hash;

		$match = hash_equals( $expected_sig, $signature_header );

		return $match;
	}

	/**
	 * Validate webhook body structure.
	 *
	 * @param array<string, mixed>|null $body Webhook body.
	 * @return bool True if valid.
	 * @since 1.0.0
	 */
	private function is_valid_webhook_body( ?array $body ): bool {
		if ( empty( $body['entry'] ) || ! is_array( $body['entry'] ) ) {
			return false;
		}

		// Reject payloads that are not from the WhatsApp Business Account object type.
		if ( isset( $body['object'] ) && 'whatsapp_business_account' !== $body['object'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Process webhook entries.
	 *
	 * @param array<string, mixed> $body Webhook body.
	 * @return void
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * @since 1.0.0
	 */
	private function process_status_update( array $status ): void {
		if ( empty( $status['id'] ) || empty( $status['status'] ) ) {
			return;
		}

		$wa_message_id = (string) $status['id'];
		$new_status    = $this->map_whatsapp_status( (string) $status['status'] );

		if ( '' === $new_status ) {
			return;
		}

		$updated = $this->log_repo->update_by_message_id(
			$wa_message_id,
			array( 'status' => $new_status )
		);

		if ( $updated ) {
			update_option( \TMASD_OPTION_LAST_WEBHOOK_STATUS_UPDATE_AT, current_time( 'mysql' ), false );
		}
	}

	/**
	 * Map WhatsApp status to internal status.
	 *
	 * @param string $wa_status WhatsApp status.
	 * @return string Internal status.
	 * @since 1.0.0
	 */
	private function map_whatsapp_status( string $wa_status ): string {
		$status_map = array(
			'sent'      => 'sent',
			'delivered' => 'delivered',
			'read'      => 'read',
			'failed'    => 'failed',
		);

		return $status_map[ $wa_status ] ?? '';
	}
}
