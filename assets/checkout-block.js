/**
 * Signals Dispatch — Block Checkout WhatsApp Opt-In
 *
 * Renders a "Send me order updates on WhatsApp" checkbox
 * inside the WooCommerce Blocks checkout.
 */
( function () {
	'use strict';

	var wc = window.wc || {};
	var blocksCheckout = wc.blocksCheckout || {};
	var element = window.wp && window.wp.element ? window.wp.element : null;
	var components = wc.blocksComponents || {};
	var settings = ( wc.wcSettings && typeof wc.wcSettings.getSetting === 'function' )
		? wc.wcSettings.getSetting( 'tmasd-whatsapp-optin_data', {} )
		: {};

	var label = ( settings && settings.label )
		? settings.label
		: ( window.tmasdCheckout && window.tmasdCheckout.label
			? window.tmasdCheckout.label
			: 'Send me order updates on WhatsApp' );

	if ( ! element || ! blocksCheckout || typeof blocksCheckout.registerCheckoutBlock === 'undefined' ) {
		// WC Blocks API not available — skip silently.
		return;
	}

	var createElement = element.createElement;
	var useState = element.useState;

	/**
	 * Block component rendered inside the checkout.
	 */
	function TmasdOptinBlock( props ) {
		var checkoutExtensionData = props.checkoutExtensionData || {};
		var setExtensionData = checkoutExtensionData.setExtensionData || function () {};

		var optinState = useState( false );
		var isChecked = optinState[0];
		var setIsChecked = optinState[1];

		function handleChange( val ) {
			setIsChecked( val );
			setExtensionData( 'tmasd-whatsapp-optin', 'optin', val );
		}

		// Use WooCommerce CheckboxControl if available, otherwise plain HTML.
		if ( components && components.CheckboxControl ) {
			return createElement( components.CheckboxControl, {
				id: 'tmasd-whatsapp-optin',
				checked: isChecked,
				onChange: handleChange,
				children: label
			} );
		}

		return createElement( 'p', { className: 'tmasd-block-optin' },
			createElement( 'label', null,
				createElement( 'input', {
					type: 'checkbox',
					checked: isChecked,
					onChange: function ( e ) { handleChange( e.target.checked ); }
				} ),
				' ',
				label
			)
		);
	}

	blocksCheckout.registerCheckoutBlock( {
		metadata: {
			name: 'tmasd/whatsapp-optin',
			parent: [ 'woocommerce/checkout-actions-block' ]
		},
		component: function ( props ) {
			return createElement( TmasdOptinBlock, props );
		}
	} );
} )();
