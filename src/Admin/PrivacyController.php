<?php
/**
 * WordPress privacy integration (exporter and eraser).
 *
 * @package TMASD\Signals\Dispatch\Admin
 */

declare(strict_types=1);

namespace TMASD\Signals\Dispatch\Admin;

use TMASD\Signals\Dispatch\Core\AbstractService;
use TMASD\Signals\Dispatch\Database\LogRepository;
use TMASD\Signals\Dispatch\Database\OptinRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integrates with WordPress personal data export and erasure tools.
 *
 * Registers a data exporter and eraser so that site admins can
 * fulfil GDPR / privacy requests for phone numbers stored by the
 * plugin (message logs and opt-in consent records).
 *
 * @final
 */
final class PrivacyController extends AbstractService {

	/**
	 * Log repository.
	 *
	 * @var LogRepository
	 */
	private LogRepository $log_repo;

	/**
	 * Opt-in repository.
	 *
	 * @var OptinRepository
	 */
	private OptinRepository $optin_repo;

	/**
	 * Constructor.
	 *
	 * @param LogRepository  $log_repo   Log repository.
	 * @param OptinRepository $optin_repo Opt-in repository.
	 */
	public function __construct( LogRepository $log_repo, OptinRepository $optin_repo ) {
		$this->log_repo   = $log_repo;
		$this->optin_repo = $optin_repo;
	}

	/**
	 * Boot the service and register privacy hooks.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
	}

	/**
	 * Register personal data exporter.
	 *
	 * @param array<int, array<string, mixed>> $exporters Registered exporters.
	 * @return array<int, array<string, mixed>> Exporters with ours added.
	 */
	public function register_exporter( array $exporters ): array {
		$exporters[] = array(
			'exporter_friendly_name' => __( 'Signals Dispatch — Message Logs & Consent', 'signals-dispatch-woocommerce' ),
			'callback'               => array( $this, 'export_personal_data' ),
		);

		return $exporters;
	}

	/**
	 * Register personal data eraser.
	 *
	 * @param array<int, array<string, mixed>> $erasers Registered erasers.
	 * @return array<int, array<string, mixed>> Erasers with ours added.
	 */
	public function register_eraser( array $erasers ): array {
		$erasers[] = array(
			'eraser_friendly_name' => __( 'Signals Dispatch — Message Logs & Consent', 'signals-dispatch-woocommerce' ),
			'callback'             => array( $this, 'erase_personal_data' ),
		);

		return $erasers;
	}

	/**
	 * Export personal data for a given email address.
	 *
	 * WordPress calls this with the email address from the erasure request.
	 * We look up any WP user with that email to find their phone numbers,
	 * then export matching log and consent records.
	 *
	 * @param string $email_address Requester email address.
	 * @param int    $page          Page number (for pagination, 1-indexed).
	 * @return array{data: array<int, array<string, mixed>>, done: bool} Export result.
	 */
	public function export_personal_data( string $email_address, int $page = 1 ): array {
		$data = array();

		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array( 'data' => $data, 'done' => true );
		}

		// Export message log rows linked to this user's orders.
		$log_rows = $this->log_repo->find_by_user_id( $user->ID );
		foreach ( $log_rows as $row ) {
			$data[] = array(
				'group_id'          => 'tmasd_message_logs',
				'group_label'       => __( 'WhatsApp Message Logs', 'signals-dispatch-woocommerce' ),
				'item_id'           => 'tmasd-log-' . (int) $row['id'],
				'data'              => array(
					array(
						'name'  => __( 'Phone Number', 'signals-dispatch-woocommerce' ),
						'value' => $this->mask_phone( (string) $row['phone_e164'] ),
					),
					array(
						'name'  => __( 'Order ID', 'signals-dispatch-woocommerce' ),
						'value' => (int) $row['order_id'],
					),
					array(
						'name'  => __( 'Template', 'signals-dispatch-woocommerce' ),
						'value' => (string) $row['template_name'],
					),
					array(
						'name'  => __( 'Status', 'signals-dispatch-woocommerce' ),
						'value' => (string) $row['status'],
					),
					array(
						'name'  => __( 'Date', 'signals-dispatch-woocommerce' ),
						'value' => (string) $row['created_at'],
					),
				),
			);
		}

		// Export consent records linked to this user.
		$consent_rows = $this->optin_repo->find_by_user_id( $user->ID );
		foreach ( $consent_rows as $row ) {
			$data[] = array(
				'group_id'    => 'tmasd_consent',
				'group_label' => __( 'WhatsApp Consent Records', 'signals-dispatch-woocommerce' ),
				'item_id'     => 'tmasd-consent-' . (int) $row['id'],
				'data'        => array(
					array(
						'name'  => __( 'Phone Number', 'signals-dispatch-woocommerce' ),
						'value' => $this->mask_phone( (string) $row['phone_e164'] ),
					),
					array(
						'name'  => __( 'Consent', 'signals-dispatch-woocommerce' ),
						'value' => ! empty( $row['consent'] ) ? __( 'Yes', 'signals-dispatch-woocommerce' ) : __( 'No', 'signals-dispatch-woocommerce' ),
					),
					array(
						'name'  => __( 'Source', 'signals-dispatch-woocommerce' ),
						'value' => (string) $row['consent_source'],
					),
					array(
						'name'  => __( 'Date', 'signals-dispatch-woocommerce' ),
						'value' => (string) $row['consent_at'],
					),
				),
			);
		}

		return array( 'data' => $data, 'done' => true );
	}

	/**
	 * Erase personal data for a given email address.
	 *
	 * Anonymises phone numbers in the logs table and removes consent
	 * records linked to the requesting user.
	 *
	 * @param string $email_address Requester email address.
	 * @param int    $page          Page number (for pagination, 1-indexed).
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool} Result.
	 */
	public function erase_personal_data( string $email_address, int $page = 1 ): array {
		$items_removed  = false;
		$items_retained = false;
		$messages       = array();

		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		// Anonymise phone numbers in message logs (retain records for business purposes).
		$anonymised = $this->log_repo->anonymise_phone_by_user_id( $user->ID );
		if ( $anonymised > 0 ) {
			$items_removed = true;
			$messages[]    = sprintf(
				/* translators: %d: number of log rows anonymised */
				__( 'Anonymised phone number in %d message log record(s).', 'signals-dispatch-woocommerce' ),
				$anonymised
			);
		}

		// Delete consent records for this user.
		$deleted = $this->optin_repo->delete_by_user_id( $user->ID );
		if ( $deleted > 0 ) {
			$items_removed = true;
			$messages[]    = sprintf(
				/* translators: %d: number of consent records deleted */
				__( 'Deleted %d consent record(s).', 'signals-dispatch-woocommerce' ),
				$deleted
			);
		}

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => $messages,
			'done'           => true,
		);
	}

	/**
	 * Add suggested privacy policy content.
	 *
	 * @return void
	 */
	public function add_privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<h2>' . __( 'Signals Dispatch for WooCommerce', 'signals-dispatch-woocommerce' ) . '</h2>'
			. '<p>' . __( 'This plugin stores the following personal data:', 'signals-dispatch-woocommerce' ) . '</p>'
			. '<ul>'
			. '<li>' . __( 'Phone numbers linked to WooCommerce orders, used to delivery WhatsApp message notifications.', 'signals-dispatch-woocommerce' ) . '</li>'
			. '<li>' . __( 'Consent records tracking whether a customer has opted in to WhatsApp messaging.', 'signals-dispatch-woocommerce' ) . '</li>'
			. '</ul>'
			. '<p>' . __( 'This data is stored in the site database and transmitted to the Meta WhatsApp Business Cloud API for message delivery. It is not shared with any other third party.', 'signals-dispatch-woocommerce' ) . '</p>';

		wp_add_privacy_policy_content(
			__( 'Signals Dispatch for WooCommerce', 'signals-dispatch-woocommerce' ),
			wp_kses_post( $content )
		);
	}

	/**
	 * Partially mask a phone number for export output.
	 *
	 * @param string $phone Phone in E.164 format.
	 * @return string Masked phone number.
	 */
	private function mask_phone( string $phone ): string {
		$len = strlen( $phone );
		if ( $len <= 4 ) {
			return str_repeat( '*', $len );
		}

		return substr( $phone, 0, 3 ) . str_repeat( '*', $len - 6 ) . substr( $phone, -3 );
	}
}
