<?php

declare(strict_types=1);

final class QueueServiceTest extends PluginTestCase {
	/**
	 * @dataProvider consentCases
	 */
	public function test_consent_is_enforced( array $meta, ?array $consent, bool $allowed ): void {
		TMASD_Test_Environment::$options[ TMASD_OPTION_REQUIRE_CONSENT ] = true;
		TMASD_Test_Environment::$order = new WC_Order( $meta );
		$this->consent = $consent;
		$this->stubPayload();

		if ( $allowed ) {
			$this->expectSuccessfulSend();
		} else {
			$this->api->expects( self::never() )->method( 'send_template_message' );
		}

		$this->queue->handle_send_template_message( 42, 'order_status_processing' );

		self::assertCount( 1, $this->logs );
		if ( $allowed ) {
			self::assertSame( 'sent', $this->updates[0]['status'] );
			self::assertSame( 'test-message-id', $this->updates[0]['wa_message_id'] );
		} else {
			self::assertSame( 'failed', $this->logs[0]['status'] );
			self::assertStringContainsString( 'Skipped:', $this->logs[0]['error_message'] );
			self::assertSame( array(), $this->updates );
		}
		self::assertSame( array(), TMASD_Test_Environment::$scheduled );
	}

	public function consentCases(): array {
		return array(
			'no consent'                => array( array(), null, false ),
			'phone opted out'           => array( array(), array( 'consent' => 0 ), false ),
			'phone opted in'            => array( array(), array( 'consent' => 1 ), true ),
			'classic opted in'          => array( array( '_tmasd_whatsapp_optin' => 'yes' ), null, true ),
			'block opted in'            => array( array( '_wc_other/tmasd/whatsapp-optin' => '1' ), null, true ),
			'classic denial overrides'  => array( array( '_tmasd_whatsapp_optin' => 'no' ), array( 'consent' => 1 ), false ),
			'block denial overrides'    => array( array( '_wc_other/tmasd/whatsapp-optin' => '0', '_tmasd_whatsapp_optin' => 'yes' ), array( 'consent' => 1 ), false ),
		);
	}

	public function test_consent_setting_can_be_disabled(): void {
		TMASD_Test_Environment::$options[ TMASD_OPTION_REQUIRE_CONSENT ] = false;
		TMASD_Test_Environment::$order = new WC_Order( array( '_tmasd_whatsapp_optin' => 'no' ) );
		$this->stubPayload();
		$this->expectSuccessfulSend();

		$this->queue->handle_send_template_message( 42, 'order_status_processing' );

		self::assertSame( 'sent', $this->updates[0]['status'] );
	}

	/**
	 * @dataProvider retryCases
	 */
	public function test_retry_policy( int $http_code, int $attempt, bool $retry ): void {
		$this->stubPayload();
		$this->api->expects( self::once() )->method( 'send_template_message' )->willReturn(
			array(
				'success'   => false,
				'http_code' => $http_code,
				'error'     => 'Mock API failure',
				'payload'   => array(),
				'response'  => array(),
			)
		);

		$this->queue->handle_send_template_message( 42, 'order_status_processing', $attempt );

		self::assertSame( 'failed', $this->updates[0]['status'] );
		self::assertSame( 'Mock API failure', $this->updates[0]['error_message'] );
		self::assertSame(
			$retry ? array( array( TMASD_ACTION_SEND_TEMPLATE, array( 42, 'order_status_processing', $attempt + 1 ), 'tmasd' ) ) : array(),
			TMASD_Test_Environment::$scheduled
		);
	}

	public function retryCases(): array {
		return array(
			'network error'  => array( 0, 0, true ),
			'rate limit'     => array( 429, 0, true ),
			'server error'   => array( 503, 2, true ),
			'client error'   => array( 400, 0, false ),
			'auth error'     => array( 401, 0, false ),
			'retry ceiling'  => array( 429, 3, false ),
		);
	}

	public function test_missing_rule_does_not_send(): void {
		$this->mapping = null;
		$this->mapper->expects( self::never() )->method( 'build_from_order' );
		$this->api->expects( self::never() )->method( 'send_template_message' );

		$this->queue->handle_send_template_message( 42, 'order_status_processing' );

		self::assertStringContainsString( 'No enabled dispatch rule', $this->logs[0]['error_message'] );
	}

	public function test_invalid_order_does_not_send(): void {
		$this->db->expects( self::never() )->method( 'get_row' );
		$this->api->expects( self::never() )->method( 'send_template_message' );

		$this->queue->handle_send_template_message( 0, 'order_status_processing' );

		self::assertStringContainsString( 'Invalid request', $this->logs[0]['error_message'] );
	}

	public function test_invalid_recipient_does_not_send(): void {
		$this->mapper->method( 'build_from_order' )->willReturn(
			array( 'phone_e164' => '', 'template_name' => 'order_update', 'language' => 'en_US', 'variables' => array() )
		);
		$this->api->expects( self::never() )->method( 'send_template_message' );

		$this->queue->handle_send_template_message( 42, 'order_status_processing' );

		self::assertStringContainsString( 'no valid billing phone', $this->logs[0]['error_message'] );
		self::assertSame( array(), TMASD_Test_Environment::$scheduled );
	}

	public function test_order_event_schedules_matching_rule(): void {
		$this->queue->handle_order_status_changed( 42, 'pending', 'processing', new WC_Order() );

		self::assertSame(
			array( array( TMASD_ACTION_SEND_TEMPLATE, array( 42, 'order_status_processing', 0 ), 'tmasd' ) ),
			TMASD_Test_Environment::$scheduled
		);
	}

	public function test_unmapped_order_status_does_not_schedule(): void {
		$this->db->expects( self::never() )->method( 'get_row' );
		$this->queue->handle_order_status_changed( 42, 'pending', 'refunded', new WC_Order() );

		self::assertSame( array(), TMASD_Test_Environment::$scheduled );
	}
}
