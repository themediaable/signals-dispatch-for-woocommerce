<?php
/**
 * Setup page controller.
 *
 * @package TMASD\Signals\Dispatch\Admin
 * @since 1.0.0
 */

declare(strict_types=1);

namespace TMASD\Signals\Dispatch\Admin;

use TMASD\Signals\Dispatch\Contracts\ApiClientInterface;
use TMASD\Signals\Dispatch\Database\LogRepository;
use TMASD\Signals\Dispatch\Services\MetaConnectionTesterService;
use TMASD\Signals\Dispatch\Services\SetupChecklistService;

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
 * @since 1.0.0
 */
final class SetupController extends AbstractAdminController {

	/**
	 * Page slug.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected string $page_slug = 'tmasd-setup';

	/**
	 * API client service.
	 *
	 * @var ApiClientInterface
	 * @since 1.0.0
	 */
	private ApiClientInterface $api_client;

	/**
	 * Log repository.
	 *
	 * @var LogRepository
	 * @since 1.0.0
	 */
	private LogRepository $log_repo;

	/**
	 * Setup checklist service.
	 *
	 * @var SetupChecklistService
	 * @since 1.0.0
	 */
	private SetupChecklistService $checklist_service;

	/**
	 * Meta connection tester service.
	 *
	 * @var MetaConnectionTesterService
	 * @since 1.0.0
	 */
	private MetaConnectionTesterService $meta_tester;

	/**
	 * Constructor.
	 *
	 * @param ApiClientInterface          $api_client        API client service.
	 * @param LogRepository               $log_repo          Log repository.
	 * @param SetupChecklistService       $checklist_service Setup checklist service.
	 * @param MetaConnectionTesterService $meta_tester       Meta connection tester.
	 * @since 1.0.0
	 */
	public function __construct(
		ApiClientInterface $api_client,
		LogRepository $log_repo,
		SetupChecklistService $checklist_service,
		MetaConnectionTesterService $meta_tester
	) {
		$this->api_client        = $api_client;
		$this->log_repo          = $log_repo;
		$this->checklist_service = $checklist_service;
		$this->meta_tester       = $meta_tester;
	}

	/**
	 * Render the setup page.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render(): void {
		$this->assert_access();
		$active_tab = $this->get_query_param( 'tab', 'credentials' );

		$this->render_notices();
		$this->render_page_header();
		$this->render_checklist();
		$this->render_tabs( $active_tab );
		$this->render_tab_content( $active_tab );

		echo '</div>';
	}

	/**
	 * Render page header.
	 *
	 * @return void
	 * @since 1.0.0
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
	 * @since 1.0.0
	 */
	private function render_tabs( string $active_tab ): void {
		$tabs = array(
			'welcome'         => __( '1. Welcome', 'signals-dispatch-for-woocommerce' ),
			'meta_app'        => __( '2. Meta App', 'signals-dispatch-for-woocommerce' ),
			'whatsapp_number' => __( '3. WhatsApp Number', 'signals-dispatch-for-woocommerce' ),
			'credentials'     => __( '4. API Credentials', 'signals-dispatch-for-woocommerce' ),
			'webhook'         => __( '5. Webhook', 'signals-dispatch-for-woocommerce' ),
			'templates'       => __( '6. Templates', 'signals-dispatch-for-woocommerce' ),
			'test'            => __( '7. Test Send', 'signals-dispatch-for-woocommerce' ),
			'finish'          => __( '8. Finish', 'signals-dispatch-for-woocommerce' ),
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
	 * @since 1.0.0
	 */
	private function render_tab_content( string $tab ): void {
		switch ( $tab ) {
			case 'welcome':
				$this->render_welcome_panel();
				break;
			case 'meta_app':
				$this->render_meta_app_panel();
				break;
			case 'whatsapp_number':
				$this->render_whatsapp_number_panel();
				break;
			case 'credentials':
				$this->render_credentials_form();
				break;
			case 'webhook':
				$this->render_webhook_info();
				break;
			case 'templates':
				$this->render_templates_panel();
				break;
			case 'test':
				$this->render_test_form();
				break;
			default:
				$this->render_finish_panel();
				break;
		}
	}

	/**
	 * Render notices.
	 *
	 * @return void
	 * @since 1.0.0
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
			$this->render_notice_error( __( 'Test message failed. Check logs for details.', 'signals-dispatch-for-woocommerce' ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		if ( isset( $_GET['connection_ok'] ) ) {
			$this->render_notice_success( __( 'API connection successful. Meta accepted your credentials.', 'signals-dispatch-for-woocommerce' ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		if ( isset( $_GET['connection_error'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
			$slug = sanitize_key( wp_unslash( $_GET['connection_error'] ) );
			$msg  = $this->get_connection_error_message( $slug );
			$this->render_notice_error( $msg );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		if ( isset( $_GET['meta_app_saved'] ) ) {
			$this->render_notice_success( __( 'Meta app confirmation saved.', 'signals-dispatch-for-woocommerce' ) );
		}
	}

	/**
	 * Return a map of option-key → human-readable label (used in error messages).
	 *
	 * @return array<string, string>
	 * @since 1.0.0
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
	 * @since 1.0.0
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

		$display_phone = get_option( \TMASD_OPTION_DISPLAY_PHONE_NUMBER, '' );

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'tmasd_save_setup' );
		echo '<input type="hidden" name="action" value="tmasd_save_setup" />';

		echo '<table class="form-table">';

		$this->render_text_field(
			\TMASD_OPTION_PHONE_NUMBER_ID,
			__( 'Phone Number ID', 'signals-dispatch-for-woocommerce' ),
			$phone_id,
			__( 'The numeric ID of your WhatsApp sender phone number. Found in Meta Business Manager under WhatsApp → Phone Numbers.', 'signals-dispatch-for-woocommerce' ),
			true
		);

		$this->render_text_field(
			\TMASD_OPTION_DISPLAY_PHONE_NUMBER,
			__( 'Display Phone Number', 'signals-dispatch-for-woocommerce' ),
			$display_phone,
			__( 'Optional. The human-readable phone number for your own reference (e.g. +91 98765 43210). Not used for sending.', 'signals-dispatch-for-woocommerce' ),
			false
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

		$this->render_connection_test_panel();
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
	 * @since 1.0.0
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
	 * Render a secret field row that never exposes the stored value.
	 *
	 * @param string $name        Field name / option key.
	 * @param string $label       Field label.
	 * @param bool   $has_value   Whether a value is already saved.
	 * @param string $description Optional helper text shown below the input.
	 * @param bool   $required    Whether the field is required.
	 * @return void
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * Render webhook guidance panel.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function render_webhook_info(): void {
		$webhook_url      = rest_url( 'tmasignals/v1/webhook' );
		$has_verify_token = '' !== get_option( \TMASD_OPTION_WEBHOOK_VERIFY_TOKEN, '' );
		$has_app_secret   = '' !== get_option( \TMASD_OPTION_APP_SECRET, '' );
		$last_received    = get_option( \TMASD_OPTION_LAST_WEBHOOK_RECEIVED_AT, '' );
		$last_status_upd  = get_option( \TMASD_OPTION_LAST_WEBHOOK_STATUS_UPDATE_AT, '' );

		echo '<div class="tmasd-card">';
		echo '<h2>' . esc_html__( 'Webhook Setup', 'signals-dispatch-for-woocommerce' ) . '</h2>';

		echo '<p>' . esc_html__( 'Follow these steps in the Meta Developer Console to connect webhook callbacks:', 'signals-dispatch-for-woocommerce' ) . '</p>';
		echo '<ol>';
		echo '<li>' . esc_html__( 'Open your Meta app and go to WhatsApp → Configuration.', 'signals-dispatch-for-woocommerce' ) . '</li>';
		echo '<li>' . esc_html__( 'Paste the Callback URL below into the Callback URL field.', 'signals-dispatch-for-woocommerce' ) . '</li>';
		echo '<li>' . esc_html__( 'Paste the Verify Token (from Step 4 → API Credentials) into the Verify Token field.', 'signals-dispatch-for-woocommerce' ) . '</li>';
		echo '<li>' . esc_html__( 'Click Verify and Save. Subscribe to messages and message_status webhooks.', 'signals-dispatch-for-woocommerce' ) . '</li>';
		echo '</ol>';

		echo '<p><strong>' . esc_html__( 'Callback URL', 'signals-dispatch-for-woocommerce' ) . '</strong></p>';
		echo '<div class="tmasd-copy-row">';
		echo '<code id="tmasd-webhook-url" class="tmasd-webhook-url">' . esc_url( $webhook_url ) . '</code>';
		echo '<button type="button" class="button tmasd-copy-btn" data-target="tmasd-webhook-url">';
		echo esc_html__( 'Copy URL', 'signals-dispatch-for-woocommerce' );
		echo '</button>';
		echo '</div>';

		echo '<table class="form-table" style="margin-top:1em">';
		echo '<tr><th>' . esc_html__( 'Verify token configured', 'signals-dispatch-for-woocommerce' ) . '</th>';
		echo '<td>' . ( $has_verify_token ? '<span class="tmasd-status-ok">&#10003; ' . esc_html__( 'Yes', 'signals-dispatch-for-woocommerce' ) . '</span>' : '<span class="tmasd-status-error">&#10007; ' . esc_html__( 'No — add it in Step 4', 'signals-dispatch-for-woocommerce' ) . '</span>' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- status spans built from esc_html.
		echo '<tr><th>' . esc_html__( 'App secret configured', 'signals-dispatch-for-woocommerce' ) . '</th>';
		echo '<td>' . ( $has_app_secret ? '<span class="tmasd-status-ok">&#10003; ' . esc_html__( 'Yes', 'signals-dispatch-for-woocommerce' ) . '</span>' : '<span class="tmasd-status-error">&#10007; ' . esc_html__( 'No — add it in Step 4', 'signals-dispatch-for-woocommerce' ) . '</span>' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- status spans built from esc_html.
		echo '<tr><th>' . esc_html__( 'Last webhook received', 'signals-dispatch-for-woocommerce' ) . '</th>';
		echo '<td>' . ( '' !== $last_received ? esc_html( $last_received ) : esc_html__( 'Never', 'signals-dispatch-for-woocommerce' ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Last status update', 'signals-dispatch-for-woocommerce' ) . '</th>';
		echo '<td>' . ( '' !== $last_status_upd ? esc_html( $last_status_upd ) : esc_html__( 'Never', 'signals-dispatch-for-woocommerce' ) ) . '</td></tr>';
		echo '</table>';

		echo '</div>';
	}

	/**
	 * Render test message form.
	 *
	 * @return void
	 * @since 1.0.0
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
	 * Render finish panel.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function render_finish_panel(): void {
		$checklist = $this->checklist_service->get_checklist();
		$total     = count( $checklist );
		$complete  = $this->checklist_service->count_complete();
		$all_done  = $complete === $total;

		echo '<div class="tmasd-card">';
		if ( $all_done ) {
			echo '<h2>&#10003; ' . esc_html__( 'Setup Complete', 'signals-dispatch-for-woocommerce' ) . '</h2>';
			echo '<p>' . esc_html__( 'All checks passed. Your plugin is ready to send WhatsApp order notifications.', 'signals-dispatch-for-woocommerce' ) . '</p>';
		} else {
			echo '<h2>' . esc_html__( 'Almost there', 'signals-dispatch-for-woocommerce' ) . '</h2>';
			echo '<p>';
			printf(
				/* translators: 1: completed count 2: total count */
				esc_html__( '%1$d of %2$d setup items are complete. Finish the remaining steps before enabling live notifications.', 'signals-dispatch-for-woocommerce' ),
				(int) $complete,
				(int) $total
			);
			echo '</p>';
		}

		$dispatch_url = admin_url( 'admin.php?page=tmasd-dispatch' );
		$logs_url     = admin_url( 'admin.php?page=tmasd-logs' );
		$health_url   = admin_url( 'admin.php?page=tmasd-health' );
		$test_url     = admin_url( 'admin.php?page=tmasd-setup&tab=test' );

		echo '<p>';
		echo '<a href="' . esc_url( $dispatch_url ) . '" class="button button-primary">' . esc_html__( 'Go to Dispatch Rules', 'signals-dispatch-for-woocommerce' ) . '</a> ';
		echo '<a href="' . esc_url( $logs_url ) . '" class="button">' . esc_html__( 'View Logs', 'signals-dispatch-for-woocommerce' ) . '</a> ';
		echo '<a href="' . esc_url( $health_url ) . '" class="button">' . esc_html__( 'Open Health Check', 'signals-dispatch-for-woocommerce' ) . '</a> ';
		echo '<a href="' . esc_url( $test_url ) . '" class="button">' . esc_html__( 'Send another test message', 'signals-dispatch-for-woocommerce' ) . '</a>';
		echo '</p>';
		echo '</div>';
	}

	/**
	 * Handle save setup form submission.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function handle_save(): void {
		$this->assert_access();

		// Accept either full-form nonce or the meta_app_only nonce.
		$meta_app_only = ! empty( $_POST['tmasd_save_meta_app_only'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked below.
		if ( $meta_app_only ) {
			$this->verify_nonce( 'tmasd_save_meta_app_confirmation' );
			update_option( \TMASD_OPTION_SETUP_META_APP_CONFIRMED, 1 );
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'           => 'tmasd-setup',
						'tab'            => 'whatsapp_number',
						'meta_app_saved' => '1',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

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
		update_option( \TMASD_OPTION_DISPLAY_PHONE_NUMBER, $this->get_post_param( \TMASD_OPTION_DISPLAY_PHONE_NUMBER ) );

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
	 * @since 1.0.0
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
	 * @since 1.0.0
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

	/**
	 * Handle API connection test form submission.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function handle_test_connection(): void {
		$this->assert_access();
		$this->verify_nonce( 'tmasd_test_connection' );

		$result = $this->meta_tester->test_connection();

		if ( ! empty( $result['success'] ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'          => 'tmasd-setup',
						'tab'           => 'credentials',
						'connection_ok' => '1',
					),
					admin_url( 'admin.php' )
				)
			);
		} else {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'             => 'tmasd-setup',
						'tab'              => 'credentials',
						'connection_error' => rawurlencode( $result['slug'] ?? 'unknown' ),
					),
					admin_url( 'admin.php' )
				)
			);
		}
		exit;
	}

	/**
	 * Render the setup checklist panel.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function render_checklist(): void {
		$items    = $this->checklist_service->get_checklist();
		$complete = $this->checklist_service->count_complete();
		$total    = count( $items );

		echo '<div class="tmasd-checklist-panel">';
		echo '<strong>' . esc_html__( 'Setup Progress', 'signals-dispatch-for-woocommerce' ) . '</strong>';
		echo ' <span class="tmasd-checklist-count">';
		printf(
			/* translators: 1: completed items 2: total items */
			esc_html__( '%1$d / %2$d complete', 'signals-dispatch-for-woocommerce' ),
			(int) $complete,
			(int) $total
		);
		echo '</span>';
		echo '<ul class="tmasd-checklist">';

		foreach ( $items as $item ) {
			$is_done = 'complete' === $item['status'];
			$icon    = $is_done ? '&#10003;' : '&#9675;';
			$class   = $is_done ? 'tmasd-checklist-item tmasd-checklist-item--done' : 'tmasd-checklist-item';
			echo '<li class="' . esc_attr( $class ) . '">';
			echo '<span aria-hidden="true">' . $icon . '</span> '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML entity.
			echo esc_html( $item['label'] );
			echo '</li>';
		}

		echo '</ul>';
		echo '</div>';
	}

	/**
	 * Render the welcome panel (Step 1).
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function render_welcome_panel(): void {
		echo '<div class="tmasd-card">';
		echo '<h2>' . esc_html__( 'Welcome to Signals Dispatch', 'signals-dispatch-for-woocommerce' ) . '</h2>';
		echo '<p>' . esc_html__( 'Signals sends WooCommerce order updates using your own WhatsApp Cloud API account. This wizard will help you collect the required values from Meta and test your setup.', 'signals-dispatch-for-woocommerce' ) . '</p>';

		echo '<h3>' . esc_html__( 'Before you start, you will need:', 'signals-dispatch-for-woocommerce' ) . '</h3>';
		echo '<ul>';
		echo '<li>' . esc_html__( 'A Meta Business account.', 'signals-dispatch-for-woocommerce' ) . '</li>';
		echo '<li>' . esc_html__( 'A WhatsApp Business Platform / Cloud API setup.', 'signals-dispatch-for-woocommerce' ) . '</li>';
		echo '<li>' . esc_html__( 'A phone number connected to WhatsApp Cloud API.', 'signals-dispatch-for-woocommerce' ) . '</li>';
		echo '<li>' . esc_html__( 'At least one approved Utility message template.', 'signals-dispatch-for-woocommerce' ) . '</li>';
		echo '<li>' . esc_html__( 'Admin access to this WordPress site.', 'signals-dispatch-for-woocommerce' ) . '</li>';
		echo '</ul>';

		$next_url = admin_url( 'admin.php?page=tmasd-setup&tab=meta_app' );
		echo '<p><a href="' . esc_url( $next_url ) . '" class="button button-primary">' . esc_html__( 'Start setup →', 'signals-dispatch-for-woocommerce' ) . '</a></p>';
		echo '</div>';
	}

	/**
	 * Render the Meta App guidance panel (Step 2).
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function render_meta_app_panel(): void {
		$confirmed = (bool) get_option( \TMASD_OPTION_SETUP_META_APP_CONFIRMED, false );
		$help_url  = admin_url( 'admin.php?page=tmasd-help&tab=setup' );

		echo '<div class="tmasd-card">';
		echo '<h2>' . esc_html__( 'Step 2: Meta App', 'signals-dispatch-for-woocommerce' ) . '</h2>';
		echo '<p>' . esc_html__( 'Signals sends messages through your own Meta Developer App connected to the WhatsApp Business Platform.', 'signals-dispatch-for-woocommerce' ) . '</p>';

		echo '<ol>';
		echo '<li>';
		printf(
			wp_kses(
				/* translators: %s: URL to Meta developer console */
				__( 'Go to the <a href="%s" target="_blank" rel="noopener noreferrer">Meta Developer Console</a> and create or select an app.', 'signals-dispatch-for-woocommerce' ),
				array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
					),
				)
			),
			'https://developers.facebook.com/apps/'
		);
		echo '</li>';
		echo '<li>' . esc_html__( 'Add the WhatsApp product to the app.', 'signals-dispatch-for-woocommerce' ) . '</li>';
		echo '<li>' . esc_html__( 'Make sure the app is connected to the correct Meta Business account.', 'signals-dispatch-for-woocommerce' ) . '</li>';
		echo '<li>';
		printf(
			wp_kses(
				/* translators: %s: URL to help page */
				__( 'Need more detail? <a href="%s">Read the setup guide →</a>', 'signals-dispatch-for-woocommerce' ),
				array( 'a' => array( 'href' => array() ) )
			),
			esc_url( $help_url )
		);
		echo '</li>';
		echo '</ol>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'tmasd_save_meta_app_confirmation' );
		echo '<input type="hidden" name="action" value="tmasd_save_setup" />';
		echo '<input type="hidden" name="tmasd_save_meta_app_only" value="1" />';

		echo '<p>';
		echo '<label>';
		echo '<input type="checkbox" name="' . esc_attr( \TMASD_OPTION_SETUP_META_APP_CONFIRMED ) . '" value="1"';
		checked( $confirmed, true );
		echo ' />';
		echo ' ' . esc_html__( 'I have created or selected my Meta app and added the WhatsApp product.', 'signals-dispatch-for-woocommerce' );
		echo '</label>';
		echo '</p>';

		submit_button( __( 'Save & continue to Step 3 →', 'signals-dispatch-for-woocommerce' ) );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render the WhatsApp Number guidance panel (Step 3).
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function render_whatsapp_number_panel(): void {
		$next_url = admin_url( 'admin.php?page=tmasd-setup&tab=credentials' );

		echo '<div class="tmasd-card">';
		echo '<h2>' . esc_html__( 'Step 3: WhatsApp Number', 'signals-dispatch-for-woocommerce' ) . '</h2>';
		echo '<p>' . esc_html__( 'Each store should use its own dedicated WhatsApp Business number. The number must be connected to WhatsApp Cloud API.', 'signals-dispatch-for-woocommerce' ) . '</p>';

		echo '<h3>' . esc_html__( 'Important notes', 'signals-dispatch-for-woocommerce' ) . '</h3>';
		echo '<ul>';
		echo '<li>' . esc_html__( 'Every phone number connected to WhatsApp Cloud API has a unique Phone Number ID. This is not the phone number itself — it is a numeric ID assigned by Meta.', 'signals-dispatch-for-woocommerce' ) . '</li>';
		echo '<li>' . esc_html__( 'Do not use a personal WhatsApp number that is already active on the mobile app unless you understand Meta\'s migration limitations.', 'signals-dispatch-for-woocommerce' ) . '</li>';
		echo '<li>' . esc_html__( 'You can find the Phone Number ID in Meta Business Manager under WhatsApp → Phone Numbers.', 'signals-dispatch-for-woocommerce' ) . '</li>';
		echo '</ul>';

		echo '<p><a href="' . esc_url( $next_url ) . '" class="button button-primary">' . esc_html__( 'Continue to Step 4: API Credentials →', 'signals-dispatch-for-woocommerce' ) . '</a></p>';
		echo '</div>';
	}

	/**
	 * Render the Templates guidance panel (Step 6).
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function render_templates_panel(): void {
		$dispatch_url = admin_url( 'admin.php?page=tmasd-dispatch' );
		$help_url     = admin_url( 'admin.php?page=tmasd-help&tab=templates' );

		echo '<div class="tmasd-card">';
		echo '<h2>' . esc_html__( 'Step 6: Templates', 'signals-dispatch-for-woocommerce' ) . '</h2>';
		echo '<p>' . esc_html__( 'Outside the WhatsApp customer service window, businesses must use approved message templates. Order updates should use Utility templates.', 'signals-dispatch-for-woocommerce' ) . '</p>';

		echo '<ul>';
		echo '<li>' . esc_html__( 'Template names must match exactly — including capitalisation.', 'signals-dispatch-for-woocommerce' ) . '</li>';
		echo '<li>' . esc_html__( 'The template language code must match the approved language.', 'signals-dispatch-for-woocommerce' ) . '</li>';
		echo '<li>' . esc_html__( 'Template variables must match the placeholders in Meta in the correct order.', 'signals-dispatch-for-woocommerce' ) . '</li>';
		echo '</ul>';

		echo '<h3>' . esc_html__( 'Suggested template for order processing', 'signals-dispatch-for-woocommerce' ) . '</h3>';
		echo '<code>Hi {{1}}, your order {{2}} is now being processed. Total: {{3}} {{4}}.</code>';
		echo '<p class="description">';
		echo esc_html__( 'Variable mapping: billing_first_name, order_number, order_total, order_currency', 'signals-dispatch-for-woocommerce' );
		echo '</p>';

		echo '<p>';
		echo '<a href="' . esc_url( $dispatch_url ) . '" class="button button-primary">' . esc_html__( 'Open Dispatch Rules', 'signals-dispatch-for-woocommerce' ) . '</a> ';
		echo '<a href="' . esc_url( $help_url ) . '" class="button">' . esc_html__( 'View Template Guide', 'signals-dispatch-for-woocommerce' ) . '</a>';
		echo '</p>';
		echo '</div>';
	}

	/**
	 * Render connection test button panel (shown inside credentials tab).
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function render_connection_test_panel(): void {
		$last_test_at     = get_option( \TMASD_OPTION_LAST_API_TEST_AT, '' );
		$last_test_status = get_option( \TMASD_OPTION_LAST_API_TEST_STATUS, '' );

		echo '<div class="tmasd-card" style="margin-top:1em">';
		echo '<h3>' . esc_html__( 'Test API Connection', 'signals-dispatch-for-woocommerce' ) . '</h3>';

		if ( '' !== $last_test_at ) {
			$status_label = 'pass' === $last_test_status
				? '<span class="tmasd-status-ok">&#10003; ' . esc_html__( 'Passed', 'signals-dispatch-for-woocommerce' ) . '</span>'
				: '<span class="tmasd-status-error">&#10007; ' . esc_html__( 'Failed', 'signals-dispatch-for-woocommerce' ) . '</span>';

			echo '<p>';
			printf(
				wp_kses(
					/* translators: 1: status badge HTML span, 2: datetime string */
					__( 'Last test: %1$s &mdash; %2$s', 'signals-dispatch-for-woocommerce' ),
					array(
						'span' => array( 'class' => array() ),
					)
				),
				$status_label, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_html.
				esc_html( $last_test_at )
			);
			echo '</p>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'tmasd_test_connection' );
		echo '<input type="hidden" name="action" value="tmasd_test_connection" />';
		echo '<button type="submit" class="button">' . esc_html__( 'Test API connection', 'signals-dispatch-for-woocommerce' ) . '</button>';
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Map a connection error slug to a user-readable message.
	 *
	 * @param string $slug Error slug stored in the option.
	 * @return string Human-readable message.
	 * @since 1.0.0
	 */
	private function get_connection_error_message( string $slug ): string {
		$messages = array(
			'missing_credentials' => __( 'Phone Number ID and Access Token are required before testing the connection.', 'signals-dispatch-for-woocommerce' ),
			'network_error'       => __( 'Your site could not reach Meta\'s API. Please check server connectivity.', 'signals-dispatch-for-woocommerce' ),
			'api_error_401'       => __( 'Meta rejected this access token. Please check whether the token is expired or copied correctly.', 'signals-dispatch-for-woocommerce' ),
			'api_error_403'       => __( 'The token works, but it does not have access to this Phone Number ID, or it is missing required permissions.', 'signals-dispatch-for-woocommerce' ),
		);

		return $messages[ $slug ]
			?? __( 'Meta returned an unexpected error. Please check your credentials and try again.', 'signals-dispatch-for-woocommerce' );
	}
}
