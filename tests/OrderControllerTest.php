<?php

declare(strict_types=1);

use TMASD\Signals\Dispatch\Admin\OrderController;
use TMASD\Signals\Dispatch\Database\LogRepository;
use TMASD\Signals\Dispatch\Database\MappingRepository;

final class OrderControllerTest extends PluginTestCase {
	private function controller(): OrderController {
		return new OrderController(
			$this->queue,
			new MappingRepository( $this->db ),
			new LogRepository( $this->db )
		);
	}

	private function validRequest(): void {
		TMASD_Test_Environment::$capabilities = array( TMASD_CAPABILITY );
		TMASD_Test_Environment::$nonces['tmasd_manual_send_42'] = 'test-nonce';
		$_POST = array( 'order_id' => '42', 'mapping_id' => '1', '_ajax_nonce' => 'test-nonce' );
	}

	public function test_unauthorized_user_cannot_send(): void {
		$this->db->expects( self::never() )->method( 'get_row' );
		$this->api->expects( self::never() )->method( 'send_template_message' );

		try {
			$this->controller()->handle_manual_send();
			self::fail( 'Expected a permission error.' );
		} catch ( TMASD_Test_Json_Response $response ) {
			self::assertFalse( $response->success );
			self::assertSame( 403, $response->getCode() );
		}
	}

	/**
	 * @dataProvider invalidNonces
	 */
	public function test_missing_or_invalid_nonce_cannot_send( string $nonce ): void {
		$this->validRequest();
		$_POST['_ajax_nonce'] = $nonce;
		$this->db->expects( self::never() )->method( 'get_row' );
		$this->api->expects( self::never() )->method( 'send_template_message' );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Invalid nonce: tmasd_manual_send_42' );
		$this->controller()->handle_manual_send();
	}

	public function invalidNonces(): array {
		return array( array( '' ), array( 'wrong-nonce' ) );
	}

	/**
	 * @dataProvider permittedCapabilities
	 */
	public function test_successful_manual_send_blocks_rapid_duplicate( string $capability ): void {
		$this->validRequest();
		TMASD_Test_Environment::$capabilities = array( $capability );
		$this->stubPayload();
		$this->expectSuccessfulSend();
		$controller = $this->controller();

		try {
			$controller->handle_manual_send();
			self::fail( 'Expected a success response.' );
		} catch ( TMASD_Test_Json_Response $response ) {
			self::assertTrue( $response->success );
		}

		self::assertSame( 'manual', $this->logs[0]['trigger_source'] );
		self::assertSame( 30, TMASD_Test_Environment::$transients['tmasd_manual_send_42']['expiration'] );

		try {
			$controller->handle_manual_send();
			self::fail( 'Expected the second send to be blocked.' );
		} catch ( TMASD_Test_Json_Response $response ) {
			self::assertFalse( $response->success );
			self::assertStringContainsString( 'already sent recently', $response->getMessage() );
		}

		self::assertCount( 1, $this->logs );
		self::assertSame( array(), TMASD_Test_Environment::$scheduled );
	}

	public function permittedCapabilities(): array {
		return array( array( TMASD_CAPABILITY ), array( 'manage_options' ) );
	}
}
