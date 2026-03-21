<?php
/**
 * Admin controller for menu and assets.
 *
 * @package TMASD\Signals\Dispatch\Admin
 */

declare(strict_types=1);

namespace TMASD\Signals\Dispatch\Admin;

use TMASD\Signals\Dispatch\Contracts\ApiClientInterface;
use TMASD\Signals\Dispatch\Database\LogRepository;
use TMASD\Signals\Dispatch\Database\MappingRepository;
use TMASD\Signals\Dispatch\Queue\QueueService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main admin controller.
 *
 * Coordinates admin menu registration, settings, and sub-controllers.
 * Single Responsibility: Menu and asset management only.
 *
 * @final
 */
final class AdminController extends AbstractAdminController {

	/**
	 * Log repository.
	 *
	 * @var LogRepository
	 */
	private LogRepository $log_repo;

	/**
	 * Mapping repository.
	 *
	 * @var MappingRepository
	 */
	private MappingRepository $mapping_repo;

	/**
	 * API client.
	 *
	 * @var ApiClientInterface
	 */
	private ApiClientInterface $api_client;

	/**
	 * Setup page controller.
	 *
	 * @var SetupController
	 */
	private SetupController $setup_controller;

	/**
	 * Dispatch page controller.
	 *
	 * @var DispatchController
	 */
	private DispatchController $dispatch_controller;

	/**
	 * Logs page controller.
	 *
	 * @var LogsController
	 */
	private LogsController $logs_controller;

	/**
	 * Health page controller.
	 *
	 * @var HealthController
	 */
	private HealthController $health_controller;

	/**
	 * Help page controller.
	 *
	 * @var HelpController
	 */
	private HelpController $help_controller;

	/**
	 * Upgrade page controller.
	 *
	 * @var UpgradeController
	 */
	private UpgradeController $upgrade_controller;

	/**
	 * Order page controller.
	 *
	 * @var OrderController
	 */
	private OrderController $order_controller;

	/**
	 * Constructor.
	 *
	 * @param LogRepository      $log_repo         Log repository.
	 * @param MappingRepository  $mapping_repo     Mapping repository.
	 * @param ApiClientInterface $api_client       API client.
	 * @param OrderController    $order_controller Order controller.
	 */
	public function __construct(
		LogRepository $log_repo,
		MappingRepository $mapping_repo,
		ApiClientInterface $api_client,
		OrderController $order_controller
	) {
		$this->log_repo     = $log_repo;
		$this->mapping_repo = $mapping_repo;
		$this->api_client   = $api_client;

		$this->setup_controller    = new SetupController( $api_client, $log_repo );
		$this->dispatch_controller = new DispatchController( $mapping_repo );
		$this->logs_controller     = new LogsController( $log_repo );
		$this->health_controller   = new HealthController( $log_repo );
		$this->help_controller     = new HelpController();
		$this->upgrade_controller  = new UpgradeController();
		$this->order_controller    = $order_controller;
	}

	/**
	 * Boot the admin controller.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_tmasd_save_setup', array( $this->setup_controller, 'handle_save' ) );
		add_action( 'admin_post_tmasd_send_test', array( $this->setup_controller, 'handle_test' ) );
		add_action( 'admin_post_tmasd_save_mapping', array( $this->dispatch_controller, 'handle_save' ) );
		add_action( 'admin_post_tmasd_delete_mapping', array( $this->dispatch_controller, 'handle_delete' ) );
		add_action( 'admin_post_tmasd_delete_log', array( $this->logs_controller, 'handle_delete' ) );
		add_action( 'admin_post_tmasd_delete_all_logs', array( $this->logs_controller, 'handle_delete_all' ) );
		add_action( 'wp_ajax_tmasd_refresh_status', array( $this->logs_controller, 'handle_refresh_status' ) );
		add_action( 'admin_footer', array( $this->logs_controller, 'render_refresh_script' ) );
		add_action( 'wp_ajax_tmasd_manual_send', array( $this->order_controller, 'handle_manual_send' ) );
		add_action( 'admin_notices', array( $this, 'render_upgrade_notice' ) );
	}

	/**
	 * Register admin menu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$cap = $this->get_capability();

		add_menu_page(
			__( 'Signals', 'signals-dispatch-woocommerce' ),
			__( 'Signals', 'signals-dispatch-woocommerce' ),
			$cap,
			'tmasd-setup',
			array( $this->setup_controller, 'render' ),
			'dashicons-admin-generic'
		);

		add_submenu_page(
			'tmasd-setup',
			__( 'Setup', 'signals-dispatch-woocommerce' ),
			__( 'Setup', 'signals-dispatch-woocommerce' ),
			$cap,
			'tmasd-setup',
			array( $this->setup_controller, 'render' )
		);

		add_submenu_page(
			'tmasd-setup',
			__( 'Dispatch Rules', 'signals-dispatch-woocommerce' ),
			__( 'Dispatch', 'signals-dispatch-woocommerce' ),
			$cap,
			'tmasd-dispatch',
			array( $this->dispatch_controller, 'render' )
		);

		add_submenu_page(
			'tmasd-setup',
			__( 'Logs', 'signals-dispatch-woocommerce' ),
			__( 'Logs', 'signals-dispatch-woocommerce' ),
			$cap,
			'tmasd-logs',
			array( $this->logs_controller, 'render' )
		);

		add_submenu_page(
			'tmasd-setup',
			__( 'Health Check', 'signals-dispatch-woocommerce' ),
			__( 'Health Check', 'signals-dispatch-woocommerce' ),
			$cap,
			'tmasd-health',
			array( $this->health_controller, 'render' )
		);

		add_submenu_page(
			'tmasd-setup',
			__( 'Help & Documentation', 'signals-dispatch-woocommerce' ),
			__( 'Help', 'signals-dispatch-woocommerce' ),
			$cap,
			'tmasd-help',
			array( $this->help_controller, 'render' )
		);

		add_submenu_page(
			'tmasd-setup',
			__( 'Pro — Coming Soon', 'signals-dispatch-woocommerce' ),
			__( 'Coming Soon ★', 'signals-dispatch-woocommerce' ),
			$cap,
			'tmasd-upgrade',
			array( $this->upgrade_controller, 'render' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		$options = array(
			\TMASD_OPTION_PHONE_NUMBER_ID,
			\TMASD_OPTION_WABA_ID,
			\TMASD_OPTION_ACCESS_TOKEN,
			\TMASD_OPTION_WEBHOOK_VERIFY_TOKEN,
			\TMASD_OPTION_APP_SECRET,
		);

		foreach ( $options as $option ) {
			register_setting(
				'tmasd_settings',
				$option,
				array(
					'sanitize_callback' => static function ( $value ): string {
						return sanitize_text_field( (string) $value );
					},
				)
			);
		}
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, 'tmasd' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'tmasd-admin',
			\TMASD_PLUGIN_URL . 'assets/admin.css',
			array(),
			\TMASD_VERSION
		);

		wp_enqueue_script(
			'tmasd-admin',
			\TMASD_PLUGIN_URL . 'assets/admin.js',
			array(),
			\TMASD_VERSION,
			true
		);
	}

	/**
	 * Render page - not used directly.
	 *
	 * @return void
	 */
	public function render(): void {
		// Main controller delegates to sub-controllers.
	}

	/**
	 * Render a dismissible upgrade notice on plugin admin pages.
	 *
	 * @return void
	 */
	public function render_upgrade_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';

		if ( strpos( $page, 'tmasd' ) === false ) {
			return;
		}

		// Don't show on the upgrade page itself.
		if ( 'tmasd-upgrade' === $page ) {
			return;
		}

		echo '<div class="notice notice-info tmasd-notice is-dismissible" data-dismiss-key="tmasd-upgrade-notice">';
		echo '<p>';
		printf(
			/* translators: 1: opening anchor tag, 2: closing anchor tag */
			esc_html__( 'Signals Pro is coming soon! %1$sSee what\'s planned%2$s — unlimited dispatch rules, automatic retries, and priority support.', 'signals-dispatch-woocommerce' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=tmasd-upgrade' ) ) . '"><strong>',
			'</strong></a>'
		);
		echo '</p>';
		echo '<button class="tmasd-notice-dismiss">&times;</button>';
		echo '</div>';
	}
}
