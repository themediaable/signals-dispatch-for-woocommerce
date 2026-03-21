<?php
/**
 * Plugin bootstrap and dependency container.
 *
 * @package TMASD\Signals\Dispatch\Core
 */

declare(strict_types=1);

namespace TMASD\Signals\Dispatch\Core;

use TMASD\Signals\Dispatch\Admin\AdminController;
use TMASD\Signals\Dispatch\Admin\OrderController;
use TMASD\Signals\Dispatch\Admin\PrivacyController;
use TMASD\Signals\Dispatch\API\WebhookController;
use TMASD\Signals\Dispatch\Checkout\CheckoutController;
use TMASD\Signals\Dispatch\Contracts\ApiClientInterface;
use TMASD\Signals\Dispatch\Contracts\PhoneNormalizerInterface;
use TMASD\Signals\Dispatch\Contracts\TemplateMapperInterface;
use TMASD\Signals\Dispatch\Database\LogRepository;
use TMASD\Signals\Dispatch\Database\MappingRepository;
use TMASD\Signals\Dispatch\Database\OptinRepository;
use TMASD\Signals\Dispatch\Database\SchemaManager;
use TMASD\Signals\Dispatch\Queue\QueueService;
use TMASD\Signals\Dispatch\Services\ApiClientService;
use TMASD\Signals\Dispatch\Services\PhoneNormalizerService;
use TMASD\Signals\Dispatch\Services\TemplateMapperService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin bootstrap and simple dependency container.
 *
 * Responsible for initializing all plugin services and managing
 * their lifecycle. Uses constructor dependency injection.
 *
 * @final
 */
final class Container {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Service instances cache.
	 *
	 * @var array<string, object>
	 */
	private array $services = array();

	/**
	 * Private constructor for singleton pattern.
	 */
	private function __construct() {
		// Private constructor.
	}

	/**
	 * Get singleton instance.
	 *
	 * @return self Container instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boot all plugin services.
	 *
	 * @return void
	 */
	public function boot(): void {
		$this->register_services();
		$this->boot_services();
	}

	/**
	 * Register all services in the container.
	 *
	 * @return void
	 */
	private function register_services(): void {
		// Repositories.
		$this->services['log_repo']     = new LogRepository();
		$this->services['mapping_repo'] = new MappingRepository();
		$this->services['optin_repo']   = new OptinRepository();
		$this->services['schema']       = new SchemaManager(
			null,
			$this->services['log_repo'],
			$this->services['mapping_repo'],
			$this->services['optin_repo']
		);

		// Services.
		$this->services['phone_normalizer'] = new PhoneNormalizerService();
		$this->services['template_mapper']  = new TemplateMapperService(
			$this->services['phone_normalizer']
		);
		$this->services['api_client']       = new ApiClientService(
			$this->services['phone_normalizer']
		);

		// Queue.
		$this->services['queue'] = new QueueService(
			$this->services['log_repo'],
			$this->services['mapping_repo'],
			$this->services['optin_repo'],
			$this->services['api_client'],
			$this->services['template_mapper']
		);

		// Webhook.
		$this->services['webhook'] = new WebhookController(
			$this->services['log_repo']
		);

		// Checkout opt-in (runs on frontend).
		$this->services['checkout'] = new CheckoutController(
			$this->services['optin_repo'],
			$this->services['phone_normalizer']
		);

		// Order admin (manual send meta box).
		$this->services['order_admin'] = new OrderController(
			$this->services['queue'],
			$this->services['mapping_repo'],
			$this->services['log_repo']
		);

		// Admin.
		$this->services['admin'] = new AdminController(
			$this->services['log_repo'],
			$this->services['mapping_repo'],
			$this->services['api_client'],
			$this->services['order_admin']
		);

		// Privacy.
		$this->services['privacy'] = new PrivacyController(
			$this->services['log_repo'],
			$this->services['optin_repo']
		);
	}

	/**
	 * Boot all bootable services.
	 *
	 * @return void
	 */
	private function boot_services(): void {
		// Boot queue service.
		$this->services['queue']->boot();

		// Boot webhook controller.
		$this->services['webhook']->boot();

		// Boot checkout opt-in (frontend).
		$this->services['checkout']->boot();

		// Boot admin in admin context.
		if ( is_admin() ) {
			$this->services['admin']->boot();
			$this->services['privacy']->boot();
			$this->services['order_admin']->boot();
		}
	}

	/**
	 * Handle plugin activation.
	 *
	 * @return void
	 */
	public function activate(): void {
		$this->register_services();
		$this->services['schema']->create_tables();

		// Add plugin capability to administrators.
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->add_cap( \TMASD_CAPABILITY );
		}

		flush_rewrite_rules();
	}

	/**
	 * Handle plugin deactivation.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Get a service from the container.
	 *
	 * @param string $key Service key.
	 * @return object|null Service instance or null.
	 */
	public function get( string $key ): ?object {
		return $this->services[ $key ] ?? null;
	}

	/**
	 * Get phone normalizer service.
	 *
	 * @return PhoneNormalizerInterface Phone normalizer.
	 */
	public function phone_normalizer(): PhoneNormalizerInterface {
		return $this->services['phone_normalizer'];
	}

	/**
	 * Get template mapper service.
	 *
	 * @return TemplateMapperInterface Template mapper.
	 */
	public function template_mapper(): TemplateMapperInterface {
		return $this->services['template_mapper'];
	}

	/**
	 * Get API client service.
	 *
	 * @return ApiClientInterface API client.
	 */
	public function api_client(): ApiClientInterface {
		return $this->services['api_client'];
	}

	/**
	 * Get log repository.
	 *
	 * @return LogRepository Log repository.
	 */
	public function log_repo(): LogRepository {
		return $this->services['log_repo'];
	}

	/**
	 * Get mapping repository.
	 *
	 * @return MappingRepository Mapping repository.
	 */
	public function mapping_repo(): MappingRepository {
		return $this->services['mapping_repo'];
	}

	/**
	 * Get optin repository.
	 *
	 * @return OptinRepository Optin repository.
	 */
	public function optin_repo(): OptinRepository {
		return $this->services['optin_repo'];
	}
}
