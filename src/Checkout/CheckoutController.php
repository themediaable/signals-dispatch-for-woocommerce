<?php
/**
 * Checkout opt-in controller.
 *
 * Renders the WhatsApp opt-in checkbox on the WooCommerce checkout
 * and processes consent when the order is placed.
 *
 * @package TMASD\Signals\Dispatch\Checkout
 */

declare(strict_types=1);

namespace TMASD\Signals\Dispatch\Checkout;

use TMASD\Signals\Dispatch\Contracts\PhoneNormalizerInterface;
use TMASD\Signals\Dispatch\Core\AbstractService;
use TMASD\Signals\Dispatch\Database\OptinRepository;
use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checkout opt-in controller.
 *
 * Adds a "Send me order updates on WhatsApp" checkbox to the
 * WooCommerce checkout (classic and block) and records consent
 * in the wp_tmasd_optins table.
 *
 * @final
 */
final class CheckoutController extends AbstractService {

	/**
	 * Opt-in repository.
	 *
	 * @var OptinRepository
	 */
	private OptinRepository $optin_repo;

	/**
	 * Phone normalizer.
	 *
	 * @var PhoneNormalizerInterface
	 */
	private PhoneNormalizerInterface $phone_normalizer;

	/**
	 * Constructor.
	 *
	 * @param OptinRepository          $optin_repo       Opt-in repository.
	 * @param PhoneNormalizerInterface $phone_normalizer Phone normalizer.
	 */
	public function __construct(
		OptinRepository $optin_repo,
		PhoneNormalizerInterface $phone_normalizer
	) {
		$this->optin_repo       = $optin_repo;
		$this->phone_normalizer = $phone_normalizer;
	}

	/**
	 * Boot the service and register hooks.
	 *
	 * @return void
	 */
	public function boot(): void {
		// Only register opt-in UI when consent enforcement is enabled.
		if ( ! $this->is_consent_enabled() ) {
			return;
		}

		// Register checkout field via WooCommerce Additional Checkout Fields API
		// (works for both block and classic checkout since WC 8.6).
		add_action( 'woocommerce_init', array( $this, 'register_checkout_field' ) );

		// Classic checkout fallback for stores that don't support the API.
		add_action( 'woocommerce_review_order_before_submit', array( $this, 'render_optin_checkbox' ) );

		// Process consent after order is placed.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_optin' ), 10, 1 );

		// Process consent from block checkout Store API.
		add_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			array( $this, 'process_block_optin' ),
			10,
			2
		);
	}

	/**
	 * Check whether consent enforcement is enabled in settings.
	 *
	 * @return bool True when consent is required.
	 */
	private function is_consent_enabled(): bool {
		return (bool) get_option( \TMASD_OPTION_REQUIRE_CONSENT, false );
	}

	/**
	 * Register the WhatsApp opt-in checkbox via WooCommerce Additional Checkout Fields API.
	 *
	 * @return void
	 */
	public function register_checkout_field(): void {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		woocommerce_register_additional_checkout_field(
			array(
				'id'       => 'tmasd/whatsapp-optin',
				'label'    => __( 'Send me order updates on WhatsApp', 'signals-dispatch-woocommerce' ),
				'location' => 'order',
				'type'     => 'checkbox',
			)
		);
	}

	/**
	 * Render the WhatsApp opt-in checkbox on classic checkout.
	 *
	 * @return void
	 */
	public function render_optin_checkbox(): void {
		woocommerce_form_field(
			'tmasd_whatsapp_optin',
			array(
				'type'  => 'checkbox',
				'class' => array( 'form-row-wide' ),
				'label' => esc_html__( 'Send me order updates on WhatsApp', 'signals-dispatch-woocommerce' ),
			)
		);
	}

	/**
	 * Process opt-in from classic checkout.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function process_optin( $order_id ): void {
		$order_id = (int) $order_id;

		// Check WooCommerce Additional Checkout Fields API meta first.
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;

		if ( $order instanceof WC_Order ) {
			$wc_field_value = $order->get_meta( '_wc_other/tmasd/whatsapp-optin' );

			if ( '' !== $wc_field_value ) {
				$this->save_consent( $order_id, '1' === $wc_field_value );
				return;
			}
		}

		// Fallback: read from classic checkout POST field.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce.
		$opted_in = ! empty( $_POST['tmasd_whatsapp_optin'] );

		$this->save_consent( $order_id, $opted_in );
	}

	/**
	 * Save consent for an order.
	 *
	 * Shared by both classic and block checkout paths. Never throws
	 * or blocks checkout — all failures are silently absorbed.
	 *
	 * @param int  $order_id WooCommerce order ID.
	 * @param bool $opted_in Whether the customer opted in.
	 * @return void
	 */
	public function save_consent( int $order_id, bool $opted_in ): void {
		try {
			$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;

			if ( ! $order instanceof WC_Order ) {
				return;
			}

			// Store opt-in preference as order meta.
			$order->update_meta_data( '_tmasd_whatsapp_optin', $opted_in ? 'yes' : 'no' );
			$order->save();

			if ( ! $opted_in ) {
				return;
			}

			$phone = $this->phone_normalizer->normalize( (string) $order->get_billing_phone() );

			if ( '' === $phone ) {
				return;
			}

			// Prevent duplicate consent rows for the same order.
			$existing = $this->optin_repo->find_by_order_id( $order_id );

			if ( null !== $existing ) {
				return;
			}

			$user_id = $order->get_customer_id();

			$this->optin_repo->record_consent(
				$phone,
				true,
				'checkout',
				$user_id > 0 ? $user_id : null,
				$order_id
			);
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Intentionally silent to never block checkout.
			// Silently ignore — consent failure must never block checkout.
		}
	}

	/**
	 * Process opt-in from block checkout via Store API.
	 *
	 * WooCommerce Additional Checkout Fields API stores the value
	 * automatically. This hook reads it back and records consent.
	 *
	 * @param WC_Order         $order   Order object.
	 * @param \WP_REST_Request $request REST request.
	 * @return void
	 */
	public function process_block_optin( $order, $request ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$wc_field_value = $order->get_meta( '_wc_other/tmasd/whatsapp-optin' );
		$opted_in       = '1' === $wc_field_value;

		$this->save_consent( $order->get_id(), $opted_in );
	}
}
