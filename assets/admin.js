/**
 * MousePlayer for WooCommerce settings: show the consent tool list only for the blocking option,
 * keep the funnel diagram in step with the event checkboxes (a click on a step switches it), check
 * the Channel ID format while typing, show that the connection check is running, and keep the
 * consent time line, the masking preview, the event example and the role counter in step with the
 * form. The script sends no requests of its own.
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
			steps[ i ].classList.toggle( 'is-on', active );
			steps[ i ].classList.toggle( 'is-off', ! active );
			var bar = steps[ i ].querySelector( 'button' );
			if ( bar ) {
				bar.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
			}
		}
		var sum = d.getElementById( 'mouseplayer-wc-funnel-summary' );
		if ( sum && sum.getAttribute( 'data-text' ) ) {
			sum.textContent = sum.getAttribute( 'data-text' ).replace( '%1$d', on ).replace( '%2$d', steps.length );
		}
	}

	// Consent time line: when the script loads and when recording starts.
	var line = d.getElementById( 'mouseplayer-wc-timeline' );
	function timeline() {
		var checked = d.querySelector( 'input[name="mouseplayer_wc_settings[consent_mode]"]:checked' );
		if ( ! line || ! checked ) {
			return;
		}
		line.setAttribute( 'data-mode', checked.value );
		var text = d.getElementById( 'mouseplayer-wc-tl-text' );
		var sentence = text ? text.getAttribute( 'data-' + checked.value.replace( /_/g, '-' ) ) : '';
		if ( text && sentence ) {
			text.textContent = sentence;
		}
	}

	// Masking preview and event example follow their checkboxes.
	function flag( id ) {
		var box = d.getElementById( 'mouseplayer_wc_' + id );
		return !! ( box && box.checked );
	}
	function previews() {
		var mask = d.getElementById( 'mouseplayer-wc-mask' );
		if ( mask ) {
			mask.classList.toggle( 'mask-checkout', flag( 'mask_checkout' ) );
			mask.classList.toggle( 'mask-cart', flag( 'mask_cart' ) );
			mask.classList.toggle( 'ignore-typing', flag( 'ignore_typing' ) );
		}
		var load = d.getElementById( 'mouseplayer-wc-payload' );
		if ( load ) {
			load.classList.toggle( 'send-value', flag( 'send_value' ) );
			load.classList.toggle( 'send-context', flag( 'send_context' ) );
		}
	}

	// Accounts that are never recorded: the sum of the excluded roles.
	var roleText = d.getElementById( 'mouseplayer-wc-roles-text' );
	function roles() {
		var meter = roleText ? roleText.closest( '.mouseplayer-wc-roles-meter' ) : null;
		if ( ! meter ) {
			return;
		}
		var total = parseInt( meter.getAttribute( 'data-total' ), 10 ) || 0;
		var sum = 0;
		var boxes = d.querySelectorAll( '.mouseplayer-wc-role input[data-count]' );
		for ( var i = 0; i < boxes.length; i++ ) {
			if ( boxes[ i ].checked ) {
				sum += parseInt( boxes[ i ].getAttribute( 'data-count' ), 10 ) || 0;
			}
		}
		sum = Math.min( sum, total );
		var fill = meter.querySelector( '.mouseplayer-wc-meter-fill' );
		if ( fill ) {
			fill.style.width = ( total ? Math.round( sum / total * 100 ) : 0 ) + '%';
		}
		var nf = window.Intl && Intl.NumberFormat ? new Intl.NumberFormat( d.documentElement.lang || undefined ) : null;
		roleText.textContent = roleText.getAttribute( 'data-text' )
			.replace( '%1$s', nf ? nf.format( sum ) : sum )
			.replace( '%2$s', nf ? nf.format( total ) : total );
	}

	d.addEventListener( 'change', function ( e ) {
		if ( ! e.target ) {
			return;
		}
		if ( e.target.name === 'mouseplayer_wc_settings[consent_mode]' ) {
			update();
			timeline();
		}
		if ( /^mouseplayer_wc_ev_/.test( e.target.id || '' ) ) {
			funnel();
		}
		if ( /^mouseplayer_wc_(mask_checkout|mask_cart|ignore_typing|send_value|send_context)$/.test( e.target.id || '' ) ) {
			previews();
		}
		if ( e.target.closest && e.target.closest( '.mouseplayer-wc-role' ) ) {
			roles();
		}
	} );
	update();
	funnel();
	timeline();
	previews();
	roles();

	// A click on a funnel step switches its checkbox (buttons are disabled without JavaScript).
	var bars = d.querySelectorAll( '.mouseplayer-wc-funnel li[data-step] button' );
	for ( var b = 0; b < bars.length; b++ ) {
		bars[ b ].disabled = false;
		bars[ b ].addEventListener( 'click', function () {
			var box = d.getElementById( 'mouseplayer_wc_' + this.parentNode.getAttribute( 'data-step' ) );
			if ( box ) {
				box.checked = ! box.checked;
				box.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
		} );
	}

	// One tooltip at a time: the step of the latest pointer or keyboard move. Leaving with the
	// pointer falls back to the focused step, leaving with the focus to the step under the pointer.
	// Esc hides it (WCAG 1.4.13) until the pointer or focus moves to another step.
	var tipList = d.querySelector( '.mouseplayer-wc-funnel' );
	if ( tipList ) {
		var hovered = null;
		var focused = null;
		var showTip = function ( li ) {
			var items = tipList.querySelectorAll( 'li[data-step]' );
			for ( var t = 0; t < items.length; t++ ) {
				items[ t ].classList.toggle( 'tip-on', items[ t ] === li );
			}
		};
		var stepOf = function ( el ) {
			return el && el.closest ? el.closest( 'li[data-step]' ) : null;
		};
		tipList.classList.add( 'has-tips' );
		tipList.addEventListener( 'mouseover', function ( e ) {
			var li = stepOf( e.target );
			if ( li && li !== hovered ) {
				hovered = li;
				showTip( li );
			}
		} );
		tipList.addEventListener( 'mouseleave', function () {
			hovered = null;
			showTip( focused );
		} );
		tipList.addEventListener( 'focusin', function ( e ) {
			focused = stepOf( e.target );
			showTip( focused );
		} );
		tipList.addEventListener( 'focusout', function () {
			focused = null;
			showTip( hovered );
		} );
		tipList.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key ) {
				showTip( null );
			}
		} );
	}

	// The funnel bars grow once when they come into view (not with reduced motion).
	var funnelBox = d.querySelector( '.mouseplayer-wc-funnel' );
	var still = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	if ( funnelBox && ! still && 'IntersectionObserver' in window ) {
		funnelBox.classList.add( 'is-waiting' );
		var seen = new IntersectionObserver( function ( entries ) {
			if ( entries[ 0 ].isIntersecting ) {
				funnelBox.classList.remove( 'is-waiting' );
				funnelBox.classList.add( 'is-growing' );
				seen.disconnect();
			}
		}, { threshold: 0.3 } );
		seen.observe( funnelBox );
	}

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
