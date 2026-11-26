/**
 * MousePlayer for WooCommerce: hands shop events, page context and consent to MousePlayer.
 *
 * Events wait in this page until MousePlayer reports that the recording has started
 * (event "mouseplayer:ready", which only happens after consent). Without consent they are
 * dropped with the page and never leave the browser.
 */
( function ( w, d ) {
	'use strict';

	var cfg = w.mouseplayerWc || {};
	var SENT_KEY = 'mouseplayer_wc_sent';
	var waiting = [];
	// IDs of events that wait in this page, so that a refreshed cart fragment does not queue them twice.
	var queued = {};

	function ready() {
		return !! ( w.MousePlayer && typeof w.MousePlayer.trackEvent === 'function' );
	}

	// Hands an event to MousePlayer, or keeps it in this page until the recording has started.
	// Only an event that was handed over is remembered in the session storage (see remember()).
	function track( name, params, id ) {
		if ( ready() ) {
			try {
				w.MousePlayer.trackEvent( name, params );
				if ( id ) {
					remember( id );
				}
			} catch ( e ) {}
		} else {
			waiting.push( [ name, params, id ] );
		}
	}

	function flush() {
		if ( ! ready() ) {
			return;
		}
		var list = waiting;
		waiting = [];
		for ( var i = 0; i < list.length; i++ ) {
			track( list[ i ][ 0 ], list[ i ][ 1 ], list[ i ][ 2 ] );
		}
	}

	w.addEventListener( 'mouseplayer:ready', flush );

	// Consent goes through the documented queue, which MousePlayer reads before and after loading.
	function consent( value ) {
		try {
			( w.mp_q = w.mp_q || [] ).push( [ 'consent', value ] );
		} catch ( e ) {}
	}

	// IDs of events already handed over to MousePlayer in this tab (session storage, key mouseplayer_wc_sent,
	// at most 50), so that a refreshed cart fragment or a reload is not sent twice. Written only after
	// MousePlayer has started recording, that is after consent.
	function sentIds() {
		try {
			return JSON.parse( w.sessionStorage.getItem( SENT_KEY ) || '[]' ) || [];
		} catch ( e ) {
			return [];
		}
	}

	function remember( id ) {
		try {
			var ids = sentIds();
			ids.push( id );
			w.sessionStorage.setItem( SENT_KEY, JSON.stringify( ids.slice( -50 ) ) );
		} catch ( e ) {}
	}

	function send( list ) {
		if ( ! list || ! list.length ) {
			return;
		}
		var ids = sentIds();
		for ( var i = 0; i < list.length; i++ ) {
			var ev = list[ i ];
			if ( ! ev || typeof ev.n !== 'string' ) {
				continue;
			}
			if ( ev.id && ( ids.indexOf( ev.id ) !== -1 || queued[ ev.id ] ) ) {
				continue;
			}
			if ( ev.id ) {
				queued[ ev.id ] = true;
			}
			track( ev.n, ev.p || {}, ev.id || '' );
		}
	}

	function sendPending() {
		var el = d.querySelector( 'div.mouseplayer-wc-pending' );
		if ( ! el ) {
			return;
		}
		try {
			send( JSON.parse( el.getAttribute( 'data-events' ) || '[]' ) );
		} catch ( e ) {}
	}

	// 1. Consent from the WP Consent API (statistics category).
	// Without a consent type no consent tool is active, and the API then answers "yes" for everything.
	// That is not a decision of the visitor, so it is not passed on: MousePlayer keeps waiting.
	if ( cfg.consentApi ) {
		var askApi = function () {
			var type = w.wp_consent_type || w.wp_fallback_consent_type || '';
			if ( typeof w.wp_has_consent === 'function' && type ) {
				consent( !! w.wp_has_consent( 'statistics' ) );
			}
		};
		d.addEventListener( 'wp_listen_for_consent_change', function ( e ) {
			var c = e && e.detail;
			if ( c && Object.prototype.hasOwnProperty.call( c, 'statistics' ) ) {
				consent( c.statistics === 'allow' );
			}
		} );
		if ( d.readyState === 'loading' ) {
			d.addEventListener( 'DOMContentLoaded', askApi );
		} else {
			askApi();
		}
		// Some consent tools set the consent type only after their own script has loaded.
		w.addEventListener( 'load', askApi );
	}

	// 2. Events of this page view.
	send( cfg.events );

	// 3. add_to_cart without page reload: the shop sends the event with the cart fragments.
	if ( cfg.addToCart && w.jQuery ) {
		w.jQuery( d.body ).on( 'added_to_cart wc_fragments_refreshed', function () {
			// WooCommerce replaces the fragment in the page right after this event.
			w.setTimeout( sendPending, 0 );
		} );
	}

	// 4. select_item: click on a product in a product list (classic templates and blocks).
	if ( cfg.selectItem ) {
		d.addEventListener( 'click', function ( e ) {
			var a = e.target && e.target.closest ? e.target.closest( 'a' ) : null;
			if ( ! a || a.closest( '.add_to_cart_button, .wp-block-button, .wc-block-components-product-button' ) ) {
				return;
			}
			var item = a.closest( 'ul.products li.product, .wc-block-product, .wc-block-grid__product' );
			if ( ! item ) {
				return;
			}
			var m = /(?:^|\s)post-(\d+)(?:\s|$)/.exec( item.className || '' );
			var t = item.querySelector( '.woocommerce-loop-product__title, .wp-block-post-title, .wc-block-grid__product-title' );
			var p = { item_name: ( t ? t.textContent : '' ).replace( /\s+/g, ' ' ).trim().slice( 0, 120 ) };
			if ( m ) {
				p.item_id = m[ 1 ];
			}
			track( 'select_item', p );
		}, true );
	}

	// 5. Page context, once the recording has started.
	if ( cfg.context && typeof cfg.context === 'object' && Object.keys( cfg.context ).length ) {
		var setContext = function () {
			try {
				if ( w.MousePlayer && typeof w.MousePlayer.setContext === 'function' ) {
					w.MousePlayer.setContext( cfg.context );
				}
			} catch ( e ) {}
		};
		w.addEventListener( 'mouseplayer:ready', setContext );
		if ( w.__mpBus && w.__mpBus[ 'mouseplayer:ready' ] ) {
			setContext();
		}
	}

	// 6. Typing in checkout, address and account forms is not recorded at all - classic and block checkout,
	// login, registration, lost password, account details, addresses and order tracking - and in every
	// masked area. Independent of the masking setting.
	if ( cfg.ignoreTyped ) {
		var ROOTS = [
			'[data-mp-mask]',
			'form.checkout',
			'.wc-block-checkout',
			'.wp-block-woocommerce-checkout',
			'.woocommerce-form-login',
			'.woocommerce-form-register',
			'.woocommerce-ResetPassword',
			'.woocommerce-EditAccountForm',
			'.woocommerce-address-fields',
			'.woocommerce-form-track-order'
		];
		var SEL = ROOTS.map( function ( r ) {
			return r + ' input, ' + r + ' textarea, ' + r + ' select';
		} ).join( ', ' );
		var mark = function ( root ) {
			var list = ( root || d ).querySelectorAll( SEL );
			for ( var i = 0; i < list.length; i++ ) {
				if ( ! list[ i ].hasAttribute( 'data-mp-ignore' ) ) {
					list[ i ].setAttribute( 'data-mp-ignore', '1' );
				}
			}
		};
		var start = function () {
			mark( d );
			if ( typeof w.MutationObserver === 'function' && d.querySelector( ROOTS.join( ', ' ) ) ) {
				var waiting = false;
				new w.MutationObserver( function () {
					if ( waiting ) {
						return;
					}
					waiting = true;
					w.setTimeout( function () {
						waiting = false;
						mark( d );
					}, 50 );
				} ).observe( d.body, { childList: true, subtree: true } );
			}
		};
		// Before the input event: fields that appear between two observer runs.
		d.addEventListener( 'focusin', function ( e ) {
			var el = e.target;
			if ( el && el.matches && el.matches( SEL ) && ! el.hasAttribute( 'data-mp-ignore' ) ) {
				el.setAttribute( 'data-mp-ignore', '1' );
			}
		}, true );
		if ( d.readyState === 'loading' ) {
			d.addEventListener( 'DOMContentLoaded', start );
		} else {
			start();
		}
	}
}( window, document ) );
