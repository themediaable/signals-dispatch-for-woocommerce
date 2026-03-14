/**
 * Signals Dispatch admin scripts.
 *
 * @package TMASD\Signals\Dispatch
 */

( function () {
	'use strict';

	/* -- Persistent notices: show only if not previously dismissed -- */
	document.querySelectorAll( '.tmasd-notice[data-dismiss-key]' ).forEach( function ( notice ) {
		var key = 'tmasd_dismissed_' + notice.getAttribute( 'data-dismiss-key' );
		if ( ! localStorage.getItem( key ) ) {
			notice.style.display = '';
		}
	} );

	/* -- Dismiss handler -- */
	document.addEventListener( 'click', function ( e ) {
		if ( ! e.target.closest( '.tmasd-notice-dismiss' ) ) {
			return;
		}

		var notice = e.target.closest( '.tmasd-notice' );
		if ( ! notice ) {
			return;
		}

		/* Persist dismissal for keyed notices */
		var dismissKey = notice.getAttribute( 'data-dismiss-key' );
		if ( dismissKey ) {
			localStorage.setItem( 'tmasd_dismissed_' + dismissKey, '1' );
		}

		notice.style.transition = 'opacity 0.2s';
		notice.style.opacity = '0';
		setTimeout( function () {
			notice.remove();
		}, 200 );
	} );

	/* -- Clean action query params so notices don't reappear on refresh -- */
	if ( window.history.replaceState ) {
		var url = new URL( window.location.href );
		var cleaned = false;
		[ 'deleted', 'purged' ].forEach( function ( param ) {
			if ( url.searchParams.has( param ) ) {
				url.searchParams.delete( param );
				cleaned = true;
			}
		} );
		if ( cleaned ) {
			window.history.replaceState( {}, '', url.toString() );
		}
	}
} )();
