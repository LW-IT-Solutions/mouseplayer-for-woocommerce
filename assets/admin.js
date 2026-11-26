/**
 * MousePlayer for WooCommerce settings: show the consent tool list only for the blocking option,
 * keep the funnel diagram in step with the event checkboxes, check the Channel ID format while
 * typing and show that the connection check is running.
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

	// Funnel diagram: one bar per event checkbox.
	function funnel() {
		var steps = d.querySelectorAll( '.mouseplayer-wc-funnel li[data-step]' );
		var on = 0;
		for ( var i = 0; i < steps.length; i++ ) {
			var box = d.getElementById( 'mouseplayer_wc_' + steps[ i ].getAttribute( 'data-step' ) );
			var active = !! ( box && box.checked );
			on += active ? 1 : 0;
			steps[ i ].className = active ? 'is-on' : 'is-off';
		}
		var sum = d.getElementById( 'mouseplayer-wc-funnel-summary' );
		if ( sum && sum.getAttribute( 'data-text' ) ) {
			sum.textContent = sum.getAttribute( 'data-text' ).replace( '%1$d', on ).replace( '%2$d', steps.length );
		}
	}

	d.addEventListener( 'change', function ( e ) {
		if ( ! e.target ) {
			return;
		}
		if ( e.target.name === 'mouseplayer_wc_settings[consent_mode]' ) {
			update();
		}
		if ( /^mouseplayer_wc_ev_/.test( e.target.id || '' ) ) {
			funnel();
		}
	} );
	update();
	funnel();

	// Channel ID: the same rule as on saving (letters, digits, - and _, 4 to 64 characters).
	var ch = d.getElementById( 'mouseplayer_wc_channel_id' );
	var note = d.getElementById( 'mouseplayer_wc_channel_check' );
	if ( ch && note ) {
		var check = function () {
			var v = ch.value.trim();
			var bad = v !== '' && ! /^[A-Za-z0-9_-]{4,64}$/.test( v );
			note.textContent = bad ? note.getAttribute( 'data-bad' ) : '';
			ch.setAttribute( 'aria-invalid', bad ? 'true' : 'false' );
			ch.classList.toggle( 'mouseplayer-wc-invalid', bad );
		};
		ch.addEventListener( 'input', check );
		check();
	}

	// Connection check: the request runs on the server and can take some seconds.
	var form = d.querySelector( '.mouseplayer-wc-check-form' );
	if ( form ) {
		form.addEventListener( 'submit', function () {
			var b = form.querySelector( '[type=submit]' );
			if ( b && b.getAttribute( 'data-busy' ) ) {
				b.value = b.getAttribute( 'data-busy' );
				b.classList.add( 'mouseplayer-wc-busy' );
				b.setAttribute( 'aria-disabled', 'true' );
				form.setAttribute( 'aria-busy', 'true' );
				// Disable after the submit has started, so the form is still sent.
				setTimeout( function () {
					b.disabled = true;
				}, 0 );
			}
		} );
	}
}( document ) );
