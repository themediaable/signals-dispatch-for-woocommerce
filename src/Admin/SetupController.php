<?php
/**
 * Setup page controller.
 *
 * @package TMASD\Signals\Dispatch\Admin
 */

declare(strict_types=1);

namespace TMASD\Signals\Dispatch\Admin;

use TMASD\Signals\Dispatch\Contracts\ApiClientInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Setup page controller.
 *
 * Handles the setup wizard for plugin configuration.
 * Single Responsibility: Setup page rendering and form handling only.
 *
 * @final
 */
final class SetupController extends AbstractAdminController {

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	protected string $page_slug = 'tmasd-setup';

	/**
	 * API client service.
	 *
	 * @var ApiClientInterface
	 */
	private ApiClientInterface $api_client;

	/**
	 * Constructor.
	 *
	 * @param ApiClientInterface $api_client API client service.
	 */
	public function __construct( ApiClientInterface $api_client ) {
		$this->api_client = $api_client;
	}

	/**
	 * Render the setup page.
	 *
	 * @return void
	 */
	public function render(): void {
		$this->assert_access();
		$active_tab = $this->get_query_param( 'tab', 'credentials' );

		$this->render_notices();
		$this->render_page_header();
		$this->render_tabs( $active_tab );
		$this->render_tab_content( $active_tab );

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
		echo esc_html__( 'Signals Dispatch Setup', 'signals-dispatch-woocommerce' );
		echo '</h1>';
		echo '<hr class="wp-header-end" />';
	}

	/**
	 * Render tab navigation.
	 *
	 * @param string $active_tab Current active tab.
	 * @return void
	 */
	private function render_tabs( string $active_tab ): void {
		$tabs = array(
			'credentials' => __( 'Step 1: Credentials', 'signals-dispatch-woocommerce' ),
			'webhook'     => __( 'Step 2: Webhook', 'signals-dispatch-woocommerce' ),
			'test'        => __( 'Step 3: Test', 'signals-dispatch-woocommerce' ),
			'done'        => __( 'Step 4: Done', 'signals-dispatch-woocommerce' ),
		);

		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $label ) {
			$url   = admin_url( 'admin.php?page=tmasd-setup&tab=' . $key );
			$class = $active_tab === $key ? 'nav-tab nav-tab-active' : 'nav-tab';
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">';
			echo esc_html( $label );
			echo '</a>';
		}
		echo '</h2>';
	}

	/**
	 * Render tab content.
	 *
	 * @param string $tab Tab key.
	 * @return void
	 */
	private function render_tab_content( string $tab ): void {
		switch ( $tab ) {
			case 'credentials':
				$this->render_credentials_form();
				break;
			case 'webhook':
				$this->render_webhook_info();
				break;
			case 'test':
				$this->render_test_form();
				break;
			default:
				$this->render_done_panel();
				break;
		}
	}

	/**
	 * Render notices.
	 *
	 * @return void
	 */
	private function render_notices(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		if ( isset( $_GET['updated'] ) ) {
			$this->render_notice_success( __( 'Settings saved.', 'signals-dispatch-woocommerce' ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		if ( isset( $_GET['test_success'] ) ) {
			$this->render_notice_success( __( 'Test message sent successfully.', 'signals-dispatch-woocommerce' ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		if ( isset( $_GET['test_error'] ) ) {
			$this->render_notice_error( __( 'Test message failed. Check logs.', 'signals-dispatch-woocommerce' ) );
		}
	}

	/**
	 * Render credentials form.
	 *
	 * @return void
	 */
	private function render_credentials_form(): void {
		$phone_id     = get_option( \TMASD_OPTION_PHONE_NUMBER_ID, '' );
		$waba_id      = get_option( \TMASD_OPTION_WABA_ID, '' );
		$access_token = get_option( \TMASD_OPTION_ACCESS_TOKEN, '' );
		$verify_token = get_option( \TMASD_OPTION_WEBHOOK_VERIFY_TOKEN, '' );

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'tmasd_save_setup' );
		echo '<input type="hidden" name="action" value="tmasd_save_setup" />';

		echo '<table class="form-table">';
		$this->render_text_field( \TMASD_OPTION_PHONE_NUMBER_ID, __( 'Phone Number ID', 'signals-dispatch-woocommerce' ), $phone_id );
		$this->render_text_field( \TMASD_OPTION_WABA_ID, __( 'WABA ID', 'signals-dispatch-woocommerce' ), $waba_id );
		$this->render_password_field( \TMASD_OPTION_ACCESS_TOKEN, __( 'Access Token', 'signals-dispatch-woocommerce' ), $access_token );
		$this->render_text_field( \TMASD_OPTION_WEBHOOK_VERIFY_TOKEN, __( 'Webhook Verify Token', 'signals-dispatch-woocommerce' ), $verify_token );
		echo '</table>';

		submit_button( __( 'Save Settings', 'signals-dispatch-woocommerce' ) );
		echo '</form>';
	}

	/**
	 * Render text field row.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @param string $value Current value.
	 * @return void
	 */
	private function render_text_field( string $name, string $label, string $value ): void {
		echo '<tr><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><input type="text" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" ';
		echo 'value="' . esc_attr( $value ) . '" class="regular-text" /></td></tr>';
	}

	/**
	 * Render password field row.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @param string $value Current value.
	 * @return void
	 */
	private function render_password_field( string $name, string $label, string $value ): void {
		echo '<tr><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><input type="password" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" ';
		echo 'value="' . esc_attr( $value ) . '" class="regular-text" /></td></tr>';
	}

	/**
	 * Render webhook info panel.
	 *
	 * @return void
	 */
	private function render_webhook_info(): void {
		$webhook_url = rest_url( 'signals/v1/webhook' );

		echo '<div class="tmasd-card">';
		echo '<h2>' . esc_html__( 'Webhook Configuration', 'signals-dispatch-woocommerce' ) . '</h2>';
		echo '<p>' . esc_html__( 'Use this URL in your WhatsApp Business App settings:', 'signals-dispatch-woocommerce' ) . '</p>';
		echo '<code>' . esc_url( $webhook_url ) . '</code>';
		echo '</div>';
	}

	/**
	 * Render test message form.
	 *
	 * @return void
	 */
	private function render_test_form(): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'tmasd_send_test' );
		echo '<input type="hidden" name="action" value="tmasd_send_test" />';

		echo '<table class="form-table">';
		echo '<tr><th scope="row"><label for="tmasd_test_phone">';
		echo esc_html__( 'Test Phone', 'signals-dispatch-woocommerce' );
		echo '</label></th>';
		echo '<td><input type="text" id="tmasd_test_phone" name="tmasd_test_phone" class="regular-text" /></td></tr>';

		echo '<tr><th scope="row"><label for="tmasd_test_template">';
		echo esc_html__( 'Template Name', 'signals-dispatch-woocommerce' );
		echo '</label></th>';
		echo '<td><input type="text" id="tmasd_test_template" name="tmasd_test_template" class="regular-text" /></td></tr>';

		echo '<tr><th scope="row"><label for="tmasd_test_language">';
		echo esc_html__( 'Language', 'signals-dispatch-woocommerce' );
		echo '</label></th>';
		echo '<td><input type="text" id="tmasd_test_language" name="tmasd_test_language" value="en_US" class="regular-text" /></td></tr>';

		echo '<tr><th scope="row"><label for="tmasd_test_vars">';
		echo esc_html__( 'Variables (JSON array)', 'signals-dispatch-woocommerce' );
		echo '</label></th>';
		echo '<td><textarea id="tmasd_test_vars" name="tmasd_test_vars" rows="4" class="large-text">[]</textarea></td></tr>';
		echo '</table>';

		submit_button( __( 'Send Test Message', 'signals-dispatch-woocommerce' ) );
		echo '</form>';
	}

	/**
	 * Render done panel.
	 *
	 * @return void
	 */
	private function render_done_panel(): void {
		echo '<div class="tmasd-card">';
		echo '<h2>' . esc_html__( 'Setup Complete', 'signals-dispatch-woocommerce' ) . '</h2>';
		echo '<p>' . esc_html__( 'Your plugin is configured. Create dispatch rules to start sending messages.', 'signals-dispatch-woocommerce' ) . '</p>';
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=tmasd-dispatch' ) ) . '" class="button button-primary">';
		echo esc_html__( 'Go to Dispatch Rules', 'signals-dispatch-woocommerce' );
		echo '</a></p>';
		echo '</div>';
	}

	/**
	 * Handle save setup form submission.
	 *
	 * @return void
	 */
	public function handle_save(): void {
		$this->assert_access();
		$this->verify_nonce( 'tmasd_save_setup' );

		$fields = array(
			\TMASD_OPTION_PHONE_NUMBER_ID,
			\TMASD_OPTION_WABA_ID,
			\TMASD_OPTION_ACCESS_TOKEN,
			\TMASD_OPTION_WEBHOOK_VERIFY_TOKEN,
		);

		foreach ( $fields as $field ) {
			$value = $this->get_post_param( $field );
			update_option( $field, $value );
		}

		$this->redirect_with_status( 'tmasd-setup', 'updated' );
	}

	/**
	 * Handle send test message form submission.
	 *
	 * @return void
	 */
	public function handle_test(): void {
		$this->assert_access();
		$this->verify_nonce( 'tmasd_send_test' );

		$phone    = $this->get_post_param( 'tmasd_test_phone' );
		$template = $this->get_post_param( 'tmasd_test_template' );
		$language = $this->get_post_param( 'tmasd_test_language', 'en_US' );
		$vars_raw = $this->get_post_param( 'tmasd_test_vars', '[]' );

		$vars   = json_decode( $vars_raw, true );
		$vars   = is_array( $vars ) ? $vars : array();
		$result = $this->api_client->send_template_message( $phone, $template, $language, $vars );

		if ( ! empty( $result['success'] ) ) {
			$this->redirect_with_status( 'tmasd-setup&tab=test', 'test_success' );
		} else {
			$this->redirect_with_status( 'tmasd-setup&tab=test', 'test_error' );
		}
	}
}
