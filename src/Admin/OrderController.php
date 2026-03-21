<?php
/**
 * Order admin controller for manual WhatsApp sends.
 *
 * @package TMASD\Signals\Dispatch\Admin
 */

declare(strict_types=1);

namespace TMASD\Signals\Dispatch\Admin;

use TMASD\Signals\Dispatch\Database\LogRepository;
use TMASD\Signals\Dispatch\Database\MappingRepository;
use TMASD\Signals\Dispatch\Queue\QueueService;
use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a "Send WhatsApp Update" meta box to the WooCommerce order page.
 *
 * Allows admins to manually trigger a mapped WhatsApp template message
 * for an order, using the existing queue/send pipeline.
 *
 * @final
 */
final class OrderController extends AbstractAdminController {

	/**
	 * Queue service.
	 *
	 * @var QueueService
	 */
	private QueueService $queue;

	/**
	 * Mapping repository.
	 *
	 * @var MappingRepository
	 */
	private MappingRepository $mapping_repo;

	/**
	 * Log repository.
	 *
	 * @var LogRepository
	 */
	private LogRepository $log_repo;

	/**
	 * Constructor.
	 *
	 * @param QueueService      $queue        Queue service.
	 * @param MappingRepository $mapping_repo Mapping repository.
	 * @param LogRepository     $log_repo     Log repository.
	 */
	public function __construct(
		QueueService $queue,
		MappingRepository $mapping_repo,
		LogRepository $log_repo
	) {
		$this->queue        = $queue;
		$this->mapping_repo = $mapping_repo;
		$this->log_repo     = $log_repo;
	}

	/**
	 * Boot the controller and register hooks.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
	}

	/**
	 * Register the meta box on order screens (legacy + HPOS).
	 *
	 * @return void
	 */
	public function register_meta_box(): void {
		$screens = array( 'shop_order', 'woocommerce_page_wc-orders' );

		foreach ( $screens as $screen ) {
			add_meta_box(
				'tmasd_manual_send',
				__( 'Send WhatsApp Update', 'signals-dispatch-for-woocommerce' ),
				array( $this, 'render_meta_box' ),
				$screen,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render the manual send meta box.
	 *
	 * @param \WP_Post|WC_Order $post_or_order Post object (legacy) or WC_Order (HPOS).
	 * @return void
	 */
	public function render_meta_box( $post_or_order ): void {
		$order_id = $this->resolve_order_id( $post_or_order );

		if ( $order_id <= 0 ) {
			echo '<p>' . esc_html__( 'Order not found.', 'signals-dispatch-for-woocommerce' ) . '</p>';
			return;
		}

		$mappings = $this->get_enabled_mappings();

		if ( empty( $mappings ) ) {
			echo '<p>' . esc_html__( 'No enabled dispatch rules found. ', 'signals-dispatch-for-woocommerce' );
			printf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=tmasd-dispatch' ) ),
				esc_html__( 'Create a dispatch rule', 'signals-dispatch-for-woocommerce' )
			);
			echo '</p>';
			return;
		}

		$nonce_action = 'tmasd_manual_send_' . $order_id;
		$nonce_value  = wp_create_nonce( $nonce_action );

		echo '<div id="tmasd-send-notice"></div>';

		echo '<p>';
		echo '<label for="tmasd_mapping_id"><strong>';
		echo esc_html__( 'Dispatch Rule', 'signals-dispatch-for-woocommerce' );
		echo '</strong></label>';
		echo '</p>';

		echo '<select id="tmasd_mapping_id" style="width:100%;margin-bottom:10px;">';
		foreach ( $mappings as $mapping ) {
			$events     = $this->mapping_repo->get_available_events();
			$event_label = $events[ $mapping['event_key'] ] ?? $mapping['event_key'];
			printf(
				'<option value="%d">%s — %s</option>',
				(int) $mapping['id'],
				esc_html( $event_label ),
				esc_html( $mapping['template_name'] )
			);
		}
		echo '</select>';

		echo '<p class="description">';
		echo esc_html__( 'Sends the selected template to the customer\'s billing phone number.', 'signals-dispatch-for-woocommerce' );
		echo '</p>';

		echo '<p>';
		printf(
			'<button type="button" id="tmasd-send-btn" class="button button-primary">%s</button>',
			esc_html__( 'Send WhatsApp Update', 'signals-dispatch-for-woocommerce' )
		);
		echo '</p>';

		// Inline AJAX script — avoids nested-form issue with WooCommerce order page.
		?>
		<script>
		(function(){
			var btn       = document.getElementById('tmasd-send-btn');
			var select    = document.getElementById('tmasd_mapping_id');
			var noticeBox = document.getElementById('tmasd-send-notice');

			function showNotice(cls, message) {
				noticeBox.textContent = '';
				var div = document.createElement('div');
				div.className = 'notice ' + cls + ' inline';
				var p = document.createElement('p');
				p.textContent = message;
				div.appendChild(p);
				noticeBox.appendChild(div);
			}

			btn.addEventListener('click', function(){
				btn.disabled = true;
				btn.textContent = <?php echo wp_json_encode( __( 'Sending…', 'signals-dispatch-for-woocommerce' ) ); ?>;
				noticeBox.textContent = '';

				var data = new FormData();
				data.append('action',     'tmasd_manual_send');
				data.append('order_id',   <?php echo wp_json_encode( (string) $order_id ); ?>);
				data.append('mapping_id', select.value);
				data.append('_ajax_nonce', <?php echo wp_json_encode( $nonce_value ); ?>);

				fetch(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, {
					method: 'POST',
					credentials: 'same-origin',
					body: data
				})
				.then(function(r){ return r.json(); })
				.then(function(res){
					var cls = res.success ? 'notice-success' : 'notice-error';
					showNotice(cls, res.data.message);
					btn.disabled    = false;
					btn.textContent = <?php echo wp_json_encode( __( 'Send WhatsApp Update', 'signals-dispatch-for-woocommerce' ) ); ?>;
				})
				.catch(function(){
					showNotice('notice-error', <?php echo wp_json_encode( __( 'Request failed. Please try again.', 'signals-dispatch-for-woocommerce' ) ); ?>);
					btn.disabled    = false;
					btn.textContent = <?php echo wp_json_encode( __( 'Send WhatsApp Update', 'signals-dispatch-for-woocommerce' ) ); ?>;
				});
			});
		})();
		</script>
		<?php
	}

	/**
	 * Handle manual send via AJAX.
	 *
	 * Sends the message synchronously and returns a JSON response
	 * so the admin gets immediate feedback in the meta box.
	 *
	 * @return void
	 */
	public function handle_manual_send(): void {
		if ( ! current_user_can( $this->get_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have sufficient permissions.', 'signals-dispatch-for-woocommerce' ) ), 403 );
		}

		$order_id   = (int) $this->get_post_param( 'order_id', '0' );
		$mapping_id = (int) $this->get_post_param( 'mapping_id', '0' );

		if ( $order_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'signals-dispatch-for-woocommerce' ) ) );
		}

		check_ajax_referer( 'tmasd_manual_send_' . $order_id );

		if ( $mapping_id <= 0 ) {
			$this->log_manual_send_failure( $order_id, 'Invalid request: missing mapping ID.' );
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'signals-dispatch-for-woocommerce' ) ) );
		}

		// Prevent duplicate sends within 30 seconds.
		$transient_key = 'tmasd_manual_send_' . $order_id;
		if ( false !== get_transient( $transient_key ) ) {
			wp_send_json_error( array( 'message' => __( 'A message was already sent recently. Please wait before sending again.', 'signals-dispatch-for-woocommerce' ) ) );
		}

		$mapping = $this->mapping_repo->find( $mapping_id );

		if ( null === $mapping || empty( $mapping['enabled'] ) ) {
			$this->log_manual_send_failure( $order_id, 'Selected dispatch rule is disabled or does not exist.' );
			wp_send_json_error( array( 'message' => __( 'Selected dispatch rule is disabled or does not exist.', 'signals-dispatch-for-woocommerce' ) ) );
		}

		// Send synchronously so the log entry and result are immediate.
		$this->queue->set_trigger_source( 'manual' );
		$this->queue->handle_send_template_message( $order_id, $mapping['event_key'], 0 );

		// Check the log to determine if it succeeded or failed.
		$last_log = $this->log_repo->find_last_by_order_id( $order_id );

		// Set transient to prevent rapid duplicate sends.
		set_transient( $transient_key, '1', 30 );

		// Add WooCommerce order note.
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		$is_failed = $last_log && in_array( $last_log['status'], array( 'failed', 'skipped' ), true );

		if ( $order instanceof WC_Order ) {
			$current_user = wp_get_current_user();
			$user_display = $current_user->exists() ? $current_user->user_login : __( 'Unknown', 'signals-dispatch-for-woocommerce' );

			if ( ! $is_failed ) {
				$order->add_order_note(
					sprintf(
						/* translators: 1: template name, 2: admin user login */
						__( 'Signals: WhatsApp message "%1$s" sent manually by %2$s.', 'signals-dispatch-for-woocommerce' ),
						$mapping['template_name'],
						$user_display
					)
				);
			} else {
				$error_msg = $last_log['error_message'] ?? __( 'Unknown error', 'signals-dispatch-for-woocommerce' );
				$order->add_order_note(
					sprintf(
						/* translators: 1: template name, 2: admin user login, 3: error message */
						__( 'Signals: WhatsApp message "%1$s" failed (sent by %2$s). Error: %3$s', 'signals-dispatch-for-woocommerce' ),
						$mapping['template_name'],
						$user_display,
						$error_msg
					)
				);
			}
		}

		if ( $is_failed ) {
			$error_msg = $last_log['error_message'] ?? __( 'Unknown error', 'signals-dispatch-for-woocommerce' );
			wp_send_json_error( array( 'message' => __( 'Failed to send WhatsApp message.', 'signals-dispatch-for-woocommerce' ) . ' ' . $error_msg ) );
		}

		wp_send_json_success( array( 'message' => __( 'WhatsApp message sent successfully.', 'signals-dispatch-for-woocommerce' ) ) );
	}

	/**
	 * Log a failed manual send attempt.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $reason   Reason for failure.
	 * @return void
	 */
	private function log_manual_send_failure( int $order_id, string $reason ): void {
		$this->log_repo->insert(
			array(
				'order_id'       => $order_id > 0 ? $order_id : 0,
				'phone_e164'     => '',
				'template_name'  => '',
				'payload_json'   => wp_json_encode( array( 'source' => 'manual_send' ) ),
				'response_json'  => '{}',
				'status'         => 'failed',
				'error_message'  => $reason,
				'trigger_source' => 'manual',
			)
		);
	}

	/**
	 * Resolve order ID from post object or WC_Order (HPOS).
	 *
	 * @param \WP_Post|WC_Order|mixed $post_or_order Post or order object.
	 * @return int Order ID or 0.
	 */
	private function resolve_order_id( $post_or_order ): int {
		if ( $post_or_order instanceof WC_Order ) {
			return $post_or_order->get_id();
		}

		if ( $post_or_order instanceof \WP_Post ) {
			return $post_or_order->ID;
		}

		return 0;
	}

	/**
	 * Get all enabled dispatch mappings.
	 *
	 * @return array<int, array<string, mixed>> Enabled mappings.
	 */
	private function get_enabled_mappings(): array {
		$all = $this->mapping_repo->all();

		return array_filter(
			$all,
			static function ( array $mapping ): bool {
				return ! empty( $mapping['enabled'] );
			}
		);
	}

	/**
	 * Render page — not used (meta box only).
	 *
	 * @return void
	 */
	public function render(): void {
		// Not used — rendering is via meta box.
	}
}
