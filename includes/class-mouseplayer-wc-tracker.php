<?php
/**
 * Front end: tracking snippet, consent bridge, masking and ecommerce events.
 *
 * @package MousePlayer_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prints the MousePlayer snippet and hands WooCommerce events to it.
 */
class MousePlayer_WC_Tracker {

	/**
	 * WooCommerce session key for add_to_cart events waiting for the next page view.
	 */
	const SESSION_KEY = 'mouseplayer_wc_pending';

	/**
	 * Order meta key that marks a reported purchase.
	 */
	const ORDER_META = '_mouseplayer_wc_reported';

	/**
	 * Events for this page view.
	 *
	 * @var array
	 */
	private $events = array();

	/**
	 * Page context for MousePlayer.setContext().
	 *
	 * @var array
	 */
	private $context = array();

	/**
	 * Attributes of the blocked snippet, when a consent tool has to release it (see enqueue()).
	 *
	 * @var array|null
	 */
	private $blocked_snippet = null;

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'print_head' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp', array( $this, 'collect_page_events' ) );
		add_action( 'woocommerce_thankyou', array( $this, 'on_thankyou' ), 20 );
		add_action( 'woocommerce_add_to_cart', array( $this, 'on_add_to_cart' ), 20, 4 );
		add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'cart_fragments' ) );
		add_action( 'wp_footer', array( $this, 'hand_over' ), 5 );
		add_filter( 'do_shortcode_tag', array( $this, 'mask_shortcode' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'mask_block' ), 10, 2 );
		add_filter( 'woocommerce_form_field_args', array( $this, 'ignore_field' ) );
		add_shortcode( 'mouseplayer_privacy_link', array( $this, 'privacy_link_shortcode' ) );
	}

	/**
	 * Settings and the WooCommerce state allow recording events in this request (also AJAX and Store API).
	 *
	 * @param string $event Event setting key, for example ev_add_to_cart.
	 * @return bool
	 */
	private function may_queue( $event ) {
		$s = mouseplayer_wc_get_settings();
		if ( empty( $s['enabled'] ) || '' === (string) $s['channel_id'] || empty( $s[ $event ] ) ) {
			return false;
		}
		if ( mouseplayer_wc_is_excluded_user( $s ) ) {
			return false;
		}
		/** This filter is documented in mouseplayer-for-woocommerce.php */
		return (bool) apply_filters( 'mouseplayer_wc_is_active', true );
	}

	/**
	 * Domain verification meta element, and the blocked snippet for a consent tool (see enqueue()).
	 */
	public function print_head() {
		$s = mouseplayer_wc_get_settings();

		if ( '' !== (string) $s['verify_token'] && ( is_front_page() || is_home() ) ) {
			echo '<meta name="mouseplayer-verify" content="' . esc_attr( $s['verify_token'] ) . '" />' . "\n";
		}

		if ( null === $this->blocked_snippet || ! mouseplayer_wc_is_active() ) {
			return;
		}

		// The consent tool keeps this element inert (for example type="text/plain") and runs it only after the
		// visitor has agreed; only then it adds mp.js from the MousePlayer host to the page.
		$js = $this->config_js( $s )
			. '(function(d,s){var f=d.getElementsByTagName(s)[0],j=d.createElement(s);'
			. 'j.async=true;j.src=' . wp_json_encode( MousePlayer_WC_Connection::script_url( MousePlayer_WC_Connection::tracking_host( $s ) ) ) . ';'
			. 'f.parentNode.insertBefore(j,f);})(document,"script");';

		wp_print_inline_script_tag( $js, $this->blocked_snippet );
	}

	/**
	 * Configuration that mp.js reads: window.mp_config, the command queue window.mp and window.mp_channelid.
	 * These global names are the documented interface of mp.js and cannot be renamed by the plugin.
	 *
	 * @param array $s Settings.
	 * @return string JavaScript.
	 */
	private function config_js( $s ) {
		$config = array(
			'channel' => (string) $s['channel_id'],
			'mode'    => 'web_only',
		);
		if ( ! empty( $s['identify_customers'] ) && is_user_logged_in() ) {
			// A stable pseudonym, never the email address or the user ID itself.
			$config['user_id'] = 'wp_' . substr( hash_hmac( 'sha256', (string) get_current_user_id(), wp_salt( 'auth' ) ), 0, 32 );
		}
		return 'window.mp_config=' . wp_json_encode( $config ) . ';'
			. 'window.mp=window.mp||function(){(window.mp.q=window.mp.q||[]).push(arguments);};'
			. 'window.mp_channelid=' . wp_json_encode( (string) $s['channel_id'] ) . ';';
	}

	/**
	 * Attributes of the snippet element: those of the chosen consent tool, if it blocks the snippet.
	 *
	 * @param array $s Settings.
	 * @return array
	 */
	private function snippet_attributes( $s ) {
		$attributes = array( 'id' => 'mouseplayer-snippet' );
		$cmps       = mouseplayer_wc_cmps();
		if ( 'cmp_block' === $s['consent_mode'] && isset( $cmps[ $s['consent_cmp'] ]['attributes'] ) ) {
			$attributes = array_merge( $attributes, (array) $cmps[ $s['consent_cmp'] ]['attributes'] );
		}

		/**
		 * Filters the attributes of the snippet script element, for example to block it with another consent tool.
		 *
		 * @param array $attributes Attribute name => value.
		 * @param array $s          Plugin settings.
		 */
		return (array) apply_filters( 'mouseplayer_wc_script_attributes', $attributes, $s );
	}

	/**
	 * Load mp.js and the event script.
	 *
	 * Without blocking attributes, mp.js from the MousePlayer host is enqueued (async, in the head) with its
	 * configuration as an inline script before it. If a consent tool has to block it, the snippet is printed
	 * as one blocked inline element in print_head() instead, because consent tools release such elements.
	 * The data for the event script is added in the footer, after the page content has run.
	 */
	public function enqueue() {
		if ( ! mouseplayer_wc_is_active() ) {
			return;
		}
		$s          = mouseplayer_wc_get_settings();
		$attributes = $this->snippet_attributes( $s );
		if ( array( 'id' => 'mouseplayer-snippet' ) === $attributes ) {
			// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- mp.js is the remote script of the service and is versioned by MousePlayer; the plugin adds no ver parameter to it.
			wp_enqueue_script(
				'mouseplayer-wc-mp',
				MousePlayer_WC_Connection::script_url( MousePlayer_WC_Connection::tracking_host( $s ) ),
				array(),
				null,
				array(
					'in_footer' => false,
					'strategy'  => 'async',
				)
			);
			wp_add_inline_script( 'mouseplayer-wc-mp', $this->config_js( $s ), 'before' );
		} else {
			$this->blocked_snippet = $attributes;
		}

		wp_enqueue_script(
			'mouseplayer-wc',
			MOUSEPLAYER_WC_URL . 'assets/frontend.js',
			array(),
			MOUSEPLAYER_WC_VERSION,
			array( 'in_footer' => true )
		);
	}

	/**
	 * Add price and currency when allowed.
	 *
	 * @param array $params Event parameters.
	 * @param mixed $value  Amount.
	 * @return array
	 */
	private function with_value( $params, $value ) {
		$s = mouseplayer_wc_get_settings();
		if ( empty( $s['send_value'] ) || '' === (string) $value ) {
			return $params;
		}
		$params['price']    = round( (float) $value, 2 );
		$params['currency'] = get_woocommerce_currency();
		return $params;
	}

	/**
	 * Add an event for this page view.
	 *
	 * @param string $name   Event name.
	 * @param array  $params Flat parameters.
	 * @param string $id     Unique ID, so that the browser does not send it twice.
	 */
	private function add_event( $name, $params, $id = '' ) {
		$this->events[] = array(
			'id' => '' !== $id ? $id : wp_generate_uuid4(),
			'n'  => (string) $name,
			'p'  => $params,
		);
	}

	/**
	 * Events and context that depend on the page.
	 */
	public function collect_page_events() {
		if ( ! mouseplayer_wc_is_active() || ! function_exists( 'WC' ) ) {
			return;
		}
		$s = mouseplayer_wc_get_settings();

		if ( ! empty( $s['ev_view_item_list'] ) && ( is_shop() || is_product_taxonomy() || ( is_search() && 'product' === get_query_var( 'post_type' ) ) ) ) {
			$this->add_event( 'view_item_list', array( 'list_name' => $this->list_name() ) );
		}

		if ( is_product() ) {
			$product = wc_get_product( get_queried_object_id() );
			if ( $product ) {
				$price = wc_get_price_to_display( $product );
				if ( ! empty( $s['ev_view_item'] ) ) {
					$this->add_event(
						'view_item',
						$this->with_value(
							array(
								'item_id'   => (string) $product->get_id(),
								'item_name' => $product->get_name(),
								'sku'       => (string) $product->get_sku(),
							),
							$price
						)
					);
				}
				$context = array(
					'page_type'  => 'product',
					'product_id' => (string) $product->get_id(),
					'in_stock'   => $product->is_in_stock(),
				);
				$terms   = get_the_terms( $product->get_id(), 'product_cat' );
				if ( is_array( $terms ) && $terms ) {
					$context['category'] = $terms[0]->name;
				}
				if ( ! empty( $s['send_value'] ) ) {
					$context['price']    = round( (float) $price, 2 );
					$context['currency'] = get_woocommerce_currency();
				}
				$this->context = $context;
			}
		}

		$cart = WC()->cart;
		if ( is_cart() && $cart ) {
			$this->context = $this->cart_context( 'cart', $s );
		}

		if ( is_checkout() && ! is_order_received_page() && $cart ) {
			if ( ! empty( $s['ev_begin_checkout'] ) && ! $cart->is_empty() ) {
				$this->add_event(
					'begin_checkout',
					$this->with_value( array( 'items' => (int) $cart->get_cart_contents_count() ), $cart->get_total( 'edit' ) )
				);
			}
			$this->context = $this->cart_context( 'checkout', $s );
		}

		// add_to_cart events from form posts and AJAX requests that were not delivered yet.
		if ( WC()->session ) {
			$pending = WC()->session->get( self::SESSION_KEY, array() );
			if ( is_array( $pending ) && $pending ) {
				$this->events = array_merge( $this->events, $pending );
				WC()->session->set( self::SESSION_KEY, array() );
			}
		}
	}

	/**
	 * Context for cart and checkout.
	 *
	 * @param string $type Page type.
	 * @param array  $s    Settings.
	 * @return array
	 */
	private function cart_context( $type, $s ) {
		$cart    = WC()->cart;
		$context = array(
			'page_type'  => $type,
			'cart_items' => (int) $cart->get_cart_contents_count(),
		);
		if ( ! empty( $s['send_value'] ) ) {
			$context['cart_value'] = round( (float) $cart->get_total( 'edit' ), 2 );
			$context['currency']   = get_woocommerce_currency();
		}
		return $context;
	}

	/**
	 * Name of the product list on archive pages.
	 *
	 * @return string
	 */
	private function list_name() {
		if ( is_search() ) {
			return 'search';
		}
		if ( is_product_taxonomy() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				return $term->name;
			}
		}
		return 'shop';
	}

	/**
	 * Remember add_to_cart for the next page view or the next cart fragment refresh.
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param int    $product_id    Product ID.
	 * @param int    $quantity      Quantity added.
	 * @param int    $variation_id  Variation ID.
	 */
	public function on_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id ) {
		$product_id   = absint( $product_id );
		$variation_id = absint( $variation_id );
		$quantity     = max( 0, (int) $quantity );
		if ( ! $product_id || ! $this->may_queue( 'ev_add_to_cart' ) || ! WC()->session ) {
			return;
		}
		$product = wc_get_product( $variation_id ? $variation_id : $product_id );
		if ( ! $product ) {
			return;
		}
		$params = array(
			'item_id'   => (string) $product_id,
			'item_name' => $product->get_name(),
			'quantity'  => (int) $quantity,
		);
		if ( $variation_id ) {
			$params['variant_id'] = (string) $variation_id;
		}
		$params = $this->with_value( $params, (float) wc_get_price_to_display( $product ) * (int) $quantity );

		$pending = WC()->session->get( self::SESSION_KEY, array() );
		if ( ! is_array( $pending ) ) {
			$pending = array();
		}
		$pending[] = array(
			'id' => wp_generate_uuid4(),
			'n'  => 'add_to_cart',
			'p'  => $params,
		);
		WC()->session->set( self::SESSION_KEY, array_slice( $pending, -20 ) );
	}

	/**
	 * Hand waiting events to the browser with the cart fragments of an AJAX add to cart.
	 *
	 * @param array $fragments Fragments.
	 * @return array
	 */
	public function cart_fragments( $fragments ) {
		if ( ! is_array( $fragments ) || ! function_exists( 'WC' ) || ! WC()->session ) {
			return $fragments;
		}
		$pending = WC()->session->get( self::SESSION_KEY, array() );
		if ( ! is_array( $pending ) ) {
			$pending = array();
		}
		if ( $pending ) {
			WC()->session->set( self::SESSION_KEY, array() );
		}
		$fragments['div.mouseplayer-wc-pending'] = '<div class="mouseplayer-wc-pending" hidden data-events="' . esc_attr( (string) wp_json_encode( $pending ) ) . '"></div>';
		return $fragments;
	}

	/**
	 * Report a purchase on the order confirmation page, once per order.
	 *
	 * @param int $order_id Order ID.
	 */
	public function on_thankyou( $order_id ) {
		if ( ! $order_id || ! mouseplayer_wc_is_active() ) {
			return;
		}
		$s = mouseplayer_wc_get_settings();
		if ( empty( $s['ev_purchase'] ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( self::ORDER_META ) ) {
			return;
		}

		/**
		 * Filters the order statuses that count as a purchase. Failed, cancelled and pending (payment not completed)
		 * orders are not reported by default.
		 *
		 * @param array    $statuses Order statuses without the wc- prefix.
		 * @param WC_Order $order    The order.
		 */
		$statuses = (array) apply_filters( 'mouseplayer_wc_purchase_statuses', array( 'processing', 'completed', 'on-hold' ), $order );
		if ( ! in_array( $order->get_status(), $statuses, true ) ) {
			return;
		}

		// The order is marked when its confirmation page is generated for the first time; a reload does not
		// hand the purchase over again. If the visitor has not consented on that page, it is not sent at all.
		$order->update_meta_data( self::ORDER_META, gmdate( 'c' ) );
		$order->save_meta_data();

		$this->add_event(
			'purchase',
			$this->with_value(
				array(
					'transaction_id' => (string) $order->get_order_number(),
					'items'          => (int) $order->get_item_count(),
				),
				$order->get_total()
			),
			'purchase-' . $order->get_id()
		);
		$this->context = array(
			'page_type'  => 'purchase',
			'cart_items' => (int) $order->get_item_count(),
		);
	}

	/**
	 * Hand events, context and options to the event script before it is printed.
	 */
	public function hand_over() {
		if ( ! wp_script_is( 'mouseplayer-wc', 'enqueued' ) ) {
			return;
		}
		$s    = mouseplayer_wc_get_settings();
		$data = array(
			'events'      => $this->events,
			'context'     => ! empty( $s['send_context'] ) ? $this->context : array(),
			'selectItem'  => ! empty( $s['ev_select_item'] ),
			'addToCart'   => ! empty( $s['ev_add_to_cart'] ),
			'consentApi'  => 'wp_consent_api' === $s['consent_mode'],
			'ignoreTyped' => ! empty( $s['ignore_typing'] ),
		);
		wp_add_inline_script( 'mouseplayer-wc', 'window.mouseplayerWc=' . wp_json_encode( $data ) . ';', 'before' );
		echo '<div class="mouseplayer-wc-pending" hidden data-events="[]"></div>' . "\n";
	}

	/**
	 * Wrap WooCommerce shortcodes with personal data in a masked area.
	 *
	 * @param string $output Shortcode output.
	 * @param string $tag    Shortcode name.
	 * @return string
	 */
	public function mask_shortcode( $output, $tag ) {
		if ( ! in_array( $tag, $this->masked_shortcodes(), true ) || ! is_string( $output ) || '' === $output ) {
			return $output;
		}
		return '<div class="mouseplayer-wc-masked" data-mp-mask>' . $output . '</div>';
	}

	/**
	 * Mark WooCommerce blocks with personal data as masked.
	 *
	 * @param string $content Block HTML.
	 * @param array  $block   Parsed block.
	 * @return string
	 */
	public function mask_block( $content, $block ) {
		$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
		if ( '' === $name || '' === $content || 0 !== strpos( $name, 'woocommerce/' ) ) {
			return $content;
		}
		$s       = mouseplayer_wc_get_settings();
		$classic = ( 'woocommerce/classic-shortcode' === $name && isset( $block['attrs']['shortcode'] ) ) ? (string) $block['attrs']['shortcode'] : '';
		$mask    = false;
		if ( ! empty( $s['mask_checkout'] ) && ( 'woocommerce/checkout' === $name || 'checkout' === $classic || 0 === strpos( $name, 'woocommerce/order-confirmation' ) ) ) {
			$mask = true;
		}
		if ( ! empty( $s['mask_cart'] ) && ( 'woocommerce/cart' === $name || 'cart' === $classic ) ) {
			$mask = true;
		}
		if ( ! $mask ) {
			return $content;
		}
		$tags = new WP_HTML_Tag_Processor( $content );
		if ( $tags->next_tag() ) {
			$tags->set_attribute( 'data-mp-mask', true );
			return $tags->get_updated_html();
		}
		return $content;
	}

	/**
	 * Shortcodes that are masked with the current settings.
	 *
	 * @return array
	 */
	private function masked_shortcodes() {
		$s    = mouseplayer_wc_get_settings();
		$list = array();
		if ( ! empty( $s['mask_checkout'] ) ) {
			$list = array( 'woocommerce_checkout', 'woocommerce_my_account', 'woocommerce_order_tracking' );
		}
		if ( ! empty( $s['mask_cart'] ) ) {
			$list[] = 'woocommerce_cart';
		}
		return $list;
	}

	/**
	 * Keep typing in WooCommerce form fields out of the recording.
	 *
	 * @param array $args Field arguments.
	 * @return array
	 */
	public function ignore_field( $args ) {
		$s = mouseplayer_wc_get_settings();
		if ( empty( $s['ignore_typing'] ) || ! is_array( $args ) ) {
			return $args;
		}
		if ( ! isset( $args['custom_attributes'] ) || ! is_array( $args['custom_attributes'] ) ) {
			$args['custom_attributes'] = array();
		}
		$args['custom_attributes']['data-mp-ignore'] = '1';
		return $args;
	}

	/**
	 * [mouseplayer_privacy_link] prints a link to MousePlayer's self-service data access page.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function privacy_link_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'text' => __( 'Show the data MousePlayer has recorded about this browser', 'mouseplayer-for-woocommerce' ),
			),
			$atts,
			'mouseplayer_privacy_link'
		);
		wp_enqueue_script( 'mouseplayer-wc-privacy', MOUSEPLAYER_WC_URL . 'assets/privacy-link.js', array(), MOUSEPLAYER_WC_VERSION, array( 'in_footer' => true ) );
		return '<a class="mouseplayer-privacy-link" href="' . esc_url( mouseplayer_wc_site_url( '/my-data' ) ) . '" rel="noopener" target="_blank">' . esc_html( $atts['text'] ) . '</a>';
	}
}
