/**
 * MousePlayer for WooCommerce: adds the visitor ID of this browser to the [mouseplayer_privacy_link] links.
 * Without a recording there is no visitor ID, and the link leads to the general data access page.
 */
( function ( w, d ) {
	'use strict';

	function update() {
		var url = null;
		try {
			if ( w.MousePlayer && typeof w.MousePlayer.privacyUrl === 'function' ) {
				url = w.MousePlayer.privacyUrl();
			}
		} catch ( e ) {}
		if ( ! url || ! /^https:\/\/www\.mouseplayer\.com\//.test( url ) ) {
			return;
		}
		var links = d.querySelectorAll( 'a.mouseplayer-privacy-link' );
		for ( var i = 0; i < links.length; i++ ) {
			links[ i ].href = url;
		}
	}

	w.addEventListener( 'mouseplayer:ready', update );
	if ( d.readyState === 'loading' ) {
		d.addEventListener( 'DOMContentLoaded', update );
	} else {
		update();
	}
}( window, document ) );
