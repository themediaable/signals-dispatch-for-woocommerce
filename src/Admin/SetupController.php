<?php
/**
 * Setup page controller.
 *
 * @package TMASD\Signals\Dispatch\Admin
 */

declare(strict_types=1);

namespace TMASD\Signals\Dispatch\Admin;

use TMASD\Signals\Dispatch\Contracts\ApiClientInterface;
use TMASD\Signals\Dispatch\Database\LogRepository;

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
	 * Log repository.
	 *
	 * @var LogRepository
	 */
	private LogRepository $log_repo;

	/**
	 * Constructor.
	 *
	 * @param ApiClientInterface $api_client API client service.
	 * @param LogRepository      $log_repo   Log repository.
	 */
	public function __construct( ApiClientInterface $api_client, LogRepository $log_repo ) {
		$this->api_client = $api_client;
		$this->log_repo   = $log_repo;
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
		echo esc_html__( 'Signals Dispatch Setup', 'signals-dispatch-for-woocommerce' );
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
			'credentials' => __( 'Step 1: Credentials', 'signals-dispatch-for-woocommerce' ),
			'webhook'     => __( 'Step 2: Webhook', 'signals-dispatch-for-woocommerce' ),
			'test'        => __( 'Step 3: Test', 'signals-dispatch-for-woocommerce' ),
			'done'        => __( 'Step 4: Done', 'signals-dispatch-for-woocommerce' ),
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
			$this->render_notice_success( __( 'Settings saved.', 'signals-dispatch-for-woocommerce' ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		if ( isset( $_GET['missing_fields'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
			$raw    = sanitize_text_field( wp_unslash( $_GET['missing_fields'] ) );
			$labels = $this->get_field_labels();
			$names  = array_filter(
				array_map(
					static function ( string $key ) use ( $labels ): string {
						return $labels[ $key ] ?? '';
					},
					explode( ',', $raw )
				)
			);
			$this->render_notice_error(
				sprintf(
					/* translators: %s: comma-separated list of required field names */
					__( 'Please fill in the following required fields: %s', 'signals-dispatch-for-woocommerce' ),
					implode( ', ', $names )
				)
			);
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		if ( isset( $_GET['test_success'] ) ) {
			$this->render_notice_success( __( 'Test message sent successfully.', 'signals-dispatch-for-woocommerce' ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		if ( isset( $_GET['test_error'] ) ) {
			$this->render_notice_error( __( 'Test message failed. Check logs.', 'signals-dispatch-for-woocommerce' ) );
		}
	}

	/**
	 * Return a map of option-key → human-readable label (used in error messages).
	 *
	 * @return array<string, string>
	 */
	private function get_field_labels(): array {
		return array(
			\TMASD_OPTION_PHONE_NUMBER_ID      => __( 'Phone Number ID', 'signals-dispatch-for-woocommerce' ),
			\TMASD_OPTION_WABA_ID              => __( 'WhatsApp Business Account ID', 'signals-dispatch-for-woocommerce' ),
			\TMASD_OPTION_ACCESS_TOKEN         => __( 'Access Token', 'signals-dispatch-for-woocommerce' ),
			\TMASD_OPTION_WEBHOOK_VERIFY_TOKEN => __( 'Webhook Verify Token', 'signals-dispatch-for-woocommerce' ),
			\TMASD_OPTION_APP_SECRET           => __( 'App Secret', 'signals-dispatch-for-woocommerce' ),
		);
	}

	/**
	 * Render credentials form.
	 *
	 * @return void
	 */
	private function render_credentials_form(): void {
		$phone_id        = get_option( \TMASD_OPTION_PHONE_NUMBER_ID, '' );
		$waba_id         = get_option( \TMASD_OPTION_WABA_ID, '' );
		$verify_token    = get_option( \TMASD_OPTION_WEBHOOK_VERIFY_TOKEN, '' );
		$has_token       = '' !== get_option( \TMASD_OPTION_ACCESS_TOKEN, '' );
		$has_secret      = '' !== get_option( \TMASD_OPTION_APP_SECRET, '' );
		$require_consent = (bool) get_option( \TMASD_OPTION_REQUIRE_CONSENT, false );
		$help_url        = admin_url( 'admin.php?page=tmasd-help' );

		echo '<p class="description">';
		printf(
			wp_kses(
				/* translators: %s: URL to help page */
				__( 'Enter your Meta WhatsApp Business API credentials below. All fields marked <span class="tmasd-required">*</span> are required. <a href="%s">Need help finding these values?</a>', 'signals-dispatch-for-woocommerce' ),
				array(
					'span' => array( 'class' => array() ),
					'a'    => array( 'href' => array() ),
				)
			),
			esc_url( $help_url )
		);
		echo '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'tmasd_save_setup' );
		echo '<input type="hidden" name="action" value="tmasd_save_setup" />';

		echo '<table class="form-table">';

		$this->render_text_field(
			\TMASD_OPTION_PHONE_NUMBER_ID,
			__( 'Phone Number ID', 'signals-dispatch-for-woocommerce' ),
			$phone_id,
			__( 'The numeric ID of your WhatsApp sender phone number. Found in the Meta Business Manager under WhatsApp → Phone Numbers.', 'signals-dispatch-for-woocommerce' ),
			true
		);

		$this->render_text_field(
			\TMASD_OPTION_WABA_ID,
			__( 'WhatsApp Business Account ID', 'signals-dispatch-for-woocommerce' ),
			$waba_id,
			__( 'The numeric ID of your WhatsApp Business Account (WABA). Found in the Meta Business Manager under Accounts → WhatsApp Accounts.', 'signals-dispatch-for-woocommerce' ),
			true
		);

		$this->render_secret_field(
			\TMASD_OPTION_ACCESS_TOKEN,
			__( 'Access Token', 'signals-dispatch-for-woocommerce' ),
			$has_token,
			__( 'A permanent or temporary system-user access token with the whatsapp_business_messaging permission. Generate one in Meta Business Manager → System Users.', 'signals-dispatch-for-woocommerce' ),
			true
		);

		$this->render_text_field(
			\TMASD_OPTION_WEBHOOK_VERIFY_TOKEN,
			__( 'Webhook Verify Token', 'signals-dispatch-for-woocommerce' ),
			$verify_token,
			__( 'A secret string you create yourself. Enter the same value here and in the "Verify token" field when registering the webhook in Meta Business Manager. Used to confirm that webhook GET requests come from Meta.', 'signals-dispatch-for-woocommerce' ),
			true
		);

		$this->render_secret_field(
			\TMASD_OPTION_APP_SECRET,
			__( 'App Secret', 'signals-dispatch-for-woocommerce' ),
			$has_secret,
			__( 'The App Secret of your Meta App. Found in the Meta App Dashboard under Settings → Basic. Used to verify the HMAC-SHA256 signature on incoming webhook POST requests.', 'signals-dispatch-for-woocommerce' ),
			true
		);

		$this->render_checkbox_field(
			\TMASD_OPTION_REQUIRE_CONSENT,
			__( 'Require Consent Before Sending', 'signals-dispatch-for-woocommerce' ),
			$require_consent,
			__( 'When enabled, messages are only sent to phone numbers that have a local opt-in consent record stored by this plugin. Recommended for GDPR compliance.', 'signals-dispatch-for-woocommerce' )
		);

		echo '</table>';

		submit_button( __( 'Save Settings', 'signals-dispatch-for-woocommerce' ) );
		echo '</form>';
	}

	/**
	 * Render text field row.
	 *
	 * @param string $name        Field name / option key.
	 * @param string $label       Field label.
	 * @param string $value       Current saved value.
	 * @param string $description Optional helper text shown below the input.
	 * @param bool   $required    Whether the field is required.
	 * @return void
	 */
	private function render_text_field( string $name, string $label, string $value, string $description = '', bool $required = false ): void {
		$label_html = esc_html( $label );
		if ( $required ) {
			$label_html .= ' <span class="tmasd-required" aria-hidden="true">*</span>';
		}
		echo '<tr><th scope="row"><label for="' . esc_attr( $name ) . '">' . $label_html . '</label></th>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- label_html built from esc_html.
		echo '<td>';
		echo '<input type="text" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" ';
		echo 'value="' . esc_attr( $value ) . '" class="regular-text"';
		if ( $required ) {
			echo ' aria-required="true"';
		}
		echo ' />';
		if ( '' !== $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Render a secret field row that never exposes the stored value.    *
	 * Leaves the input empty so the secret is not echoed into the DOM.
	 * Shows a placeholder when a value is already saved so the admin
	 * knows a secret is stored without revealing it. The stored value
	 * is only overwritten on save when the field is non-empty.
	 *
	 * @param string $name      Field name / option key.
	 * @param string $label     Field label.
	 * @param bool   $has_value Whether a value is already saved.
	 * @return void
	 */
	/**
	 * Render a secret field row that never exposes the stored value.
	 *
	 * @param string $name        Field name / option key.
	 * @param string $label       Field label.
	 * @param bool   $has_value   Whether a value is already saved.
	 * @param string $description Optional helper text shown below the input.
	 * @param bool   $required    Whether the field is required.
	 * @return void
	 */
	private function render_secret_field( string $name, string $label, bool $has_value, string $description = '', bool $required = false ): void {
		$placeholder = $has_value
			? esc_attr__( '•••••••• already saved — leave blank to keep', 'signals-dispatch-for-woocommerce' )
			: '';
		$label_html  = esc_html( $label );
		if ( $required ) {
			$label_html .= ' <span class="tmasd-required" aria-hidden="true">*</span>';
		}
		echo '<tr><th scope="row"><label for="' . esc_attr( $name ) . '">' . $label_html . '</label></th>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- label_html built from esc_html.
		echo '<td>';
		echo '<input type="password" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" ';
		echo 'value="" placeholder="' . $placeholder . '" autocomplete="new-password" class="regular-text"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_attr applied above.
		if ( $required ) {
			echo ' aria-required="true"';
		}
		echo ' />';
		if ( '' !== $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Render a checkbox field row.
	 *
	 * @param string $name        Field name / option key.
	 * @param string $label       Field label.
	 * @param bool   $checked     Whether the checkbox is currently checked.
	 * @param string $description Optional description shown below the checkbox.
	 * @return void
	 */
	private function render_checkbox_field( string $name, string $label, bool $checked, string $description = '' ): void {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th>';
		echo '<td>';
		echo '<label for="' . esc_attr( $name ) . '">';
		echo '<input type="checkbox" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="1"';
		checked( $checked, true );
		echo ' />';
		if ( '' !== $description ) {
			echo ' ' . esc_html( $description );
		}
		echo '</label>';
		echo '</td></tr>';
	}

	/**
	 * Render webhook info panel.
	 *
	 * @return void
	 */
	private function render_webhook_info(): void {
		$webhook_url = rest_url( 'tmasignals/v1/webhook' );

		echo '<div class="tmasd-card">';
		echo '<h2>' . esc_html__( 'Webhook Configuration', 'signals-dispatch-for-woocommerce' ) . '</h2>';
		echo '<p>' . esc_html__( 'Use this URL in your WhatsApp Business App settings:', 'signals-dispatch-for-woocommerce' ) . '</p>';
		echo '<code class="tmasd-webhook-url">' . esc_url( $webhook_url ) . '</code>';
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
		echo esc_html__( 'Test Phone', 'signals-dispatch-for-woocommerce' );
		echo '</label></th>';
		echo '<td><input type="text" id="tmasd_test_phone" name="tmasd_test_phone" class="regular-text" /></td></tr>';

		echo '<tr><th scope="row"><label for="tmasd_test_template">';
		echo esc_html__( 'Template Name', 'signals-dispatch-for-woocommerce' );
		echo '</label></th>';
		echo '<td><input type="text" id="tmasd_test_template" name="tmasd_test_template" class="regular-text" /></td></tr>';

		echo '<tr><th scope="row"><label for="tmasd_test_language">';
		echo esc_html__( 'Language', 'signals-dispatch-for-woocommerce' );
		echo '</label></th>';
		echo '<td><input type="text" id="tmasd_test_language" name="tmasd_test_language" value="en_US" class="regular-text" /></td></tr>';

		echo '<tr><th scope="row"><label for="tmasd_test_vars">';
		echo esc_html__( 'Variables (JSON array)', 'signals-dispatch-for-woocommerce' );
		echo '</label></th>';
		echo '<td><textarea id="tmasd_test_vars" name="tmasd_test_vars" rows="4" class="large-text">[]</textarea></td></tr>';
		echo '</table>';

		submit_button( __( 'Send Test Message', 'signals-dispatch-for-woocommerce' ) );
		echo '</form>';
	}

	/**
	 * Render done panel.
	 *
	 * @return void
	 */
	private function render_done_panel(): void {
		echo '<div class="tmasd-card">';
		echo '<h2>' . esc_html__( 'Setup Complete', 'signals-dispatch-for-woocommerce' ) . '</h2>';
		echo '<p>' . esc_html__( 'Your plugin is configured. Create dispatch rules to start sending messages.', 'signals-dispatch-for-woocommerce' ) . '</p>';
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=tmasd-dispatch' ) ) . '" class="button button-primary">';
		echo esc_html__( 'Go to Dispatch Rules', 'signals-dispatch-for-woocommerce' );
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

		// Collect submitted values.
		$phone_id     = $this->get_post_param( \TMASD_OPTION_PHONE_NUMBER_ID );
		$waba_id      = $this->get_post_param( \TMASD_OPTION_WABA_ID );
		$verify_token = $this->get_post_param( \TMASD_OPTION_WEBHOOK_VERIFY_TOKEN );
		$new_token    = $this->get_post_param( \TMASD_OPTION_ACCESS_TOKEN );
		$new_secret   = $this->get_post_param( \TMASD_OPTION_APP_SECRET );

		// Required field validation.
		// Secrets count as satisfied when a value is already stored.
		$token_ok  = '' !== $new_token || '' !== get_option( \TMASD_OPTION_ACCESS_TOKEN, '' );
		$secret_ok = '' !== $new_secret || '' !== get_option( \TMASD_OPTION_APP_SECRET, '' );

		$missing = array();
		if ( '' === $phone_id ) {
			$missing[] = \TMASD_OPTION_PHONE_NUMBER_ID; }
		if ( '' === $waba_id ) {
			$missing[] = \TMASD_OPTION_WABA_ID; }
		if ( ! $token_ok ) {
			$missing[] = \TMASD_OPTION_ACCESS_TOKEN; }
		if ( '' === $verify_token ) {
			$missing[] = \TMASD_OPTION_WEBHOOK_VERIFY_TOKEN; }
		if ( ! $secret_ok ) {
			$missing[] = \TMASD_OPTION_APP_SECRET; }

		if ( ! empty( $missing ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'           => 'tmasd-setup',
						'missing_fields' => implode( ',', $missing ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// Non-secret fields: always overwrite, autoload allowed.
		update_option( \TMASD_OPTION_PHONE_NUMBER_ID, $phone_id );
		update_option( \TMASD_OPTION_WABA_ID, $waba_id );
		update_option( \TMASD_OPTION_WEBHOOK_VERIFY_TOKEN, $verify_token );

		// Consent enforcement toggle.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		update_option( \TMASD_OPTION_REQUIRE_CONSENT, isset( $_POST[ \TMASD_OPTION_REQUIRE_CONSENT ] ) ? 1 : 0 );

		// Secret fields: only overwrite when the admin provides a new value;
		// store with autoload disabled to avoid leaking into the page cache.
		if ( '' !== $new_token ) {
			update_option( \TMASD_OPTION_ACCESS_TOKEN, $new_token, false );
		}
		if ( '' !== $new_secret ) {
			update_option( \TMASD_OPTION_APP_SECRET, $new_secret, false );
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

		// Validate template name format.
		if ( ! preg_match( '/^[a-z0-9_\-]{1,512}$/i', $template ) ) {
			$this->redirect_with_status( 'tmasd-setup&tab=test', 'test_error' );
			return;
		}

		// Validate language code format.
		if ( ! preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?$/', $language ) ) {
			$this->redirect_with_status( 'tmasd-setup&tab=test', 'test_error' );
			return;
		}

		// Validate vars: must be a JSON array of scalar string values only.
		$vars = json_decode( $vars_raw, true );
		if ( ! is_array( $vars ) ) {
			$vars = array();
		}
		$vars = array_values(
			array_filter(
				$vars,
				static function ( $v ): bool {
					return is_string( $v ) || is_numeric( $v );
				}
			)
		);
		$vars = array_map( 'strval', $vars );

		$result = $this->api_client->send_template_message( $phone, $template, $language, $vars );

		$this->log_test_message( $phone, $template, $result );

		if ( ! empty( $result['success'] ) ) {
			$this->redirect_with_status( 'tmasd-setup&tab=test', 'test_success' );
		} else {
			$this->redirect_with_status( 'tmasd-setup&tab=test', 'test_error' );
		}
	}

	/**
	 * Log a test message result.
	 *
	 * @param string               $phone    Phone number.
	 * @param string               $template Template name.
	 * @param array<string, mixed> $result   API result.
	 * @return void
	 */
	private function log_test_message( string $phone, string $template, array $result ): void {
		$success    = ! empty( $result['success'] );
		$response   = $result['response'] ?? array();
		$message_id = null;
		$error_code = null;
		$error_msg  = null;

		if ( $success && ! empty( $response['messages'][0]['id'] ) ) {
			$message_id = (string) $response['messages'][0]['id'];
		}

		if ( ! $success ) {
			$error_code = (string) ( $response['error']['code'] ?? '' );
			$error_msg  = $result['error'] ?? ( $response['error']['message'] ?? 'Unknown error' );
		}

		$this->log_repo->insert(
			array(
				'order_id'      => 0,
				'phone_e164'    => $phone,
				'template_name' => $template,
				'payload_json'  => wp_json_encode( $result['payload'] ?? array() ),
				'response_json' => wp_json_encode( $response ),
				'status'        => $success ? 'sent' : 'failed',
				'wa_message_id' => $message_id,
				'error_code'    => $error_code,
				'error_message' => $error_msg,
			)
		);
	}
}
