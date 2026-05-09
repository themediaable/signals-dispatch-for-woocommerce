<?php
/**
 * Health check page controller.
 *
 * @package TMASD\Signals\Dispatch\Admin
 * @since 1.1.0
 */

declare(strict_types=1);

namespace TMASD\Signals\Dispatch\Admin;

use TMASD\Signals\Dispatch\Database\LogRepository;
use TMASD\Signals\Dispatch\Database\MappingRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Health check page controller.
 *
 * Displays actionable pass/warning/fail statuses for every
 * plugin dependency and configuration item.
 *
 * @final
 * @since 1.1.0
 */
final class HealthController extends AbstractAdminController {

	/**
	 * Page slug.
	 *
	 * @var string
	 * @since 1.1.0
	 */
	protected string $page_slug = 'tmasd-health';

	/**
	 * Log repository.
	 *
	 * @var LogRepository
	 * @since 1.1.0
	 */
	private LogRepository $log_repo;

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
	 * @param LogRepository     $log_repo     Log repository.
	 * @param MappingRepository $mapping_repo Mapping repository.
	 * @since 1.1.0
	 */
	public function __construct( LogRepository $log_repo, MappingRepository $mapping_repo ) {
		$this->log_repo     = $log_repo;
		$this->mapping_repo = $mapping_repo;
	}

	/**
	 * Render the health check page.
	 *
	 * @return void
	 * @since 1.1.0
	 */
	public function render(): void {
		$this->assert_access();

		$this->render_page_header();
		$this->render_checks();
		$this->render_statistics();

		echo '</div>';
	}

	/**
	 * Render page header.
	 *
	 * @return void
	 * @since 1.1.0
	 */
	private function render_page_header(): void {
		echo '<div class="wrap tmasd-admin">';
		echo '<h1 class="wp-heading-inline">';
		echo esc_html__( 'Health Check', 'signals-dispatch-for-woocommerce' );
		echo '</h1>';
		echo '<hr class="wp-header-end" />';
	}

	/**
	 * Build all health check items.
	 *
	 * Each item:
	 *   label   – human-readable check name
	 *   status  – pass | warning | fail | not_checked
	 *   detail  – what the status means
	 *   fix     – how to resolve a non-pass status
	 *   fix_url – admin URL of the relevant setup step (optional)
	 *
	 * @return array<int, array<string, string>>
	 * @since 1.1.0
	 */
	private function get_checks(): array {
		$setup_url       = admin_url( 'admin.php?page=tmasd-setup' );
		$credentials_url = admin_url( 'admin.php?page=tmasd-setup&tab=credentials' );
		$webhook_url     = admin_url( 'admin.php?page=tmasd-setup&tab=webhook' );
		$dispatch_url    = admin_url( 'admin.php?page=tmasd-dispatch' );

		$checks = array();

		// WooCommerce active.
		$woo_active = class_exists( 'WooCommerce' );
		$checks[]   = array(
			'label'   => __( 'WooCommerce active', 'signals-dispatch-for-woocommerce' ),
			'status'  => $woo_active ? 'pass' : 'fail',
			'detail'  => $woo_active
				? __( 'WooCommerce is installed and active.', 'signals-dispatch-for-woocommerce' )
				: __( 'WooCommerce is not active. Signals Dispatch requires WooCommerce.', 'signals-dispatch-for-woocommerce' ),
			'fix'     => $woo_active ? '' : __( 'Install and activate WooCommerce.', 'signals-dispatch-for-woocommerce' ),
			'fix_url' => $woo_active ? '' : admin_url( 'plugins.php' ),
		);

		// Action Scheduler.
		$has_scheduler = function_exists( 'as_enqueue_async_action' ) || function_exists( 'as_schedule_single_action' );
		$checks[]      = array(
			'label'   => __( 'Action Scheduler available', 'signals-dispatch-for-woocommerce' ),
			'status'  => $has_scheduler ? 'pass' : 'fail',
			'detail'  => $has_scheduler
				? __( 'Action Scheduler is available for background job processing.', 'signals-dispatch-for-woocommerce' )
				: __( 'Action Scheduler is not available. It is bundled with WooCommerce and should be present if WooCommerce is active.', 'signals-dispatch-for-woocommerce' ),
			'fix'     => $has_scheduler ? '' : __( 'Ensure WooCommerce is fully activated.', 'signals-dispatch-for-woocommerce' ),
			'fix_url' => $has_scheduler ? '' : admin_url( 'plugins.php' ),
		);

		// Phone Number ID.
		$phone_id  = get_option( \TMASD_OPTION_PHONE_NUMBER_ID, '' );
		$has_phone = '' !== $phone_id;
		$checks[]  = array(
			'label'   => __( 'Phone Number ID configured', 'signals-dispatch-for-woocommerce' ),
			'status'  => $has_phone ? 'pass' : 'fail',
			'detail'  => $has_phone
				? __( 'Phone Number ID is saved.', 'signals-dispatch-for-woocommerce' )
				: __( 'Phone Number ID is missing. Messages cannot be sent without it.', 'signals-dispatch-for-woocommerce' ),
			'fix'     => $has_phone ? '' : __( 'Add your Phone Number ID in Step 4: API Credentials.', 'signals-dispatch-for-woocommerce' ),
			'fix_url' => $has_phone ? '' : $credentials_url,
		);

		// WABA ID.
		$waba_id  = get_option( \TMASD_OPTION_WABA_ID, '' );
		$has_waba = '' !== $waba_id;
		$checks[] = array(
			'label'   => __( 'WABA ID configured', 'signals-dispatch-for-woocommerce' ),
			'status'  => $has_waba ? 'pass' : 'fail',
			'detail'  => $has_waba
				? __( 'WhatsApp Business Account ID is saved.', 'signals-dispatch-for-woocommerce' )
				: __( 'WABA ID is missing.', 'signals-dispatch-for-woocommerce' ),
			'fix'     => $has_waba ? '' : __( 'Add your WABA ID in Step 4: API Credentials.', 'signals-dispatch-for-woocommerce' ),
			'fix_url' => $has_waba ? '' : $credentials_url,
		);

		// Access token.
		$has_token = '' !== get_option( \TMASD_OPTION_ACCESS_TOKEN, '' );
		$checks[]  = array(
			'label'   => __( 'Access token saved', 'signals-dispatch-for-woocommerce' ),
			'status'  => $has_token ? 'pass' : 'fail',
			'detail'  => $has_token
				? __( 'Access token is saved.', 'signals-dispatch-for-woocommerce' )
				: __( 'Access token is missing. All API calls will fail.', 'signals-dispatch-for-woocommerce' ),
			'fix'     => $has_token ? '' : __( 'Add your access token in Step 4: API Credentials.', 'signals-dispatch-for-woocommerce' ),
			'fix_url' => $has_token ? '' : $credentials_url,
		);

		// App secret.
		$has_secret = '' !== get_option( \TMASD_OPTION_APP_SECRET, '' );
		$checks[]   = array(
			'label'   => __( 'App secret saved', 'signals-dispatch-for-woocommerce' ),
			'status'  => $has_secret ? 'pass' : 'warning',
			'detail'  => $has_secret
				? __( 'App secret is saved. Webhook POST signatures will be verified.', 'signals-dispatch-for-woocommerce' )
				: __( 'App secret is missing. Incoming webhook POSTs cannot be verified and will be rejected.', 'signals-dispatch-for-woocommerce' ),
			'fix'     => $has_secret ? '' : __( 'Add your app secret in Step 4: API Credentials.', 'signals-dispatch-for-woocommerce' ),
			'fix_url' => $has_secret ? '' : $credentials_url,
		);

		// API connection.
		$test_status = get_option( \TMASD_OPTION_LAST_API_TEST_STATUS, '' );
		$test_at     = get_option( \TMASD_OPTION_LAST_API_TEST_AT, '' );
		if ( '' === $test_status ) {
			$api_status = 'not_checked';
			$api_detail = __( 'API connection has not been tested yet.', 'signals-dispatch-for-woocommerce' );
			$api_fix    = __( 'Run "Test API connection" in Step 4: API Credentials.', 'signals-dispatch-for-woocommerce' );
		} elseif ( 'pass' === $test_status ) {
			$api_status = 'pass';
			$api_detail = sprintf(
				/* translators: %s: datetime of last test */
				__( 'API connection passed (last tested: %s).', 'signals-dispatch-for-woocommerce' ),
				$test_at
			);
			$api_fix = '';
		} else {
			$api_status = 'fail';
			$api_detail = sprintf(
				/* translators: %s: datetime of last test */
				__( 'API connection failed (last tested: %s). Meta rejected the saved credentials.', 'signals-dispatch-for-woocommerce' ),
				$test_at
			);
			$api_fix = __( 'Check your credentials and run "Test API connection" in Step 4: API Credentials.', 'signals-dispatch-for-woocommerce' );
		}

		$checks[] = array(
			'label'   => __( 'API connection valid', 'signals-dispatch-for-woocommerce' ),
			'status'  => $api_status,
			'detail'  => $api_detail,
			'fix'     => $api_fix,
			'fix_url' => 'pass' !== $api_status ? $credentials_url : '',
		);

		// Webhook verify token.
		$has_verify = '' !== get_option( \TMASD_OPTION_WEBHOOK_VERIFY_TOKEN, '' );
		$checks[]   = array(
			'label'   => __( 'Webhook verify token saved', 'signals-dispatch-for-woocommerce' ),
			'status'  => $has_verify ? 'pass' : 'fail',
			'detail'  => $has_verify
				? __( 'Webhook verify token is saved.', 'signals-dispatch-for-woocommerce' )
				: __( 'Webhook verify token is missing. Meta cannot verify the webhook endpoint.', 'signals-dispatch-for-woocommerce' ),
			'fix'     => $has_verify ? '' : __( 'Add your verify token in Step 4: API Credentials.', 'signals-dispatch-for-woocommerce' ),
			'fix_url' => $has_verify ? '' : $credentials_url,
		);

		// Last webhook received.
		$last_webhook = get_option( \TMASD_OPTION_LAST_WEBHOOK_RECEIVED_AT, '' );
		$checks[]     = array(
			'label'   => __( 'Webhook received', 'signals-dispatch-for-woocommerce' ),
			'status'  => '' !== $last_webhook ? 'pass' : 'not_checked',
			'detail'  => '' !== $last_webhook
				/* translators: %s: datetime string */
				? sprintf( __( 'Last webhook received: %s', 'signals-dispatch-for-woocommerce' ), $last_webhook )
				: __( 'No webhook received yet. Configure the webhook in Meta and test it.', 'signals-dispatch-for-woocommerce' ),
			'fix'     => '' !== $last_webhook ? '' : __( 'Complete Step 5: Webhook and use Meta Developer Console to verify.', 'signals-dispatch-for-woocommerce' ),
			'fix_url' => '' !== $last_webhook ? '' : $webhook_url,
		);

		// Dispatch rules.
		$enabled_rule = false;
		foreach ( array_keys( $this->mapping_repo->get_available_events() ) as $event_key ) {
			if ( null !== $this->mapping_repo->find_by_event( $event_key ) ) {
				$enabled_rule = true;
				break;
			}
		}
		$checks[] = array(
			'label'   => __( 'At least one dispatch rule enabled', 'signals-dispatch-for-woocommerce' ),
			'status'  => $enabled_rule ? 'pass' : 'warning',
			'detail'  => $enabled_rule
				? __( 'At least one dispatch rule is enabled. Order notifications will be sent.', 'signals-dispatch-for-woocommerce' )
				: __( 'No enabled dispatch rules. Messages will not be sent for any order event.', 'signals-dispatch-for-woocommerce' ),
			'fix'     => $enabled_rule ? '' : __( 'Create and enable a dispatch rule.', 'signals-dispatch-for-woocommerce' ),
			'fix_url' => $enabled_rule ? '' : $dispatch_url,
		);

		// Consent setting.
		$consent_required = (bool) get_option( \TMASD_OPTION_REQUIRE_CONSENT, false );
		$checks[]         = array(
			'label'   => __( 'Consent enforcement', 'signals-dispatch-for-woocommerce' ),
			'status'  => 'pass',
			'detail'  => $consent_required
				? __( 'Require consent is ON. Messages are only sent to opted-in customers.', 'signals-dispatch-for-woocommerce' )
				: __( 'Require consent is OFF. Messages are sent regardless of opt-in. Consider enabling for GDPR compliance.', 'signals-dispatch-for-woocommerce' ),
			'fix'     => '',
			'fix_url' => $credentials_url,
		);

		// Recent failed sends.
		$counts       = $this->log_repo->get_status_counts_last_24h();
		$failed_count = (int) ( $counts['failed'] ?? 0 );
		$checks[]     = array(
			'label'   => __( 'Recent failed sends (last 24 h)', 'signals-dispatch-for-woocommerce' ),
			'status'  => 0 === $failed_count ? 'pass' : 'warning',
			'detail'  => 0 === $failed_count
				? __( 'No failed sends in the last 24 hours.', 'signals-dispatch-for-woocommerce' )
				: sprintf(
					/* translators: %d: number of failed sends */
					__( '%d sends failed in the last 24 hours. Check the Logs page for details.', 'signals-dispatch-for-woocommerce' ),
					$failed_count
				),
			'fix'     => 0 === $failed_count ? '' : __( 'Review logs for error codes and check API credentials.', 'signals-dispatch-for-woocommerce' ),
			'fix_url' => 0 === $failed_count ? '' : admin_url( 'admin.php?page=tmasd-logs' ),
		);

		return $checks;
	}

	/**
	 * Render all health checks as a table.
	 *
	 * @return void
	 * @since 1.1.0
	 */
	private function render_checks(): void {
		$checks = $this->get_checks();

		$status_labels = array(
			'pass'        => __( 'Pass', 'signals-dispatch-for-woocommerce' ),
			'warning'     => __( 'Warning', 'signals-dispatch-for-woocommerce' ),
			'fail'        => __( 'Fail', 'signals-dispatch-for-woocommerce' ),
			'not_checked' => __( 'Not checked', 'signals-dispatch-for-woocommerce' ),
		);

		$status_classes = array(
			'pass'        => 'tmasd-status-ok',
			'warning'     => 'tmasd-status-warning',
			'fail'        => 'tmasd-status-error',
			'not_checked' => 'tmasd-status-neutral',
		);

		echo '<table class="widefat striped tmasd-health-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Check', 'signals-dispatch-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'signals-dispatch-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Detail', 'signals-dispatch-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'How to fix', 'signals-dispatch-for-woocommerce' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		foreach ( $checks as $check ) {
			$status = $check['status'] ?? 'not_checked';
			$class  = $status_classes[ $status ] ?? 'tmasd-status-neutral';
			$label  = $status_labels[ $status ] ?? $status;

			echo '<tr>';
			echo '<td>' . esc_html( $check['label'] ) . '</td>';
			echo '<td><span class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span></td>';
			echo '<td>' . esc_html( $check['detail'] ) . '</td>';
			echo '<td>';
			if ( '' !== $check['fix'] ) {
				if ( '' !== $check['fix_url'] ) {
					echo '<a href="' . esc_url( $check['fix_url'] ) . '">' . esc_html( $check['fix'] ) . '</a>';
				} else {
					echo esc_html( $check['fix'] );
				}
			} else {
				echo '&mdash;';
			}
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Render message statistics.
	 *
	 * @return void
	 * @since 1.1.0
	 */
	private function render_statistics(): void {
		$counts = $this->log_repo->get_status_counts_last_24h();

		echo '<h2 style="margin-top:2em">' . esc_html__( 'Last 24 Hours', 'signals-dispatch-for-woocommerce' ) . '</h2>';
		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Status', 'signals-dispatch-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Count', 'signals-dispatch-for-woocommerce' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		if ( empty( $counts ) ) {
			echo '<tr><td colspan="2">';
			echo esc_html__( 'No log entries.', 'signals-dispatch-for-woocommerce' );
			echo '</td></tr>';
		} else {
			foreach ( $counts as $status => $count ) {
				echo '<tr>';
				echo '<td>' . esc_html( $status ) . '</td>';
				echo '<td>' . esc_html( (string) $count ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
	}
}
