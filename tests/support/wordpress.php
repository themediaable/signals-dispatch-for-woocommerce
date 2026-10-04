<?php

declare(strict_types=1);

// Minimal boundary doubles: no live WordPress, database, scheduler, or HTTP calls.
final class TMASD_Test_Environment {
	public static array $options = array();
	public static array $capabilities = array();
	public static array $transients = array();
	public static array $scheduled = array();
	public static ?WC_Order $order = null;
	public static array $nonces = array();

	public static function reset(): void {
		self::$options      = array();
		self::$capabilities = array();
		self::$transients   = array();
		self::$scheduled   = array();
		self::$order       = null;
		self::$nonces      = array();
		$_POST             = array();
	}
}

final class TMASD_Test_Json_Response extends RuntimeException {
	public array $data;
	public bool $success;

	public function __construct( array $data, bool $success, int $status ) {
		parent::__construct( $data['message'], $status );
		$this->data    = $data;
		$this->success = $success;
	}
}

class wpdb {
	public string $prefix = 'wp_';
	public int $insert_id = 1;

	public function prepare( string $query, ...$args ): string {
		return $query;
	}

	public function get_row( string $query, string $output ) {
		throw new LogicException( 'Unexpected database read.' );
	}

	public function insert( string $table, array $data ): int {
		throw new LogicException( 'Unexpected database insert.' );
	}

	public function update( string $table, array $data, array $where ): int {
		throw new LogicException( 'Unexpected database update.' );
	}
}

class WC_Order {
	private array $meta;

	public function __construct( array $meta = array() ) {
		$this->meta = $meta;
	}

	public function get_meta( string $key ) {
		return $this->meta[ $key ] ?? '';
	}
}

class WP_REST_Request {
	private string $body = '';
	private array $headers = array();
	private array $params = array();

	public function set_body( string $body ): void {
		$this->body = $body;
	}

	public function get_body(): string {
		return $this->body;
	}

	public function get_json_params(): ?array {
		return json_decode( $this->body, true );
	}

	public function set_header( string $name, string $value ): void {
		$this->headers[ strtolower( $name ) ] = $value;
	}

	public function get_header( string $name ): ?string {
		return $this->headers[ strtolower( $name ) ] ?? null;
	}

	public function set_param( string $name, string $value ): void {
		$this->params[ $name ] = $value;
	}

	public function get_param( string $name ): ?string {
		return $this->params[ $name ] ?? null;
	}
}

class WP_REST_Response {
	private $data;
	private int $status;

	public function __construct( $data, int $status ) {
		$this->data   = $data;
		$this->status = $status;
	}

	public function get_status(): int {
		return $this->status;
	}

	public function get_data() {
		return $this->data;
	}
}

function plugin_dir_path( string $file ): string {
	return dirname( $file ) . '/';
}

function plugin_dir_url( string $file ): string {
	return 'https://example.test/plugins/signals-dispatch-for-woocommerce/';
}

function register_activation_hook( string $file, callable $callback ): void {}
function register_deactivation_hook( string $file, callable $callback ): void {}
function add_action( string $hook, callable $callback, int $priority = 10, int $args = 1 ): void {}

function get_option( string $name, $default = false ) {
	return TMASD_Test_Environment::$options[ $name ] ?? $default;
}

function update_option( string $name, $value, bool $autoload = true ): bool {
	TMASD_Test_Environment::$options[ $name ] = $value;
	return true;
}

function current_time( string $type ): string {
	return '2026-01-01 00:00:00';
}

function wp_parse_args( array $args, array $defaults ): array {
	return array_merge( $defaults, $args );
}

function wp_json_encode( $data ) {
	return json_encode( $data );
}

function wc_get_order( int $id ): ?WC_Order {
	return TMASD_Test_Environment::$order;
}

function current_user_can( string $capability ): bool {
	return in_array( $capability, TMASD_Test_Environment::$capabilities, true );
}

function __( string $text, string $domain ): string {
	return $text;
}

function wp_unslash( string $value ): string {
	return stripslashes( $value );
}

function sanitize_text_field( string $value ): string {
	return trim( strip_tags( $value ) );
}

function wp_send_json_error( array $data, int $status = 200 ): void {
	throw new TMASD_Test_Json_Response( $data, false, $status );
}

function wp_send_json_success( array $data, int $status = 200 ): void {
	throw new TMASD_Test_Json_Response( $data, true, $status );
}

function check_ajax_referer( string $action ): void {
	$expected = TMASD_Test_Environment::$nonces[ $action ] ?? null;
	if ( null === $expected || ( $_POST['_ajax_nonce'] ?? '' ) !== $expected ) {
		throw new RuntimeException( 'Invalid nonce: ' . $action, 403 );
	}
}

function get_transient( string $key ) {
	return TMASD_Test_Environment::$transients[ $key ]['value'] ?? false;
}

function set_transient( string $key, $value, int $expiration ): bool {
	TMASD_Test_Environment::$transients[ $key ] = array(
		'value'      => $value,
		'expiration' => $expiration,
	);
	return true;
}

function as_enqueue_async_action( string $hook, array $args, string $group ): int {
	TMASD_Test_Environment::$scheduled[] = array( $hook, $args, $group );
	return count( TMASD_Test_Environment::$scheduled );
}
