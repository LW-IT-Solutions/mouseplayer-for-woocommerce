<?php
/**
 * Plugin Name:       MousePlayer for WooCommerce
 * Plugin URI:        https://github.com/LW-IT-Solutions/mouseplayer-for-woocommerce
 * Description:       Session replay and heatmaps from MousePlayer for WooCommerce, with consent handling, masking of checkout and account pages, and the six ecommerce funnel events.
 * Version:           1.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            LW IT Solutions Company
 * Author URI:        https://www.mouseplayer.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mouseplayer-for-woocommerce
 * WC requires at least: 9.0
 * WC tested up to:      11.1
 *
 * @package MousePlayer_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MOUSEPLAYER_WC_VERSION', '1.1.0' );
define( 'MOUSEPLAYER_WC_FILE', __FILE__ );
define( 'MOUSEPLAYER_WC_PATH', plugin_dir_path( __FILE__ ) );
define( 'MOUSEPLAYER_WC_URL', plugin_dir_url( __FILE__ ) );
define( 'MOUSEPLAYER_WC_OPTION', 'mouseplayer_wc_settings' );
define( 'MOUSEPLAYER_WC_KEY_OPTION', 'mouseplayer_wc_api_key' );
define( 'MOUSEPLAYER_WC_DEFAULT_HOST', 'www.mouseplayer.com' );

/**
 * Address of a page on the MousePlayer website (privacy policy, terms, data access).
 *
 * @param string $path Path starting with a slash.
 * @return string
 */
function mouseplayer_wc_site_url( $path ) {
	return 'https://' . MOUSEPLAYER_WC_DEFAULT_HOST . $path;
}

require_once MOUSEPLAYER_WC_PATH . 'includes/class-mouseplayer-wc-connection.php';
require_once MOUSEPLAYER_WC_PATH . 'includes/class-mouseplayer-wc-settings.php';
require_once MOUSEPLAYER_WC_PATH . 'includes/class-mouseplayer-wc-tracker.php';

/**
 * Default settings. Tracking is off until a Channel ID is entered and tracking is switched on.
 *
 * @return array
 */
function mouseplayer_wc_defaults() {
	return array(
		'enabled'            => 0,
		'channel_id'         => '',
		'host'               => MOUSEPLAYER_WC_DEFAULT_HOST,
		'api_key'            => '',
		'consent_mode'       => 'auto',
		'consent_cmp'        => '',
		'exclude_roles'      => array( 'administrator', 'shop_manager' ),
		'mask_checkout'      => 1,
		'mask_cart'          => 0,
		'ignore_typing'      => 1,
		'identify_customers' => 0,
		'ev_view_item_list'  => 1,
		'ev_select_item'     => 1,
		'ev_view_item'       => 1,
		'ev_add_to_cart'     => 1,
		'ev_begin_checkout'  => 1,
		'ev_purchase'        => 1,
		'send_value'         => 1,
		'send_context'       => 1,
		'verify_token'       => '',
	);
}

/**
 * Settings merged with the defaults.
 *
 * The API key is kept in an option of its own that is not autoloaded
 * (MOUSEPLAYER_WC_KEY_OPTION); version 1.0.0 stored it in the settings array,
 * which is still read until mouseplayer_wc_move_api_key() has moved it.
 *
 * @return array
 */
function mouseplayer_wc_get_settings() {
	$saved = get_option( MOUSEPLAYER_WC_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	$settings = wp_parse_args( $saved, mouseplayer_wc_defaults() );
	$key      = (string) get_option( MOUSEPLAYER_WC_KEY_OPTION, '' );
	if ( '' !== $key ) {
		$settings['api_key'] = $key;
	}
	$settings['api_key'] = (string) $settings['api_key'];
	return $settings;
}

/**
 * Moves an API key saved by version 1.0.0 out of the autoloaded settings into
 * its own option. Runs before the setting is registered, so that the
 * settings sanitizer is not involved.
 */
function mouseplayer_wc_move_api_key() {
	$saved = get_option( MOUSEPLAYER_WC_OPTION, array() );
	if ( ! is_array( $saved ) || ! array_key_exists( 'api_key', $saved ) ) {
		return;
	}
	$key = sanitize_text_field( (string) $saved['api_key'] );
	if ( '' !== $key && '' === (string) get_option( MOUSEPLAYER_WC_KEY_OPTION, '' ) ) {
		update_option( MOUSEPLAYER_WC_KEY_OPTION, $key, false );
	}
	unset( $saved['api_key'] );
	update_option( MOUSEPLAYER_WC_OPTION, $saved );
}
add_action( 'admin_init', 'mouseplayer_wc_move_api_key', 5 );

/**
 * Consent tools and the script attributes MousePlayer documents for blocking the snippet until consent.
 *
 * @return array Key => array( 'name' => label, 'attributes' => array ).
 */
function mouseplayer_wc_cmps() {
	$cmps = array(
		'consented'      => array(
			'name'       => 'Consented.eu',
			'attributes' => array(
				'type'           => 'text/plain',
				'data-consented' => 'mouseplayer',
			),
		),
		'usercentrics'   => array(
			'name'       => 'Usercentrics',
			'attributes' => array(
				'type'              => 'text/plain',
				'data-usercentrics' => 'MousePlayer',
			),
		),
		'cookiebot'      => array(
			'name'       => 'Cookiebot',
			'attributes' => array(
				'type'                => 'text/plain',
				'data-cookieconsent' => 'statistics',
			),
		),
		'onetrust'       => array(
			'name'       => 'OneTrust',
			'attributes' => array(
				'type'  => 'text/plain',
				'class' => 'optanon-category-C0002',
			),
		),
		'consentmanager' => array(
			'name'       => 'consentmanager',
			'attributes' => array(
				'type'             => 'text/plain',
				'class'            => 'cmplazyload',
				'data-cmp-purpose' => '1',
			),
		),
		'borlabs'        => array(
			'name'       => 'Borlabs Cookie',
			'attributes' => array(
				'type'                        => 'text/template',
				'data-borlabs-cookie-blocker' => '',
			),
		),
		'complianz'      => array(
			'name'       => 'Complianz',
			'attributes' => array(
				'type'          => 'text/plain',
				'data-category' => 'statistics',
			),
		),
	);

	/**
	 * Filters the list of consent tools and the attributes used to block the MousePlayer snippet.
	 *
	 * @param array $cmps Key => array( 'name' => string, 'attributes' => array ).
	 */
	return (array) apply_filters( 'mouseplayer_wc_cmps', $cmps );
}

/**
 * Is the current visitor excluded from recording by role?
 *
 * @param array $s Settings.
 * @return bool
 */
function mouseplayer_wc_is_excluded_user( $s ) {
	if ( ! is_user_logged_in() ) {
		return false;
	}
	$roles = (array) wp_get_current_user()->roles;
	return (bool) array_intersect( $roles, (array) $s['exclude_roles'] );
}

/**
 * Is tracking active for the current front-end request?
 *
 * @return bool
 */
function mouseplayer_wc_is_active() {
	$s = mouseplayer_wc_get_settings();

	if ( empty( $s['enabled'] ) || '' === (string) $s['channel_id'] ) {
		return false;
	}
	if ( is_admin() || wp_doing_ajax() || is_feed() || is_embed() || is_customize_preview() || is_robots() ) {
		return false;
	}
	if ( mouseplayer_wc_is_excluded_user( $s ) ) {
		return false;
	}

	/**
	 * Lets a shop switch tracking off for a request, for example for test orders.
	 *
	 * @param bool $active Whether the snippet and events are printed.
	 */
	return (bool) apply_filters( 'mouseplayer_wc_is_active', true );
}

/**
 * Declare compatibility with order tables (HPOS) and the cart and checkout blocks.
 */
function mouseplayer_wc_declare_compatibility() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', MOUSEPLAYER_WC_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', MOUSEPLAYER_WC_FILE, true );
	}
}
add_action( 'before_woocommerce_init', 'mouseplayer_wc_declare_compatibility' );

/**
 * Tell the WP Consent API that this plugin asks for consent through it.
 */
add_filter( 'wp_consent_api_registered_' . plugin_basename( __FILE__ ), '__return_true' );

/**
 * Settings link in the plugin list.
 *
 * @param array $links Existing links.
 * @return array
 */
function mouseplayer_wc_action_links( $links ) {
	$url = admin_url( 'admin.php?page=' . MousePlayer_WC_Settings::PAGE_SLUG );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'mouseplayer-for-woocommerce' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'mouseplayer_wc_action_links' );

/**
 * Suggested text for the privacy policy guide (Settings > Privacy).
 */
function mouseplayer_wc_privacy_policy_content() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}
	$text  = '<p class="privacy-policy-tutorial">' . esc_html__( 'With tracking switched on, every shop page loads the MousePlayer script from mouseplayer.com, which transmits the visitor\'s IP address and browser information to MousePlayer. With the option "My consent tool blocks the snippet" this happens only after consent; with the other consent options the script loads with the page and waits. The MousePlayer script records the visit and the plugin hands shop events to it only after the visitor has consented. Adapt the text below to your consent tool and your settings.', 'mouseplayer-for-woocommerce' ) . '</p>';
	$text .= '<p><strong class="privacy-policy-tutorial">' . esc_html__( 'Suggested text:', 'mouseplayer-for-woocommerce' ) . ' </strong>';
	$text .= esc_html__( 'With your consent, this shop uses MousePlayer, a session replay and analytics service, to understand how visitors use the shop. MousePlayer records page views, clicks, scrolling, mouse movements and the content of the pages as you see them, together with browser, device and approximate location derived from the IP address. Typed text in checkout and account forms is not recorded, and the content of the checkout, order confirmation and account pages is masked before it leaves your browser. What is typed into password and payment fields is never recorded. If enabled, the shop also sends shopping steps such as product views, add to cart, checkout and purchase with order value and currency. MousePlayer stores a random visitor ID in your browser\'s local storage, and the shop keeps the IDs of shopping steps already sent in your browser\'s session storage until the tab is closed, so that none is sent twice. You can withdraw your consent at any time in the consent settings of this shop.', 'mouseplayer-for-woocommerce' ) . '</p>';
	$text .= '<p>' . sprintf(
		/* translators: 1: MousePlayer privacy policy URL, 2: MousePlayer self-service data access URL. */
		wp_kses_post( __( 'Privacy policy of MousePlayer: <a href="%1$s">%1$s</a>. Visitors can see the data recorded about their browser at <a href="%2$s">%2$s</a>; the shortcode [mouseplayer_privacy_link] prints a link that already contains the visitor ID of the current browser.', 'mouseplayer-for-woocommerce' ) ),
		esc_url( mouseplayer_wc_site_url( '/en/company/privacy-policy' ) ),
		esc_url( mouseplayer_wc_site_url( '/my-data' ) )
	) . '</p>';

	wp_add_privacy_policy_content( 'MousePlayer for WooCommerce', wp_kses_post( $text ) );
}
add_action( 'admin_init', 'mouseplayer_wc_privacy_policy_content' );

/**
 * Remember since when the plugin is active; uninstall only has to look at orders from then on.
 */
function mouseplayer_wc_activate() {
	add_option( 'mouseplayer_wc_since', time(), '', false );
}
register_activation_hook( __FILE__, 'mouseplayer_wc_activate' );

new MousePlayer_WC_Settings();
new MousePlayer_WC_Tracker();
