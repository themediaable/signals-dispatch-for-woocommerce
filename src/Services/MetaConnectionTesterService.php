<?php
/**
 * Meta API connection tester service.
 *
 * @package TMASD\Signals\Dispatch\Services
 * @since 1.1.0
 */

declare(strict_types=1);

namespace TMASD\Signals\Dispatch\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tests the saved WhatsApp Cloud API credentials against Meta's Graph API.
 *
 * Calls GET /{phone_number_id} with the saved bearer token and stores
 * the result in plugin options so the setup checklist and health page
 * can display it without re-testing on every page load.
 *
 * Secrets are never logged or included in any return value.
 *
 * @since 1.1.0
 */
final class MetaConnectionTesterService {

	/**
	 * Graph API base URL.
	 *
	 * @var string
	 * @since 1.1.0
	 */
	private const GRAPH_API_BASE = 'https://graph.facebook.com/v18.0';

	/**
	 * Request timeout in seconds.
	 *
	 * @var int
	 * @since 1.1.0
	 */
	private const TIMEOUT = 15;

	/**
	 * Test the saved credentials against Meta's Graph API.
	 *
	 * Stores the result in plugin options and returns an array with:
	 * - success (bool)
	 * - slug    (string) — machine-readable error slug or empty on success
	 * - message (string) — human-readable result message
	 *
	 * @return array<string, mixed>
	 * @since 1.1.0
	 */
	public function test_connection(): array {
		$phone_id = get_option( \TMASD_OPTION_PHONE_NUMBER_ID, '' );
		$token    = get_option( \TMASD_OPTION_ACCESS_TOKEN, '' );

		if ( '' === $phone_id || '' === $token ) {
			return $this->fail(
				'missing_credentials',
				__( 'Phone Number ID and Access Token are required before testing the connection.', 'signals-dispatch-for-woocommerce' )
			);
		}

		$url      = self::GRAPH_API_BASE . '/' . rawurlencode( $phone_id );
		$response = wp_remote_get(
			$url,
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
				'timeout' => self::TIMEOUT,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->store_result(
				false,
				'network_error',
				__( 'Your site could not reach Meta\'s API. Please check server connectivity.', 'signals-dispatch-for-woocommerce' )
			);
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		$body      = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $http_code ) {
			return $this->store_result( true, '', '' );
		}

		$error_code = isset( $body['error']['code'] ) ? (int) $body['error']['code'] : 0;
		$message    = $this->map_error( $http_code, $error_code );
		$slug       = 'api_error_' . $http_code;

		return $this->store_result( false, $slug, $message );
	}

	/**
	 * Map an HTTP/API error to a user-friendly message.
	 *
	 * @param int $http_code  HTTP response code.
	 * @param int $error_code Meta API error code.
	 * @return string
	 * @since 1.1.0
	 */
	private function map_error( int $http_code, int $error_code ): string {
		// Token expired / invalid.
		if ( 401 === $http_code || 190 === $error_code ) {
			return __( 'Meta rejected this access token. Please check whether the token is expired or copied correctly.', 'signals-dispatch-for-woocommerce' );
		}

		if ( 403 === $http_code ) {
			if ( 10 === $error_code ) {
				return __( 'This token may be missing WhatsApp messaging or business permissions.', 'signals-dispatch-for-woocommerce' );
			}
			return __( 'The token works, but it does not have access to this Phone Number ID.', 'signals-dispatch-for-woocommerce' );
		}

		return sprintf(
			/* translators: %d: HTTP status code */
			__( 'Meta returned an unexpected response (HTTP %d). Please check your credentials and try again.', 'signals-dispatch-for-woocommerce' ),
			$http_code
		);
	}

	/**
	 * Persist the test result to plugin options and return it.
	 *
	 * Options are stored with autoload=false so secrets are not leaked
	 * into the page cache or option set.
	 *
	 * @param bool   $success Whether the test passed.
	 * @param string $slug    Machine-readable error slug.
	 * @param string $message Human-readable result message.
	 * @return array<string, mixed>
	 * @since 1.1.0
	 */
	private function store_result( bool $success, string $slug, string $message ): array {
		update_option( \TMASD_OPTION_LAST_API_TEST_AT, current_time( 'mysql' ), false );
		update_option( \TMASD_OPTION_LAST_API_TEST_STATUS, $success ? 'pass' : 'fail', false );
		update_option( \TMASD_OPTION_LAST_API_TEST_ERROR, $slug, false );

		return array(
			'success' => $success,
			'slug'    => $slug,
			'message' => $message,
		);
	}

	/**
	 * Return a failure result without storing (used for pre-flight checks).
	 *
	 * @param string $slug    Machine-readable error slug.
	 * @param string $message Human-readable message.
	 * @return array<string, mixed>
	 * @since 1.1.0
	 */
	private function fail( string $slug, string $message ): array {
		return array(
			'success' => false,
			'slug'    => $slug,
			'message' => $message,
		);
	}
}
