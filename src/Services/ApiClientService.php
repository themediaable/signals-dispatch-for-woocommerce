<?php
/**
 * WhatsApp API client service.
 *
 * @package TMASD\Signals\Dispatch\Services
 */

declare(strict_types=1);

namespace TMASD\Signals\Dispatch\Services;

use TMASD\Signals\Dispatch\Contracts\ApiClientInterface;
use TMASD\Signals\Dispatch\Contracts\PhoneNormalizerInterface;
use TMASD\Signals\Dispatch\Core\AbstractService;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WhatsApp Cloud API client service.
 *
 * Handles communication with the WhatsApp Business Cloud API.
 * Uses encapsulation to hide API implementation details.
 *
 * @final
 */
final class ApiClientService extends AbstractService implements ApiClientInterface {

	/**
	 * API version to use.
	 *
	 * @var string
	 */
	private const API_VERSION = 'v18.0';

	/**
	 * Base URL for the Graph API.
	 *
	 * @var string
	 */
	private const API_BASE_URL = 'https://graph.facebook.com';

	/**
	 * Request timeout in seconds.
	 *
	 * @var int
	 */
	private const REQUEST_TIMEOUT = 20;

	/**
	 * HTTP success status code range start.
	 *
	 * @var int
	 */
	private const HTTP_SUCCESS_MIN = 200;

	/**
	 * HTTP success status code range end.
	 *
	 * @var int
	 */
	private const HTTP_SUCCESS_MAX = 300;

	/**
	 * Phone normalizer service.
	 *
	 * @var PhoneNormalizerInterface
	 */
	private PhoneNormalizerInterface $phone_normalizer;

	/**
	 * Constructor.
	 *
	 * @param PhoneNormalizerInterface $phone_normalizer Phone normalizer service.
	 */
	public function __construct( PhoneNormalizerInterface $phone_normalizer ) {
		$this->phone_normalizer = $phone_normalizer;
	}

	/**
	 * Send a template message via WhatsApp Cloud API.
	 *
	 * @param string             $phone         Phone number to send to.
	 * @param string             $template_name Template name to use.
	 * @param string             $language      Language code for the template.
	 * @param array<int, string> $variables     Template variables.
	 * @return array<string, mixed> Response array with success status.
	 */
	public function send_template_message(
		string $phone,
		string $template_name,
		string $language,
		array $variables
	): array {
		$credentials = $this->get_credentials();

		if ( ! $this->validate_credentials( $credentials ) ) {
			return $this->build_error_response( 'Missing API credentials.' );
		}

		$phone_e164 = $this->phone_normalizer->normalize( $phone );

		if ( '' === $phone_e164 ) {
			return $this->build_error_response( 'Invalid phone number.' );
		}

		return $this->execute_request(
			$credentials,
			$phone_e164,
			$template_name,
			$language,
			$variables
		);
	}

	/**
	 * Get API credentials from options.
	 *
	 * @return array<string, string> Credentials array.
	 */
	private function get_credentials(): array {
		return array(
			'phone_number_id' => $this->get_option( \TMASD_OPTION_PHONE_NUMBER_ID ),
			'access_token'    => $this->get_option( \TMASD_OPTION_ACCESS_TOKEN ),
		);
	}

	/**
	 * Validate that all required credentials are present.
	 *
	 * @param array<string, string> $credentials Credentials array.
	 * @return bool True if valid.
	 */
	private function validate_credentials( array $credentials ): bool {
		return '' !== $credentials['phone_number_id'] && '' !== $credentials['access_token'];
	}

	/**
	 * Execute the API request.
	 *
	 * @param array<string, string> $credentials   API credentials.
	 * @param string                $phone_e164    Normalized phone.
	 * @param string                $template_name Template name.
	 * @param string                $language      Language code.
	 * @param array<int, string>    $variables     Template variables.
	 * @return array<string, mixed> Response array.
	 */
	private function execute_request(
		array $credentials,
		string $phone_e164,
		string $template_name,
		string $language,
		array $variables
	): array {
		$endpoint = $this->build_endpoint( $credentials['phone_number_id'] );
		$payload  = $this->build_payload( $phone_e164, $template_name, $language, $variables );

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => $this->build_headers( $credentials['access_token'] ),
				'body'    => wp_json_encode( $payload ),
				'timeout' => self::REQUEST_TIMEOUT,
			)
		);

		return $this->process_response( $response, $payload );
	}

	/**
	 * Build API endpoint URL.
	 *
	 * @param string $phone_number_id WhatsApp phone number ID.
	 * @return string Full endpoint URL.
	 */
	private function build_endpoint( string $phone_number_id ): string {
		return sprintf(
			'%s/%s/%s/messages',
			self::API_BASE_URL,
			self::API_VERSION,
			rawurlencode( $phone_number_id )
		);
	}

	/**
	 * Build request headers.
	 *
	 * @param string $access_token Access token.
	 * @return array<string, string> Headers array.
	 */
	private function build_headers( string $access_token ): array {
		return array(
			'Authorization' => 'Bearer ' . $access_token,
			'Content-Type'  => 'application/json',
		);
	}

	/**
	 * Build the API payload.
	 *
	 * @param string             $phone_e164    Phone in E.164 format.
	 * @param string             $template_name Template name.
	 * @param string             $language      Language code.
	 * @param array<int, string> $variables     Template variables.
	 * @return array<string, mixed> API payload.
	 */
	private function build_payload(
		string $phone_e164,
		string $template_name,
		string $language,
		array $variables
	): array {
		return array(
			'messaging_product' => 'whatsapp',
			'to'                => $phone_e164,
			'type'              => 'template',
			'template'          => array(
				'name'       => $template_name,
				'language'   => array( 'code' => $language ),
				'components' => array(
					array(
						'type'       => 'body',
						'parameters' => $this->build_parameters( $variables ),
					),
				),
			),
		);
	}

	/**
	 * Build body parameters from variables.
	 *
	 * @param array<int, string> $variables Variable values.
	 * @return array<int, array<string, string>> Parameters array.
	 */
	private function build_parameters( array $variables ): array {
		$params = array();

		foreach ( $variables as $value ) {
			$params[] = array(
				'type' => 'text',
				'text' => (string) $value,
			);
		}

		return $params;
	}

	/**
	 * Process API response.
	 *
	 * @param array<string, mixed>|WP_Error $response API response.
	 * @param array<string, mixed>          $payload  Request payload.
	 * @return array<string, mixed> Processed response.
	 */
	private function process_response( $response, array $payload ): array {
		if ( is_wp_error( $response ) ) {
			return $this->build_error_response(
				$response->get_error_message(),
				$payload,
				array( 'error' => $response->get_error_message() ),
				0 // http_code 0 signals a network/WP_Error — always retryable.
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$json = $this->decode_response( $body );

		if ( $this->is_success_status( $code ) ) {
			return $this->build_success_response( $payload, $json );
		}

		$error_message = $this->extract_error_message( $json );
		return $this->build_error_response( $error_message, $payload, $json, (int) $code );
	}

	/**
	 * Decode JSON response body.
	 *
	 * @param string $body Response body.
	 * @return array<string, mixed> Decoded JSON.
	 */
	private function decode_response( string $body ): array {
		$json = json_decode( $body, true );
		return is_array( $json ) ? $json : array();
	}

	/**
	 * Check if HTTP status code indicates success.
	 *
	 * @param int $code HTTP status code.
	 * @return bool True if success.
	 */
	private function is_success_status( int $code ): bool {
		return $code >= self::HTTP_SUCCESS_MIN && $code < self::HTTP_SUCCESS_MAX;
	}

	/**
	 * Extract error message from API response.
	 *
	 * @param array<string, mixed> $json API response.
	 * @return string Error message.
	 */
	private function extract_error_message( array $json ): string {
		return $json['error']['message'] ?? 'Request failed';
	}

	/**
	 * Build success response array.
	 *
	 * @param array<string, mixed> $payload  Request payload.
	 * @param array<string, mixed> $response API response.
	 * @return array<string, mixed> Success response.
	 */
	private function build_success_response( array $payload, array $response ): array {
		return array(
			'success'  => true,
			'payload'  => $payload,
			'response' => $response,
		);
	}

	/**
	 * Build error response array.
	 *
	 * @param string               $message   Error message.
	 * @param array<string, mixed> $payload   Request payload.
	 * @param array<string, mixed> $response  API response.
	 * @param int                  $http_code HTTP status code (0 = network error).
	 * @return array<string, mixed> Error response.
	 */
	private function build_error_response(
		string $message,
		array $payload = array(),
		array $response = array(),
		int $http_code = 0
	): array {
		return array(
			'success'   => false,
			'error'     => $message,
			'http_code' => $http_code,
			'payload'   => $payload,
			'response'  => $response,
		);
	}
}
