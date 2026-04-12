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

	/* -- Toggle rows (log detail / FAQ accordion) -- */
	document.addEventListener( 'click', function ( e ) {
		var toggle = e.target.closest( '[data-toggle-row]' );
		if ( toggle ) {
			e.preventDefault();
			var targetId = toggle.getAttribute( 'data-toggle-row' );
			var target = document.getElementById( targetId );
			if ( target ) {
				target.style.display = target.style.display === 'table-row' ? 'none' : 'table-row';
			}
			return;
		}

		var faqHeader = e.target.closest( '[data-faq-target]' );
		if ( faqHeader ) {
			var faqItem = faqHeader.closest( '.tmasd-faq-item' );
			if ( faqItem ) {
				faqItem.classList.toggle( 'is-open' );
			}
			return;
		}
	} );

	/* -- Confirm prompts on delete actions -- */
	document.addEventListener( 'click', function ( e ) {
		var confirmEl = e.target.closest( '[data-confirm]' );
		if ( ! confirmEl ) {
			return;
		}
		var message = confirmEl.getAttribute( 'data-confirm' );
		if ( message && ! window.confirm( message ) ) { // eslint-disable-line no-alert
			e.preventDefault();
		}
	} );

	/* -- Refresh Status (delegated from Logs table) -- */
	document.addEventListener( 'click', function ( e ) {
		var refreshLink = e.target.closest( '.tmasd-action-refresh[data-log-id]' );
		if ( ! refreshLink ) {
			return;
		}
		e.preventDefault();
		var logId = refreshLink.getAttribute( 'data-log-id' );
		if ( typeof window.tmasdRefreshStatus === 'function' ) {
			window.tmasdRefreshStatus( refreshLink, parseInt( logId, 10 ) );
		}
	} );

	/* -- Manual Send meta box (Order page) -- */
	if ( typeof window.tmasdManualSend !== 'undefined' ) {
		var cfg = window.tmasdManualSend;
		var btn = document.getElementById( 'tmasd-send-btn' );
		var select = document.getElementById( 'tmasd_mapping_id' );
		var noticeBox = document.getElementById( 'tmasd-send-notice' );

		if ( btn && select && noticeBox ) {
			btn.addEventListener( 'click', function () {
				btn.disabled = true;
				btn.textContent = cfg.i18n.sending;
				noticeBox.textContent = '';

				var data = new FormData();
				data.append( 'action', 'tmasd_manual_send' );
				data.append( 'order_id', cfg.orderId );
				data.append( 'mapping_id', select.value );
				data.append( '_ajax_nonce', cfg.nonce );

				fetch( cfg.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: data
				} )
					.then( function ( r ) {
						return r.json();
					} )
					.then( function ( res ) {
						var cls = res.success ? 'notice-success' : 'notice-error';
						tmasdShowNotice( noticeBox, cls, res.data.message );
						btn.disabled = false;
						btn.textContent = cfg.i18n.send;
					} )
					.catch( function () {
						tmasdShowNotice( noticeBox, 'notice-error', cfg.i18n.failed );
						btn.disabled = false;
						btn.textContent = cfg.i18n.send;
					} );
			} );
		}
	}

	/**
	 * Show an inline admin notice inside a container.
	 *
	 * @param {Element} container Target container element.
	 * @param {string}  cls       Notice CSS class (e.g. 'notice-success').
	 * @param {string}  message   Notice text.
	 */
	function tmasdShowNotice( container, cls, message ) {
		container.textContent = '';
		var div = document.createElement( 'div' );
		div.className = 'notice ' + cls + ' inline';
		var p = document.createElement( 'p' );
		p.textContent = message;
		div.appendChild( p );
		container.appendChild( div );
	}

	/* -- Refresh Status (Logs page) -- */
	if ( typeof window.tmasdRefresh !== 'undefined' ) {
		window.tmasdRefreshStatus = function ( link, logId ) {
			var rcfg = window.tmasdRefresh;
			var origText = link.textContent;
			link.textContent = rcfg.i18n.refreshing;
			link.style.pointerEvents = 'none';
			var formData = new FormData();
			formData.append( 'action', 'tmasd_refresh_status' );
			formData.append( 'log_id', logId );
			formData.append( '_ajax_nonce', rcfg.nonce );
			fetch( rcfg.ajaxUrl, { method: 'POST', body: formData } )
				.then( function ( r ) {
					return r.json();
				} )
				.then( function ( data ) {
					if ( data.success ) {
						var row = link.closest( 'tr' );
						var badge = row.querySelector( '.tmasd-badge' );
						if ( badge ) {
							var oldStatus = badge.textContent.trim();
							badge.textContent = data.data.status;
							badge.className = 'tmasd-badge tmasd-badge--' + data.data.status;
							if ( oldStatus !== data.data.status ) {
								link.textContent = rcfg.i18n.updated;
							} else {
								link.textContent = rcfg.i18n.noChange;
							}
						}
					} else {
						link.textContent = rcfg.i18n.error;
					}
					link.style.pointerEvents = '';
					setTimeout( function () {
						link.textContent = origText;
					}, 2000 );
				} )
				.catch( function () {
					link.textContent = rcfg.i18n.error;
					link.style.pointerEvents = '';
					setTimeout( function () {
						link.textContent = origText;
					}, 2000 );
				} );
		};
	}
} )();
