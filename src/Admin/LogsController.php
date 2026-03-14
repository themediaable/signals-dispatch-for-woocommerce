<?php
/**
 * Logs page controller.
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
 * Logs page controller.
 *
 * Displays message logs with search and pagination.
 * Single Responsibility: Logs page rendering only.
 *
 * @final
 */
final class LogsController extends AbstractAdminController {

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	protected string $page_slug = 'tmasd-logs';

	/**
	 * Items per page.
	 *
	 * @var int
	 */
	private const PER_PAGE = 20;

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
	 * Render the logs page.
	 *
	 * @return void
	 */
	public function render(): void {
		$this->assert_access();

		$status = $this->get_query_param( 'status' );
		$search = $this->get_query_param( 's' );
		$paged  = max( 1, (int) $this->get_query_param( 'paged', '1' ) );

		$result = $this->log_repo->list_paginated(
			array(
				'status'   => $status,
				'search'   => $search,
				'paged'    => $paged,
				'per_page' => self::PER_PAGE,
			)
		);

		$this->render_page_header();
		$this->render_search_form( $search );
		$this->render_logs_table( $result['rows'] );
		$this->render_pagination( $result['total'], $paged, $status, $search );

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
		echo esc_html__( 'Logs', 'signals-dispatch-woocommerce' );
		echo '</h1>';
		echo '<hr class="wp-header-end" />';
	}

	/**
	 * Render search form.
	 *
	 * @param string $search Current search term.
	 * @return void
	 */
	private function render_search_form( string $search ): void {
		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="tmasd-logs" />';
		echo '<p class="search-box">';
		echo '<label class="screen-reader-text" for="tmasd-search">';
		echo esc_html__( 'Search Logs', 'signals-dispatch-woocommerce' );
		echo '</label>';
		echo '<input type="search" id="tmasd-search" name="s" value="' . esc_attr( $search ) . '" />';
		echo '<input type="submit" class="button" value="' . esc_attr__( 'Search', 'signals-dispatch-woocommerce' ) . '" />';
		echo '</p>';
		echo '</form>';
	}

	/**
	 * Render logs table.
	 *
	 * @param array<int, array<string, mixed>> $rows Log rows.
	 * @return void
	 */
	private function render_logs_table( array $rows ): void {
		echo '<table class="widefat striped">';
		$this->render_table_header();
		echo '<tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="9">';
			echo esc_html__( 'No logs found.', 'signals-dispatch-woocommerce' );
			echo '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				$this->render_log_row( $row );
			}
		}

		echo '</tbody></table>';
	}

	/**
	 * Render table header.
	 *
	 * @return void
	 */
	private function render_table_header(): void {
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'ID', 'signals-dispatch-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Order', 'signals-dispatch-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Phone', 'signals-dispatch-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Template', 'signals-dispatch-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'signals-dispatch-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Error', 'signals-dispatch-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Message ID', 'signals-dispatch-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Date', 'signals-dispatch-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Details', 'signals-dispatch-woocommerce' ) . '</th>';
		echo '</tr></thead>';
	}

	/**
	 * Render a single log row.
	 *
	 * @param array<string, mixed> $row Log data.
	 * @return void
	 */
	private function render_log_row( array $row ): void {
		$order_display = ! empty( $row['order_id'] ) ? (string) $row['order_id'] : '-';
		$wa_id_display = ! empty( $row['wa_message_id'] ) ? (string) $row['wa_message_id'] : '-';
		$error_display = ! empty( $row['error_message'] ) ? (string) $row['error_message'] : '-';
		$row_id        = 'tmasd-detail-' . (int) $row['id'];

		echo '<tr>';
		echo '<td>' . esc_html( (string) $row['id'] ) . '</td>';
		echo '<td>' . esc_html( $order_display ) . '</td>';
		echo '<td>' . esc_html( (string) $row['phone_e164'] ) . '</td>';
		echo '<td>' . esc_html( (string) $row['template_name'] ) . '</td>';
		echo '<td>' . esc_html( (string) $row['status'] ) . '</td>';
		echo '<td style="max-width:200px;word-wrap:break-word;">' . esc_html( $error_display ) . '</td>';
		echo '<td>' . esc_html( $wa_id_display ) . '</td>';
		echo '<td>' . esc_html( (string) $row['created_at'] ) . '</td>';
		echo '<td>';
		echo '<a href="#" onclick="document.getElementById(\'' . esc_attr( $row_id ) . '\').style.display = document.getElementById(\'' . esc_attr( $row_id ) . '\').style.display === \'none\' ? \'table-row\' : \'none\'; return false;">';
		echo esc_html__( 'View', 'signals-dispatch-woocommerce' );
		echo '</a>';
		echo '</td>';
		echo '</tr>';

		$this->render_detail_row( $row, $row_id );
	}

	/**
	 * Render expandable detail row with full payload and response.
	 *
	 * @param array<string, mixed> $row    Log data.
	 * @param string               $row_id HTML element ID.
	 * @return void
	 */
	private function render_detail_row( array $row, string $row_id ): void {
		$payload  = $this->format_json( $row['payload_json'] ?? '{}' );
		$response = $this->format_json( $row['response_json'] ?? '{}' );

		echo '<tr id="' . esc_attr( $row_id ) . '" style="display:none; background:#f9f9f9;">';
		echo '<td colspan="9" style="padding:15px;">';

		if ( ! empty( $row['error_code'] ) ) {
			echo '<strong>' . esc_html__( 'Error Code:', 'signals-dispatch-woocommerce' ) . '</strong> ';
			echo esc_html( (string) $row['error_code'] ) . '<br>';
		}

		echo '<strong>' . esc_html__( 'Payload:', 'signals-dispatch-woocommerce' ) . '</strong>';
		echo '<pre style="background:#fff;padding:10px;border:1px solid #ddd;overflow:auto;max-height:200px;">';
		echo esc_html( $payload );
		echo '</pre>';

		echo '<strong>' . esc_html__( 'Response:', 'signals-dispatch-woocommerce' ) . '</strong>';
		echo '<pre style="background:#fff;padding:10px;border:1px solid #ddd;overflow:auto;max-height:200px;">';
		echo esc_html( $response );
		echo '</pre>';

		echo '</td></tr>';
	}

	/**
	 * Pretty-print a JSON string.
	 *
	 * @param string $json JSON string.
	 * @return string Formatted JSON.
	 */
	private function format_json( string $json ): string {
		$decoded = json_decode( $json, true );

		if ( ! is_array( $decoded ) ) {
			return $json;
		}

		$encoded = wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		return is_string( $encoded ) ? $encoded : $json;
	}

	/**
	 * Render pagination.
	 *
	 * @param int    $total  Total items.
	 * @param int    $paged  Current page.
	 * @param string $status Current status filter.
	 * @param string $search Current search term.
	 * @return void
	 */
	private function render_pagination( int $total, int $paged, string $status, string $search ): void {
		$total_pages = (int) ceil( $total / self::PER_PAGE );

		if ( $total_pages <= 1 ) {
			return;
		}

		$base_url = admin_url( 'admin.php?page=tmasd-logs' );

		if ( '' !== $status ) {
			$base_url = add_query_arg( 'status', $status, $base_url );
		}

		if ( '' !== $search ) {
			$base_url = add_query_arg( 's', $search, $base_url );
		}

		$pagination = paginate_links(
			array(
				'base'      => esc_url_raw( add_query_arg( 'paged', '%#%', $base_url ) ),
				'format'    => '',
				'prev_text' => '&laquo;',
				'next_text' => '&raquo;',
				'total'     => $total_pages,
				'current'   => $paged,
			)
		);

		echo '<div class="tablenav"><div class="tablenav-pages">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links returns safe HTML.
		echo $pagination;
		echo '</div></div>';
	}
}
