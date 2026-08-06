<?php
/**
 * Skript-Einbindung und Emission der Ecommerce-Events.
 *
 * @package MousePlayer_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Haengt sich in WooCommerce ein und schiebt Events in die mp_q Queue.
 */
class MousePlayer_WC_Tracker {

	/**
	 * Events, die in dieser Anfrage noch ausgegeben werden muessen.
	 *
	 * @var array
	 */
	private $queue = array();

	/**
	 * Hooks registrieren.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'print_bootstrap' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_script' ) );

		// Ecommerce-Hooks nur, wenn WooCommerce wirklich laeuft.
		add_action(
			'wp',
			function () {
				if ( ! class_exists( 'WooCommerce' ) || ! mouseplayer_wc_is_active() ) {
					return;
				}
				$this->collect_page_events();
			}
		);

		add_action( 'woocommerce_thankyou', array( $this, 'on_thankyou' ), 10, 1 );
		add_action( 'wp_footer', array( $this, 'print_queue' ), 99 );
	}

	/**
	 * mp_config und die Pre-Load-Queue MUESSEN vor mp.js stehen.
	 */
	public function print_bootstrap() {
		if ( ! mouseplayer_wc_is_active() ) {
			return;
		}

		$s      = mouseplayer_wc_get_settings();
		$config = array( 'channel' => (string) $s['channel_id'] );

		// Optionale Identitaet: nie die rohe E-Mail, sondern ein stabiler Hash.
		if ( ! empty( $s['identify_customers'] ) && is_user_logged_in() ) {
			$config['user_id'] = 'wp_' . substr( hash( 'sha256', get_current_user_id() . '|' . wp_salt( 'nonce' ) ), 0, 32 );
		}

		echo "<script id=\"mouseplayer-bootstrap\">";
		echo 'window.mp_config=' . wp_json_encode( $config ) . ';';
		echo 'window.mp_q=window.mp_q||[];';
		echo 'window.mp=window.mp||function(){(window.mp.q=window.mp.q||[]).push(arguments);};';
		echo '</script>';
	}

	/**
	 * mp.js einbinden - ueber wp_enqueue_script, nicht als handgeschriebenes Tag.
	 */
	public function enqueue_script() {
		if ( ! mouseplayer_wc_is_active() ) {
			return;
		}

		$s   = mouseplayer_wc_get_settings();
		$src = 'https://' . $s['host'] . '/mp.js';

		wp_enqueue_script( 'mouseplayer', esc_url_raw( $src ), array(), MOUSEPLAYER_WC_VERSION, false );
	}

	/**
	 * Event in die Ausgabewarteschlange legen.
	 *
	 * @param string $name   Eventname.
	 * @param array  $params Parameter.
	 */
	private function push( $name, $params = array() ) {
		$this->queue[] = array(
			'name'   => (string) $name,
			'params' => is_array( $params ) ? $params : array(),
		);
	}

	/**
	 * Preis/Waehrung nur anhaengen, wenn erlaubt.
	 * MousePlayer erwartet 'price' und 'currency' - daran haengt die Umsatzanzeige.
	 *
	 * @param array $params Bestehende Parameter.
	 * @param float $value  Betrag.
	 * @return array
	 */
	private function with_value( $params, $value ) {
		$s = mouseplayer_wc_get_settings();
		if ( empty( $s['send_value'] ) ) {
			return $params;
		}
		$params['price']    = round( (float) $value, 2 );
		$params['currency'] = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';
		return $params;
	}

	/**
	 * Seitenabhaengige Events einsammeln.
	 */
	private function collect_page_events() {
		$s = mouseplayer_wc_get_settings();

		// view_item_list - Shop, Kategorie, Tag, Suche.
		if ( ! empty( $s['ev_view_item_list'] ) && ( is_shop() || is_product_category() || is_product_tag() || is_search() ) ) {
			$this->push(
				'view_item_list',
				array(
					'list_name' => $this->current_list_name(),
				)
			);
		}

		// view_item - Produktdetailseite.
		if ( ! empty( $s['ev_view_item'] ) && is_product() ) {
			$product = wc_get_product( get_queried_object_id() );
			if ( $product ) {
				$this->push(
					'view_item',
					$this->with_value(
						array(
							'item_id'   => (string) $product->get_id(),
							'item_name' => $product->get_name(),
							'sku'       => (string) $product->get_sku(),
						),
						$product->get_price()
					)
				);
			}
		}

		// begin_checkout - Checkout, aber NICHT die Danke-Seite.
		if ( ! empty( $s['ev_begin_checkout'] ) && function_exists( 'is_checkout' ) && is_checkout()
			&& ! ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) ) {
			$total = ( WC()->cart && ! WC()->cart->is_empty() ) ? WC()->cart->get_total( 'edit' ) : 0;
			$items = ( WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;
			$this->push(
				'begin_checkout',
				$this->with_value( array( 'items' => (int) $items ), $total )
			);
		}
	}

	/**
	 * Sprechender Listenname fuer view_item_list.
	 *
	 * @return string
	 */
	private function current_list_name() {
		if ( is_search() ) {
			return 'search';
		}
		if ( is_product_category() || is_product_tag() ) {
			$term = get_queried_object();
			if ( $term && isset( $term->name ) ) {
				return (string) $term->name;
			}
		}
		return 'shop';
	}

	/**
	 * purchase auf der Bestellbestaetigung - genau einmal pro Bestellung.
	 *
	 * @param int $order_id Bestellnummer.
	 */
	public function on_thankyou( $order_id ) {
		$s = mouseplayer_wc_get_settings();

		if ( empty( $s['ev_purchase'] ) || ! mouseplayer_wc_is_active() || ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Die Danke-Seite wird beim Neuladen erneut aufgerufen. Ohne diese Sperre
		// wuerde jede Aktualisierung einen weiteren Kauf melden und Umsatz sowie
		// Funnel-Zahlen aufblaehen.
		if ( $order->get_meta( '_mouseplayer_tracked' ) ) {
			return;
		}
		$order->update_meta_data( '_mouseplayer_tracked', current_time( 'mysql' ) );
		$order->save();

		$this->push(
			'purchase',
			$this->with_value(
				array(
					'transaction_id' => (string) $order->get_order_number(),
					'items'          => (int) $order->get_item_count(),
				),
				$order->get_total()
			)
		);
	}

	/**
	 * Gesammelte Events sowie die JS-seitigen Listener ausgeben.
	 */
	public function print_queue() {
		if ( ! mouseplayer_wc_is_active() ) {
			return;
		}

		$s   = mouseplayer_wc_get_settings();
		$out = '';

		foreach ( $this->queue as $ev ) {
			$out .= 'window.mp_q.push(["event",' . wp_json_encode( $ev['name'] ) . ',' . wp_json_encode( $ev['params'] ) . ']);';
		}

		// add_to_cart und select_item passieren im Browser (AJAX-Buttons, Klick auf
		// eine Kachel), lassen sich also serverseitig nicht zuverlaessig abgreifen.
		if ( ! empty( $s['ev_add_to_cart'] ) ) {
			$out .= "(function(){
				function send(el){
					try{
						var p={};
						if(el){
							p.item_id=el.getAttribute('data-product_id')||el.value||'';
							var q=el.getAttribute('data-quantity'); if(q) p.quantity=parseInt(q,10)||1;
						}
						window.mp_q.push(['event','add_to_cart',p]);
					}catch(e){}
				}
				document.addEventListener('click',function(e){
					var b=e.target.closest && e.target.closest('.add_to_cart_button, .single_add_to_cart_button');
					if(b) send(b);
				},true);
				if(window.jQuery){ jQuery(document.body).on('added_to_cart',function(ev,frag,hash,btn){ send(btn && btn[0]); }); }
			})();";
		}

		if ( ! empty( $s['ev_select_item'] ) ) {
			$out .= "(function(){
				document.addEventListener('click',function(e){
					var a=e.target.closest && e.target.closest('.products .product a, .wc-block-grid__product a');
					if(!a) return;
					try{
						var li=a.closest('.product, .wc-block-grid__product');
						var t=li?li.querySelector('.woocommerce-loop-product__title, .wc-block-grid__product-title'):null;
						window.mp_q.push(['event','select_item',{item_name:(t?t.textContent:'').trim().slice(0,120)}]);
					}catch(err){}
				},true);
			})();";
		}

		if ( '' === $out ) {
			return;
		}

		echo '<script id="mouseplayer-events">window.mp_q=window.mp_q||[];' . $out . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inline script, values passed through wp_json_encode().
	}
}
