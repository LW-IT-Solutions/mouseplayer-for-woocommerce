/**
 * MousePlayer for WooCommerce settings: show the consent tool list only for the blocking option.
 */
( function ( d ) {
	'use strict';

	function update() {
		var row = d.querySelector( 'tr.mouseplayer-wc-cmp-row' );
		var checked = d.querySelector( 'input[name="mouseplayer_wc_settings[consent_mode]"]:checked' );
		if ( row ) {
			row.hidden = ! checked || checked.value !== 'cmp_block';
		}
	}

	d.addEventListener( 'change', function ( e ) {
		if ( e.target && e.target.name === 'mouseplayer_wc_settings[consent_mode]' ) {
			update();
		}
	} );
	update();
}( document ) );
