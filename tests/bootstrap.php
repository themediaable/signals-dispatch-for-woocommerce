<?php

declare(strict_types=1);

if ( defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'Run these isolated tests without loading a live WordPress site.' );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );

require __DIR__ . '/support/wordpress.php';
require dirname( __DIR__ ) . '/signals-dispatch-for-woocommerce.php';
require __DIR__ . '/support/PluginTestCase.php';
