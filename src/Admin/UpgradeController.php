<?php
/**
 * Coming Soon page controller.
 *
 * @package TMASD\Signals\Dispatch\Admin
 */

declare(strict_types=1);

namespace TMASD\Signals\Dispatch\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coming Soon page controller.
 *
 * Displays a "Coming Soon" landing page showcasing upcoming Pro features
 * with an inquiry button that sends email to the team.
 *
 * @final
 */
final class UpgradeController extends AbstractAdminController {

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	protected string $page_slug = 'tmasd-upgrade';

	/**
	 * Contact email for inquiries.
	 *
	 * @var string
	 */
	private const CONTACT_EMAIL = 'contact@themediaablesignals.com';

	/**
	 * Render the coming soon page.
	 *
	 * @return void
	 */
	public function render(): void {
		$this->assert_access();

		echo '<div class="wrap tmasd-admin tmasd-upgrade-page">';
		echo '<h1 class="wp-heading-inline">';
		echo esc_html__( 'Pro Features — Coming Soon', 'signals-dispatch-for-woocommerce' );
		echo '</h1>';
		echo '<hr class="wp-header-end" />';

		$this->render_hero();
		$this->render_coming_soon_features();
		$this->render_comparison_table();
		$this->render_faq();
		$this->render_cta_footer();

		echo '</div>';
	}

	/**
	 * Render hero section.
	 *
	 * @return void
	 */
	private function render_hero(): void {
		$mailto = $this->get_mailto_url();
		?>
		<div class="tmasd-panel tmasd-upgrade-hero">
			<span class="tmasd-coming-soon-badge"><?php esc_html_e( 'Coming Soon', 'signals-dispatch-for-woocommerce' ); ?></span>
			<h2><?php esc_html_e( 'Signals Pro is on the Way', 'signals-dispatch-for-woocommerce' ); ?></h2>
			<p><?php esc_html_e( 'We\'re building powerful new features — automatic retries, COD confirmation, scheduled log cleanup, and priority support. Be the first to know when it launches.', 'signals-dispatch-for-woocommerce' ); ?></p>
			<p>
				<a href="<?php echo esc_url( $mailto ); ?>" class="button button-primary button-hero">
					<?php esc_html_e( 'Notify Me at Launch', 'signals-dispatch-for-woocommerce' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Render upcoming Pro features section.
	 *
	 * @return void
	 */
	private function render_coming_soon_features(): void {
		$features = array(
			array(
				'icon'        => 'dashicons-money-alt',
				'title'       => __( 'COD Confirmation', 'signals-dispatch-for-woocommerce' ),
				'description' => __( 'Send automated Cash on Delivery order confirmations to reduce RTOs and improve delivery success.', 'signals-dispatch-for-woocommerce' ),
			),
			array(
				'icon'        => 'dashicons-database',
				'title'       => __( 'Scheduled Log Cleanup', 'signals-dispatch-for-woocommerce' ),
				'description' => __( 'Automatically purge old logs on a configurable schedule — keep your database lean while retaining what matters.', 'signals-dispatch-for-woocommerce' ),
			),
			array(
				'icon'        => 'dashicons-sos',
				'title'       => __( 'Priority Support', 'signals-dispatch-for-woocommerce' ),
				'description' => __( 'Get fast, dedicated support from our team to help with setup, template approval, and troubleshooting.', 'signals-dispatch-for-woocommerce' ),
			),
		);

		echo '<div class="tmasd-panel">';
		echo '<h2>' . esc_html__( 'What\'s Coming in Pro', 'signals-dispatch-for-woocommerce' ) . '</h2>';
		echo '<div class="tmasd-cards tmasd-coming-grid">';

		foreach ( $features as $feature ) {
			echo '<div class="tmasd-card tmasd-coming-card">';
			echo '<span class="dashicons ' . esc_attr( $feature['icon'] ) . ' tmasd-coming-icon"></span>';
			echo '<h3>' . esc_html( $feature['title'] ) . '</h3>';
			echo '<p>' . esc_html( $feature['description'] ) . '</p>';
			echo '</div>';
		}

		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render Free vs Pro comparison table.
	 *
	 * @return void
	 */
	private function render_comparison_table(): void {
		$features = $this->get_comparison_features();

		?>
		<div class="tmasd-panel">
			<h2><?php esc_html_e( 'Free vs Pro Comparison', 'signals-dispatch-for-woocommerce' ); ?></h2>
			<table class="widefat striped tmasd-comparison-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Feature', 'signals-dispatch-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Free', 'signals-dispatch-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Pro (Coming Soon)', 'signals-dispatch-for-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $features as $feature ) : ?>
					<tr>
						<td><?php echo esc_html( $feature['name'] ); ?></td>
						<td>
							<?php if ( $feature['free'] ) : ?>
								<span class="tmasd-badge tmasd-badge--yes"><?php echo esc_html( is_string( $feature['free'] ) ? $feature['free'] : __( 'Yes', 'signals-dispatch-for-woocommerce' ) ); ?></span>
							<?php else : ?>
								<span class="tmasd-badge tmasd-badge--no"><?php esc_html_e( 'No', 'signals-dispatch-for-woocommerce' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $feature['pro'] ) : ?>
								<span class="tmasd-badge tmasd-badge--yes"><?php echo esc_html( is_string( $feature['pro'] ) ? $feature['pro'] : __( 'Yes', 'signals-dispatch-for-woocommerce' ) ); ?></span>
							<?php else : ?>
								<span class="tmasd-badge tmasd-badge--no"><?php esc_html_e( 'No', 'signals-dispatch-for-woocommerce' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Get comparison feature data.
	 *
	 * @return array<int, array{name: string, free: bool|string, pro: bool|string}> Features.
	 */
	private function get_comparison_features(): array {
		return array(
			array(
				'name' => __( 'Setup Wizard', 'signals-dispatch-for-woocommerce' ),
				'free' => true,
				'pro'  => true,
			),
			array(
				'name' => __( 'Dispatch Rules', 'signals-dispatch-for-woocommerce' ),
				'free' => true,
				'pro'  => true,
			),
			array(
				'name' => __( 'Automated Order Notifications', 'signals-dispatch-for-woocommerce' ),
				'free' => true,
				'pro'  => true,
			),
			array(
				'name' => __( 'Checkout Opt-In Checkbox', 'signals-dispatch-for-woocommerce' ),
				'free' => true,
				'pro'  => true,
			),
			array(
				'name' => __( 'Webhook Delivery Status Tracking', 'signals-dispatch-for-woocommerce' ),
				'free' => true,
				'pro'  => true,
			),
			array(
				'name' => __( 'Manual Send on Order Page', 'signals-dispatch-for-woocommerce' ),
				'free' => true,
				'pro'  => true,
			),
			array(
				'name' => __( 'Log Retention', 'signals-dispatch-for-woocommerce' ),
				'free' => __( 'Manual cleanup', 'signals-dispatch-for-woocommerce' ),
				'pro'  => __( 'Scheduled auto-cleanup', 'signals-dispatch-for-woocommerce' ),
			),
			array(
				'name' => __( 'COD Confirmation', 'signals-dispatch-for-woocommerce' ),
				'free' => false,
				'pro'  => true,
			),
			array(
				'name' => __( 'Automatic Retries', 'signals-dispatch-for-woocommerce' ),
				'free' => true,
				'pro'  => true,
			),
			array(
				'name' => __( 'Priority Support', 'signals-dispatch-for-woocommerce' ),
				'free' => false,
				'pro'  => true,
			),
		);
	}

	/**
	 * Render FAQ section.
	 *
	 * @return void
	 */
	private function render_faq(): void {
		$faqs = array(
			array(
				'question' => __( 'When will Pro be available?', 'signals-dispatch-for-woocommerce' ),
				'answer'   => __( 'We\'re actively working on Pro features. Send us an enquiry to be notified the moment it launches.', 'signals-dispatch-for-woocommerce' ),
			),
			array(
				'question' => __( 'Will I lose my existing data when I upgrade?', 'signals-dispatch-for-woocommerce' ),
				'answer'   => __( 'No. Upgrading will preserve all your dispatch rules, logs, consent records, and settings. It only adds new capabilities.', 'signals-dispatch-for-woocommerce' ),
			),
			array(
				'question' => __( 'Can I request a specific feature?', 'signals-dispatch-for-woocommerce' ),
				'answer'   => __( 'Absolutely! Send us an email with your feature request and we\'ll prioritize based on community feedback.', 'signals-dispatch-for-woocommerce' ),
			),
			array(
				'question' => __( 'Is the free version going away?', 'signals-dispatch-for-woocommerce' ),
				'answer'   => __( 'No. The free version will always be available with all current features. Pro simply adds more power on top.', 'signals-dispatch-for-woocommerce' ),
			),
		);

		echo '<div class="tmasd-panel tmasd-upgrade-faq">';
		echo '<h2>' . esc_html__( 'Frequently Asked Questions', 'signals-dispatch-for-woocommerce' ) . '</h2>';

		foreach ( $faqs as $faq ) {
			echo '<details>';
			echo '<summary>' . esc_html( $faq['question'] ) . '</summary>';
			echo '<p>' . esc_html( $faq['answer'] ) . '</p>';
			echo '</details>';
		}

		echo '</div>';
	}

	/**
	 * Render CTA footer.
	 *
	 * @return void
	 */
	private function render_cta_footer(): void {
		$mailto = $this->get_mailto_url();
		?>
		<div class="tmasd-panel tmasd-upgrade-cta-footer" style="text-align:center;">
			<h2><?php esc_html_e( 'Interested in Signals Pro?', 'signals-dispatch-for-woocommerce' ); ?></h2>
			<p><?php esc_html_e( 'Drop us a line and we\'ll keep you in the loop. Early enquirers get priority access.', 'signals-dispatch-for-woocommerce' ); ?></p>
			<div class="tmasd-cta-buttons">
				<a href="<?php echo esc_url( $mailto ); ?>" class="button button-primary button-hero">
					<?php esc_html_e( 'Enquire Now', 'signals-dispatch-for-woocommerce' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Build the mailto URL for enquiries.
	 *
	 * @return string Mailto URL.
	 */
	private function get_mailto_url(): string {
		$site_name = get_bloginfo( 'name' );
		$subject   = sprintf(
			/* translators: %s: site name */
			__( 'Signals Pro Enquiry — %s', 'signals-dispatch-for-woocommerce' ),
			$site_name
		);
		$body = __( 'Hi, I\'m interested in Signals Pro. Please notify me when it launches.', 'signals-dispatch-for-woocommerce' );

		return 'mailto:' . self::CONTACT_EMAIL
			. '?subject=' . rawurlencode( $subject )
			. '&body=' . rawurlencode( $body );
	}
}
