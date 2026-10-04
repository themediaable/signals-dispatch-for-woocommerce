<?php

declare(strict_types=1);

use TMASD\Signals\Dispatch\API\WebhookController;
use TMASD\Signals\Dispatch\Database\LogRepository;

final class WebhookControllerTest extends PluginTestCase {
	private function request( string $status = 'delivered' ): WP_REST_Request {
		$request = new WP_REST_Request();
		$request->set_body(
			json_encode(
				array(
					'object' => 'whatsapp_business_account',
					'entry'  => array(
						array( 'changes' => array(
							array( 'value' => array(
								'statuses' => array( array( 'id' => 'test-message-id', 'status' => $status ) ),
							) ),
						) ),
					),
				)
			)
		);
		return $request;
	}

	/**
	 * @dataProvider rejectedSignatures
	 */
	public function test_rejects_unauthenticated_webhooks( string $case ): void {
		$request = $this->request();
		TMASD_Test_Environment::$options[ TMASD_OPTION_APP_SECRET ] = 'test-app-secret';
		$signature = 'sha256=' . hash_hmac( 'sha256', $request->get_body(), 'test-app-secret' );

		if ( 'missing secret' === $case ) {
			unset( TMASD_Test_Environment::$options[ TMASD_OPTION_APP_SECRET ] );
			$request->set_header( 'X-Hub-Signature-256', $signature );
		} elseif ( 'wrong signature' === $case ) {
			$request->set_header( 'X-Hub-Signature-256', 'sha256=wrong' );
		} elseif ( 'tampered body' === $case ) {
			$request->set_header( 'X-Hub-Signature-256', $signature );
			$request->set_body( $request->get_body() . ' ' );
		} elseif ( 'wrong prefix' === $case ) {
			$request->set_header( 'X-Hub-Signature-256', substr( $signature, 7 ) );
		}

		$this->db->expects( self::never() )->method( 'update' );
		$controller = new WebhookController( new LogRepository( $this->db ) );
		$response = $controller->handle_webhook( $request );

		self::assertSame( 401, $response->get_status() );
		self::assertSame( 'Unauthorized', $response->get_data() );
		self::assertArrayNotHasKey( TMASD_OPTION_LAST_WEBHOOK_RECEIVED_AT, TMASD_Test_Environment::$options );
	}

	public function rejectedSignatures(): array {
		return array(
			array( 'missing secret' ),
			array( 'missing signature' ),
			array( 'wrong signature' ),
			array( 'tampered body' ),
			array( 'wrong prefix' ),
		);
	}

	/**
	 * @dataProvider deliveryStatuses
	 */
	public function test_signed_status_updates_are_persisted( string $status ): void {
		$request = $this->request( $status );
		TMASD_Test_Environment::$options[ TMASD_OPTION_APP_SECRET ] = 'test-app-secret';
		$request->set_header( 'X-Hub-Signature-256', 'sha256=' . hash_hmac( 'sha256', $request->get_body(), 'test-app-secret' ) );
		$this->db->expects( self::once() )->method( 'update' )->with(
			'wp_tmasd_logs',
			array( 'status' => $status, 'updated_at' => current_time( 'mysql' ) ),
			array( 'wa_message_id' => 'test-message-id' )
		);

		$response = ( new WebhookController( new LogRepository( $this->db ) ) )->handle_webhook( $request );

		self::assertSame( 200, $response->get_status() );
		self::assertSame( 'OK', $response->get_data() );
		self::assertArrayHasKey( TMASD_OPTION_LAST_WEBHOOK_RECEIVED_AT, TMASD_Test_Environment::$options );
		self::assertArrayHasKey( TMASD_OPTION_LAST_WEBHOOK_STATUS_UPDATE_AT, TMASD_Test_Environment::$options );
	}

	public function deliveryStatuses(): array {
		return array( array( 'sent' ), array( 'delivered' ), array( 'read' ), array( 'failed' ) );
	}

	public function test_unknown_signed_status_does_not_update_logs(): void {
		$request = $this->request( 'unknown' );
		TMASD_Test_Environment::$options[ TMASD_OPTION_APP_SECRET ] = 'test-app-secret';
		$request->set_header( 'X-Hub-Signature-256', 'sha256=' . hash_hmac( 'sha256', $request->get_body(), 'test-app-secret' ) );
		$this->db->expects( self::never() )->method( 'update' );

		$response = ( new WebhookController( new LogRepository( $this->db ) ) )->handle_webhook( $request );

		self::assertSame( 200, $response->get_status() );
		self::assertArrayNotHasKey( TMASD_OPTION_LAST_WEBHOOK_STATUS_UPDATE_AT, TMASD_Test_Environment::$options );
	}

	public function test_wrong_verification_token_is_forbidden(): void {
		TMASD_Test_Environment::$options[ TMASD_OPTION_WEBHOOK_VERIFY_TOKEN ] = 'test-verify-token';
		$request = new WP_REST_Request();
		$request->set_param( 'hub.mode', 'subscribe' );
		$request->set_param( 'hub.verify_token', 'wrong-token' );

		$response = ( new WebhookController( new LogRepository( $this->db ) ) )->handle_verify( $request );

		self::assertSame( 403, $response->get_status() );
	}

	/**
	 * @dataProvider ignoredBodies
	 */
	public function test_irrelevant_signed_payloads_do_not_update_logs( array $body ): void {
		$request = new WP_REST_Request();
		$request->set_body( json_encode( $body ) );
		TMASD_Test_Environment::$options[ TMASD_OPTION_APP_SECRET ] = 'test-app-secret';
		$request->set_header( 'X-Hub-Signature-256', 'sha256=' . hash_hmac( 'sha256', $request->get_body(), 'test-app-secret' ) );
		$this->db->expects( self::never() )->method( 'update' );

		$response = ( new WebhookController( new LogRepository( $this->db ) ) )->handle_webhook( $request );

		self::assertSame( 200, $response->get_status() );
		self::assertArrayNotHasKey( TMASD_OPTION_LAST_WEBHOOK_RECEIVED_AT, TMASD_Test_Environment::$options );
	}

	public function ignoredBodies(): array {
		return array(
			'empty body'      => array( array() ),
			'missing entry'   => array( array( 'object' => 'whatsapp_business_account' ) ),
			'wrong object'    => array( array( 'object' => 'page', 'entry' => array( array() ) ) ),
		);
	}
}
