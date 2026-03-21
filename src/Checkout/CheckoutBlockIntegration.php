<?php
/**
 * Block checkout integration for WhatsApp opt-in.
 *
 * @package TMASD\Signals\Dispatch\Checkout
 */

declare(strict_types=1);

namespace TMASD\Signals\Dispatch\Checkout;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the WhatsApp opt-in checkbox with WC Blocks checkout.
 *
 * @final
 */
final class CheckoutBlockIntegration implements IntegrationInterface {

	/**
	 * Checkout controller reference for shared logic.
	 *
	 * @var CheckoutController
	 */
	private CheckoutController $checkout_controller;

	/**
	 * Constructor.
	 *
	 * @param CheckoutController $checkout_controller Checkout controller.
	 */
	public function __construct( CheckoutController $checkout_controller ) {
		$this->checkout_controller = $checkout_controller;
	}

	/**
	 * Integration name used as script handle prefix.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'tmasd-whatsapp-optin';
	}

	/**
	 * Register frontend scripts for the block checkout.
	 *
	 * @return void
	 */
	public function initialize(): void {
		$script_url = \TMASD_PLUGIN_URL . 'assets/checkout-block.js';

		wp_register_script(
			'tmasd-checkout-block',
			$script_url,
			array( 'wc-blocks-checkout' ),
			\TMASD_VERSION,
			true
		);

		wp_localize_script(
			'tmasd-checkout-block',
			'tmasdCheckout',
			array(
				'label' => esc_html__( 'Send me order updates on WhatsApp', 'signals-dispatch-woocommerce' ),
			)
		);
	}

	/**
	 * Return script handles for the block editor (not used).
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles(): array {
		return array();
	}

	/**
	 * Return script handles for the frontend checkout block.
	 *
	 * @return string[]
	 */
	public function get_script_handles(): array {
		return array( 'tmasd-checkout-block' );
	}

	/**
	 * Return data to expose to the frontend script.
	 *
	 * @return array<string, mixed>
	 */
	public function get_script_data(): array {
		return array(
			'label' => esc_html__( 'Send me order updates on WhatsApp', 'signals-dispatch-woocommerce' ),
		);
	}
}
