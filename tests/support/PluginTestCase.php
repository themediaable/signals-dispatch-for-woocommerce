<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TMASD\Signals\Dispatch\Contracts\ApiClientInterface;
use TMASD\Signals\Dispatch\Contracts\TemplateMapperInterface;
use TMASD\Signals\Dispatch\Database\LogRepository;
use TMASD\Signals\Dispatch\Database\MappingRepository;
use TMASD\Signals\Dispatch\Database\OptinRepository;
use TMASD\Signals\Dispatch\Queue\QueueService;

abstract class PluginTestCase extends TestCase {
	protected wpdb $db;
	protected ApiClientInterface $api;
	protected TemplateMapperInterface $mapper;
	protected QueueService $queue;
	protected array $logs;
	protected array $updates;
	protected ?array $mapping;
	protected ?array $consent;

	protected function setUp(): void {
		TMASD_Test_Environment::reset();
		$this->logs    = array();
		$this->updates = array();
		$this->mapping = array(
			'id'            => 1,
			'enabled'       => 1,
			'event_key'     => 'order_status_processing',
			'template_name' => 'order_update',
		);
		$this->consent = null;
		$this->db = $this->getMockBuilder( wpdb::class )
			->onlyMethods( array( 'get_row', 'insert', 'update' ) )
			->getMock();
		$this->db->method( 'get_row' )->willReturnCallback(
			function ( string $query ): ?array {
				if ( false !== strpos( $query, 'tmasd_template_map' ) ) {
					return $this->mapping;
				}
				if ( false !== strpos( $query, 'tmasd_optins' ) ) {
					return $this->consent;
				}
				if ( false !== strpos( $query, 'tmasd_logs' ) ) {
					$log = end( $this->logs );
					return $log ? array_merge( $log, end( $this->updates ) ?: array() ) : null;
				}
				throw new LogicException( 'Unexpected query: ' . $query );
			}
		);
		$this->db->method( 'insert' )->willReturnCallback(
			function ( string $table, array $data ): int {
				self::assertSame( 'wp_tmasd_logs', $table );
				$this->logs[] = $data;
				return 1;
			}
		);
		$this->db->method( 'update' )->willReturnCallback(
			function ( string $table, array $data, array $where ): int {
				self::assertSame( 'wp_tmasd_logs', $table );
				$this->updates[] = $data;
				return 1;
			}
		);
		$this->api    = $this->createMock( ApiClientInterface::class );
		$this->mapper = $this->createMock( TemplateMapperInterface::class );
		$this->queue  = new QueueService(
			new LogRepository( $this->db ),
			new MappingRepository( $this->db ),
			new OptinRepository( $this->db ),
			$this->api,
			$this->mapper
		);
	}

	protected function stubPayload(): void {
		$this->mapper->method( 'build_from_order' )->willReturn(
			array(
				'phone_e164'    => '+15555550100',
				'template_name' => 'order_update',
				'language'      => 'en_US',
				'variables'     => array( 'Test Customer' ),
			)
		);
	}

	protected function expectSuccessfulSend(): void {
		$this->api->expects( self::once() )->method( 'send_template_message' )
			->with( '+15555550100', 'order_update', 'en_US', array( 'Test Customer' ) )
			->willReturn(
				array(
					'success'  => true,
					'payload'  => array(),
					'response' => array( 'messages' => array( array( 'id' => 'test-message-id' ) ) ),
				)
			);
	}
}
