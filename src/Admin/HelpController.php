<?php
/**
 * Help page controller.
 *
 * @package TMASD\Signals\Dispatch\Admin
 */

declare(strict_types=1);

namespace TMASD\Signals\Dispatch\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Help page controller.
 *
 * Displays WhatsApp API setup guide and FAQs.
 * Single Responsibility: Help page rendering only.
 *
 * @final
 */
final class HelpController extends AbstractAdminController {

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	protected string $page_slug = 'tmasd-help';

	/**
	 * Render the help page.
	 *
	 * @return void
	 */
	public function render(): void {
		$this->assert_access();

		$this->render_page_header();
		$this->render_tabs();
		$this->render_tab_content();

		echo '</div>';
	}

	/**
	 * Get current active tab.
	 *
	 * @return string
	 */
	private function get_current_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab navigation only.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'setup';

		$valid_tabs = array( 'setup', 'templates', 'faq', 'troubleshooting' );

		return in_array( $tab, $valid_tabs, true ) ? $tab : 'setup';
	}

	/**
	 * Render page header.
	 *
	 * @return void
	 */
	private function render_page_header(): void {
		echo '<div class="wrap tmasd-admin">';
		echo '<h1 class="wp-heading-inline">';
		echo esc_html__( 'Help & Documentation', 'signals-dispatch-woocommerce' );
		echo '</h1>';
		echo '<hr class="wp-header-end" />';
	}

	/**
	 * Render navigation tabs.
	 *
	 * @return void
	 */
	private function render_tabs(): void {
		$current_tab = $this->get_current_tab();

		$tabs = array(
			'setup'           => __( 'API Setup', 'signals-dispatch-woocommerce' ),
			'templates'       => __( 'Message Templates', 'signals-dispatch-woocommerce' ),
			'faq'             => __( 'FAQ', 'signals-dispatch-woocommerce' ),
			'troubleshooting' => __( 'Troubleshooting', 'signals-dispatch-woocommerce' ),
		);

		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $tab_id => $tab_label ) {
			$active_class = ( $current_tab === $tab_id ) ? ' nav-tab-active' : '';
			$url          = admin_url( 'admin.php?page=tmasd-help&tab=' . $tab_id );
			printf(
				'<a href="%s" class="nav-tab%s">%s</a>',
				esc_url( $url ),
				esc_attr( $active_class ),
				esc_html( $tab_label )
			);
		}
		echo '</nav>';
	}

	/**
	 * Render tab content.
	 *
	 * @return void
	 */
	private function render_tab_content(): void {
		$current_tab = $this->get_current_tab();

		echo '<div class="tmasd-tab-content">';

		switch ( $current_tab ) {
			case 'templates':
				$this->render_templates_tab();
				break;
			case 'faq':
				$this->render_faq_tab();
				break;
			case 'troubleshooting':
				$this->render_troubleshooting_tab();
				break;
			default:
				$this->render_setup_tab();
				break;
		}

		echo '</div>';
	}

	/**
	 * Render setup tab content.
	 *
	 * @return void
	 */
	private function render_setup_tab(): void {
		?>
		<div class="card">
			<h2><?php esc_html_e( 'WhatsApp Business API Setup', 'signals-dispatch-woocommerce' ); ?></h2>
			
			<h3><?php esc_html_e( 'Step 1: Create a Meta Developer Account', 'signals-dispatch-woocommerce' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Go to developers.facebook.com', 'signals-dispatch-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Click "Get Started" and log in with your Facebook account', 'signals-dispatch-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Complete the developer registration process', 'signals-dispatch-woocommerce' ); ?></li>
			</ol>

			<h3><?php esc_html_e( 'Step 2: Create a Meta App', 'signals-dispatch-woocommerce' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Go to developers.facebook.com/apps', 'signals-dispatch-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Click "Create App"', 'signals-dispatch-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Select "Other" for use case, then click "Next"', 'signals-dispatch-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Select "Business" as the app type', 'signals-dispatch-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Enter your app name (e.g., "My Store Notifications")', 'signals-dispatch-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Select or create a Business Portfolio', 'signals-dispatch-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Click "Create App"', 'signals-dispatch-woocommerce' ); ?></li>
			</ol>

			<h3><?php esc_html_e( 'Step 3: Add WhatsApp Product', 'signals-dispatch-woocommerce' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'In your app dashboard, find "WhatsApp" in the products list', 'signals-dispatch-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Click "Set Up"', 'signals-dispatch-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'This will add WhatsApp to your app and open the API Setup page', 'signals-dispatch-woocommerce' ); ?></li>
			</ol>

			<h3><?php esc_html_e( 'Step 4: Get Your Credentials', 'signals-dispatch-woocommerce' ); ?></h3>
			<p><?php esc_html_e( 'On the WhatsApp → API Setup page, you will find:', 'signals-dispatch-woocommerce' ); ?></p>
			
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Credential', 'signals-dispatch-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Where to Find', 'signals-dispatch-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'Phone Number ID', 'signals-dispatch-woocommerce' ); ?></strong></td>
						<td><?php esc_html_e( 'Under "From" phone number dropdown - a numeric ID like 1234567890123456', 'signals-dispatch-woocommerce' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'WhatsApp Business Account ID', 'signals-dispatch-woocommerce' ); ?></strong></td>
						<td><?php esc_html_e( 'Shown as "WhatsApp Business Account ID" on the API Setup page', 'signals-dispatch-woocommerce' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Access Token', 'signals-dispatch-woocommerce' ); ?></strong></td>
						<td><?php esc_html_e( 'Click "Generate" for a temporary token (24 hours), or create a permanent one via System User', 'signals-dispatch-woocommerce' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Webhook Verify Token', 'signals-dispatch-woocommerce' ); ?></strong></td>
						<td><?php esc_html_e( 'Create your own secret string (e.g., my_store_webhook_secret_2026)', 'signals-dispatch-woocommerce' ); ?></td>
					</tr>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Step 5: Create a Permanent Access Token', 'signals-dispatch-woocommerce' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Go to business.facebook.com/settings', 'signals-dispatch-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Navigate to Users → System Users', 'signals-dispatch-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Click "Add" to create a new system user', 'signals-dispatch-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Set role to "Admin"', 'signals-dispatch-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Click "Add Assets" → Select your WhatsApp Business Account → Enable full control', 'signals-dispatch-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Click "Generate New Token"', 'signals-dispatch-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Select your app and add permissions: whatsapp_business_messaging, whatsapp_business_management', 'signals-dispatch-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Click "Generate Token" and save it securely', 'signals-dispatch-woocommerce' ); ?></li>
			</ol>

			<h3><?php esc_html_e( 'Step 6: Configure Webhook', 'signals-dispatch-woocommerce' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'In your Meta App, go to WhatsApp → Configuration', 'signals-dispatch-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Click "Edit" on the Webhook section', 'signals-dispatch-woocommerce' ); ?></li>
				<li>
					<?php esc_html_e( 'Enter your callback URL:', 'signals-dispatch-woocommerce' ); ?>
					<code><?php echo esc_html( home_url( '/wp-json/signals/v1/webhook' ) ); ?></code>
				</li>
				<li><?php esc_html_e( 'Enter the Verify Token you configured in this plugin', 'signals-dispatch-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Click "Verify and Save"', 'signals-dispatch-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Subscribe to webhook field: messages', 'signals-dispatch-woocommerce' ); ?></li>
			</ol>

			<div class="notice notice-info inline">
				<p>
					<strong><?php esc_html_e( 'Tip:', 'signals-dispatch-woocommerce' ); ?></strong>
					<?php esc_html_e( 'For local development, use a tunneling service like ngrok to expose your local WordPress to the internet.', 'signals-dispatch-woocommerce' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render templates tab content.
	 *
	 * @return void
	 */
	private function render_templates_tab(): void {
		?>
		<div class="card">
			<h2><?php esc_html_e( 'Creating WhatsApp Message Templates', 'signals-dispatch-woocommerce' ); ?></h2>
			
			<p><?php esc_html_e( 'WhatsApp requires pre-approved templates for business-initiated messages. Here\'s how to create them:', 'signals-dispatch-woocommerce' ); ?></p>

			<h3><?php esc_html_e( 'Creating a Template', 'signals-dispatch-woocommerce' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Go to WhatsApp → Message Templates in your Meta App', 'signals-dispatch-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Click "Create Template"', 'signals-dispatch-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Select "Utility" category (for order notifications)', 'signals-dispatch-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Name your template (e.g., order_confirmation)', 'signals-dispatch-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Add your message with variables', 'signals-dispatch-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Submit for approval (usually takes minutes to hours)', 'signals-dispatch-woocommerce' ); ?></li>
			</ol>

			<h3><?php esc_html_e( 'Template Example', 'signals-dispatch-woocommerce' ); ?></h3>
			<div class="tmasd-code-block">
				<?php esc_html_e( 'Hello {{1}}, your order #{{2}} for {{3}} has been confirmed!', 'signals-dispatch-woocommerce' ); ?>
			</div>

			<h3><?php esc_html_e( 'Variable Mapping', 'signals-dispatch-woocommerce' ); ?></h3>
			<p><?php esc_html_e( 'In Dispatch Rules, map variables using a JSON array:', 'signals-dispatch-woocommerce' ); ?></p>
			<div class="tmasd-code-block">
				["billing_first_name", "order_number", "order_total"]
			</div>
			<p>
				<?php esc_html_e( 'This maps: {{1}} → Customer first name, {{2}} → Order number, {{3}} → Order total', 'signals-dispatch-woocommerce' ); ?>
			</p>

			<h3><?php esc_html_e( 'Available Variables', 'signals-dispatch-woocommerce' ); ?></h3>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Variable', 'signals-dispatch-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Description', 'signals-dispatch-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr><td><code>order_id</code></td><td><?php esc_html_e( 'Internal order ID', 'signals-dispatch-woocommerce' ); ?></td></tr>
					<tr><td><code>order_number</code></td><td><?php esc_html_e( 'Display order number', 'signals-dispatch-woocommerce' ); ?></td></tr>
					<tr><td><code>order_total</code></td><td><?php esc_html_e( 'Order total amount', 'signals-dispatch-woocommerce' ); ?></td></tr>
					<tr><td><code>order_currency</code></td><td><?php esc_html_e( 'Currency code (e.g., USD)', 'signals-dispatch-woocommerce' ); ?></td></tr>
					<tr><td><code>billing_first_name</code></td><td><?php esc_html_e( 'Customer first name', 'signals-dispatch-woocommerce' ); ?></td></tr>
					<tr><td><code>billing_last_name</code></td><td><?php esc_html_e( 'Customer last name', 'signals-dispatch-woocommerce' ); ?></td></tr>
					<tr><td><code>billing_phone</code></td><td><?php esc_html_e( 'Customer phone number', 'signals-dispatch-woocommerce' ); ?></td></tr>
					<tr><td><code>billing_email</code></td><td><?php esc_html_e( 'Customer email', 'signals-dispatch-woocommerce' ); ?></td></tr>
					<tr><td><code>shipping_first_name</code></td><td><?php esc_html_e( 'Shipping first name', 'signals-dispatch-woocommerce' ); ?></td></tr>
					<tr><td><code>shipping_last_name</code></td><td><?php esc_html_e( 'Shipping last name', 'signals-dispatch-woocommerce' ); ?></td></tr>
					<tr><td><code>status</code></td><td><?php esc_html_e( 'Current order status', 'signals-dispatch-woocommerce' ); ?></td></tr>
					<tr><td><code>site_name</code></td><td><?php esc_html_e( 'WordPress site name', 'signals-dispatch-woocommerce' ); ?></td></tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render FAQ tab content.
	 *
	 * @return void
	 */
	private function render_faq_tab(): void {
		$faqs = array(
			array(
				'question' => __( 'What WhatsApp templates can I use?', 'signals-dispatch-woocommerce' ),
				'answer'   => __( 'You can use any approved WhatsApp message templates from your WhatsApp Business Account. The plugin supports utility templates with dynamic variable substitution.', 'signals-dispatch-woocommerce' ),
			),
			array(
				'question' => __( 'How do I get a permanent access token?', 'signals-dispatch-woocommerce' ),
				'answer'   => __( 'Go to Business Settings → System Users → Create a new system user → Add WhatsApp Business Account assets → Generate Token with whatsapp_business_messaging and whatsapp_business_management permissions.', 'signals-dispatch-woocommerce' ),
			),
			array(
				'question' => __( 'Can customers opt out of WhatsApp messages?', 'signals-dispatch-woocommerce' ),
				'answer'   => __( 'Yes, the plugin tracks customer opt-in/opt-out preferences and respects them when sending messages.', 'signals-dispatch-woocommerce' ),
			),
			array(
				'question' => __( 'How many test recipients can I add?', 'signals-dispatch-woocommerce' ),
				'answer'   => __( 'Before your app is approved for production, you can add up to 5 test phone numbers in the Meta Developer Console.', 'signals-dispatch-woocommerce' ),
			),
			array(
				'question' => __( 'What is the webhook used for?', 'signals-dispatch-woocommerce' ),
				'answer'   => __( 'The webhook receives delivery status updates from WhatsApp (sent, delivered, read, failed) and updates the message logs automatically.', 'signals-dispatch-woocommerce' ),
			),
			array(
				'question' => __( 'Is there a message limit?', 'signals-dispatch-woocommerce' ),
				'answer'   => __( 'Yes, WhatsApp has rate limits based on your account tier. New accounts start at 250 business-initiated messages per 24 hours. This increases as your quality rating improves.', 'signals-dispatch-woocommerce' ),
			),
			array(
				'question' => __( 'What happens if a message fails?', 'signals-dispatch-woocommerce' ),
				'answer'   => __( 'Failed messages are automatically retried up to 3 times using WooCommerce Action Scheduler. You can view failed messages and their error details in the Logs page.', 'signals-dispatch-woocommerce' ),
			),
			array(
				'question' => __( 'Do I need WooCommerce installed?', 'signals-dispatch-woocommerce' ),
				'answer'   => __( 'Yes, WooCommerce 7.0 or higher is required. The plugin uses WooCommerce order data and Action Scheduler for message queuing.', 'signals-dispatch-woocommerce' ),
			),
		);

		echo '<div class="card">';
		echo '<h2>' . esc_html__( 'Frequently Asked Questions', 'signals-dispatch-woocommerce' ) . '</h2>';

		foreach ( $faqs as $index => $faq ) {
			$this->render_faq_item( $faq['question'], $faq['answer'], $index );
		}

		echo '</div>';
	}

	/**
	 * Render a single FAQ item.
	 *
	 * @param string $question Question text.
	 * @param string $answer   Answer text.
	 * @param int    $index    Item index.
	 * @return void
	 */
	private function render_faq_item( string $question, string $answer, int $index ): void {
		$faq_id = 'faq-' . $index;
		?>
		<div class="tmasd-faq-item">
			<h3 class="tmasd-faq-header" onclick="document.getElementById('<?php echo esc_attr( $faq_id ); ?>').style.display = document.getElementById('<?php echo esc_attr( $faq_id ); ?>').style.display === 'none' ? 'block' : 'none';">
				<span class="dashicons dashicons-arrow-right-alt2"></span>
				<?php echo esc_html( $question ); ?>
			</h3>
			<div id="<?php echo esc_attr( $faq_id ); ?>" class="tmasd-faq-answer">
				<p><?php echo esc_html( $answer ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render troubleshooting tab content.
	 *
	 * @return void
	 */
	private function render_troubleshooting_tab(): void {
		$issues = array(
			array(
				'issue'    => __( 'Messages not sending', 'signals-dispatch-woocommerce' ),
				'solution' => __( 'Verify API credentials in Setup page. Ensure Action Scheduler is running (WooCommerce → Status → Scheduled Actions). Check that the order has a valid billing phone number.', 'signals-dispatch-woocommerce' ),
			),
			array(
				'issue'    => __( 'Invalid Access Token error', 'signals-dispatch-woocommerce' ),
				'solution' => __( 'Your token may have expired (temporary tokens last 24 hours). Generate a new permanent token via System User in Meta Business Settings.', 'signals-dispatch-woocommerce' ),
			),
			array(
				'issue'    => __( 'Webhook verification failing', 'signals-dispatch-woocommerce' ),
				'solution' => __( 'Ensure your site is accessible from the internet. The verify token must match exactly in both places (plugin settings and Meta app). Check for trailing spaces.', 'signals-dispatch-woocommerce' ),
			),
			array(
				'issue'    => __( 'Webhook updates not appearing in logs', 'signals-dispatch-woocommerce' ),
				'solution' => __( 'Verify the webhook is subscribed to "messages" field in Meta app. Check that your callback URL is correct and SSL certificate is valid.', 'signals-dispatch-woocommerce' ),
			),
			array(
				'issue'    => __( 'Template not found error', 'signals-dispatch-woocommerce' ),
				'solution' => __( 'Template name is case-sensitive. Ensure template status is "Approved" in Meta dashboard. Verify you\'re using the correct language code.', 'signals-dispatch-woocommerce' ),
			),
			array(
				'issue'    => __( 'Phone number not registered', 'signals-dispatch-woocommerce' ),
				'solution' => __( 'Make sure you\'re using the Phone Number ID (a numeric string), not the actual phone number. Verify the number is properly set up in WhatsApp Business.', 'signals-dispatch-woocommerce' ),
			),
			array(
				'issue'    => __( 'Logs page is empty', 'signals-dispatch-woocommerce' ),
				'solution' => __( 'Ensure dispatch rules are enabled for the order status. Verify the order has a billing phone number. Check the Health page for system status.', 'signals-dispatch-woocommerce' ),
			),
			array(
				'issue'    => __( 'Messages stuck in queue', 'signals-dispatch-woocommerce' ),
				'solution' => __( 'Check if WP-Cron is working (install WP Crontrol plugin to debug). Ensure Action Scheduler is processing jobs in WooCommerce → Status → Scheduled Actions.', 'signals-dispatch-woocommerce' ),
			),
		);

		echo '<div class="card">';
		echo '<h2>' . esc_html__( 'Troubleshooting Guide', 'signals-dispatch-woocommerce' ) . '</h2>';

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Issue', 'signals-dispatch-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Solution', 'signals-dispatch-woocommerce' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		foreach ( $issues as $item ) {
			echo '<tr>';
			echo '<td><strong>' . esc_html( $item['issue'] ) . '</strong></td>';
			echo '<td>' . esc_html( $item['solution'] ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		$this->render_support_section();

		echo '</div>';
	}

	/**
	 * Render support section.
	 *
	 * @return void
	 */
	private function render_support_section(): void {
		?>
		<div class="notice notice-info inline">
			<h3><?php esc_html_e( 'Need More Help?', 'signals-dispatch-woocommerce' ); ?></h3>
			<ul>
				<li>
					<a href="https://github.com/themediaable/signals-dispatch-woocommerce/issues" target="_blank">
						<?php esc_html_e( 'Report an issue on GitHub', 'signals-dispatch-woocommerce' ); ?>
					</a>
				</li>
				<li>
					<a href="https://developers.facebook.com/docs/whatsapp/cloud-api" target="_blank">
						<?php esc_html_e( 'WhatsApp Cloud API Documentation', 'signals-dispatch-woocommerce' ); ?>
					</a>
				</li>
				<li>
					<a href="https://developers.facebook.com/docs/whatsapp/message-templates" target="_blank">
						<?php esc_html_e( 'Message Templates Guide', 'signals-dispatch-woocommerce' ); ?>
					</a>
				</li>
			</ul>
		</div>
		<?php
	}
}
