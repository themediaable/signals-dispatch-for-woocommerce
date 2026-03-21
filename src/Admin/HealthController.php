<?php
/**
 * Health check page controller.
 *
 * @package TMASD\Signals\Dispatch\Admin
 */

declare(strict_types=1);

namespace TMASD\Signals\Dispatch\Admin;

use TMASD\Signals\Dispatch\Database\LogRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Health check page controller.
 *
 * Displays system health and message statistics.
 * Single Responsibility: Health page rendering only.
 *
 * @final
 */
final class HealthController extends AbstractAdminController {

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	protected string $page_slug = 'tmasd-health';

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
	 * Render the health check page.
	 *
	 * @return void
	 */
	public function render(): void {
		$this->assert_access();

		$this->render_page_header();
		$this->render_status_cards();
		$this->render_statistics();

		echo '</div>';
	}

	/**
	 * Render page header.
	 *
	 * @return void
	 */
	private function render_page_header(): void {
		echo '<div class="wrap tmasd-admin">';
		echo '<h1 class="wp-heading-inline">';
		echo esc_html__( 'Health Check', 'signals-dispatch-for-woocommerce' );
		echo '</h1>';
		echo '<hr class="wp-header-end" />';
	}

	/**
	 * Render status cards.
	 *
	 * @return void
	 */
	private function render_status_cards(): void {
		echo '<div class="tmasd-cards">';
		$this->render_configuration_card();
		$this->render_webhook_card();
		$this->render_scheduler_card();
		echo '</div>';
	}

	/**
	 * Render configuration status card.
	 *
	 * @return void
	 */
	private function render_configuration_card(): void {
		$configured = $this->is_configured();

		$class = $configured ? 'tmasd-card tmasd-card--ok' : 'tmasd-card tmasd-card--error';
		echo '<div class="' . esc_attr( $class ) . '">';
		echo '<h2>' . esc_html__( 'Configuration', 'signals-dispatch-for-woocommerce' ) . '</h2>';
		echo '<p>';
		echo $configured
			? esc_html__( 'Configured', 'signals-dispatch-for-woocommerce' )
			: esc_html__( 'Missing credentials', 'signals-dispatch-for-woocommerce' );
		echo '</p>';
		echo '</div>';
	}

	/**
	 * Check if plugin is configured.
	 *
	 * @return bool True if configured.
	 */
	private function is_configured(): bool {
		$phone_id     = get_option( \TMASD_OPTION_PHONE_NUMBER_ID, '' );
		$waba_id      = get_option( \TMASD_OPTION_WABA_ID, '' );
		$access_token = get_option( \TMASD_OPTION_ACCESS_TOKEN, '' );

		return ! empty( $phone_id ) && ! empty( $waba_id ) && ! empty( $access_token );
	}

	/**
	 * Render webhook status card.
	 *
	 * @return void
	 */
	private function render_webhook_card(): void {
		$has_token = ! empty( get_option( \TMASD_OPTION_WEBHOOK_VERIFY_TOKEN, '' ) );

		$class = $has_token ? 'tmasd-card tmasd-card--ok' : 'tmasd-card tmasd-card--error';
		echo '<div class="' . esc_attr( $class ) . '">';
		echo '<h2>' . esc_html__( 'Webhook Token', 'signals-dispatch-for-woocommerce' ) . '</h2>';
		echo '<p>';
		echo $has_token
			? esc_html__( 'Set', 'signals-dispatch-for-woocommerce' )
			: esc_html__( 'Missing', 'signals-dispatch-for-woocommerce' );
		echo '</p>';
		echo '</div>';
	}

	/**
	 * Render action scheduler status card.
	 *
	 * @return void
	 */
	private function render_scheduler_card(): void {
		$available = $this->is_scheduler_available();

		$class = $available ? 'tmasd-card tmasd-card--ok' : 'tmasd-card tmasd-card--error';
		echo '<div class="' . esc_attr( $class ) . '">';
		echo '<h2>' . esc_html__( 'Action Scheduler', 'signals-dispatch-for-woocommerce' ) . '</h2>';
		echo '<p>';
		echo $available
			? esc_html__( 'Available', 'signals-dispatch-for-woocommerce' )
			: esc_html__( 'Unavailable', 'signals-dispatch-for-woocommerce' );
		echo '</p>';
		echo '</div>';
	}

	/**
	 * Check if Action Scheduler is available.
	 *
	 * @return bool True if available.
	 */
	private function is_scheduler_available(): bool {
		return function_exists( 'as_enqueue_async_action' )
			|| function_exists( 'as_schedule_single_action' );
	}

	/**
	 * Render message statistics.
	 *
	 * @return void
	 */
	private function render_statistics(): void {
		$counts = $this->log_repo->get_status_counts_last_24h();

		echo '<h2>' . esc_html__( 'Last 24 Hours', 'signals-dispatch-for-woocommerce' ) . '</h2>';
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
