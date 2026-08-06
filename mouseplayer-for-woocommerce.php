<?php
/**
 * Plugin Name:       MousePlayer for WooCommerce
 * Plugin URI:        https://mouseplayer.com/integrations/woocommerce
 * Description:       Adds MousePlayer session replay to your store and forwards the GA4-style ecommerce events (view_item_list, select_item, view_item, add_to_cart, begin_checkout, purchase) so the Ecommerce Funnel fills itself.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            MousePlayer
 * Author URI:        https://mouseplayer.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mouseplayer-for-woocommerce
 * Domain Path:       /languages
 *
 * WC requires at least: 6.0
 * WC tested up to:      9.4
 *
 * @package MousePlayer_For_WooCommerce
 */

// Kein direkter Aufruf - Pflicht fuer wordpress.org.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MOUSEPLAYER_WC_VERSION', '1.0.0' );
define( 'MOUSEPLAYER_WC_FILE', __FILE__ );
define( 'MOUSEPLAYER_WC_PATH', plugin_dir_path( __FILE__ ) );
define( 'MOUSEPLAYER_WC_OPTION', 'mouseplayer_wc_settings' );

require_once MOUSEPLAYER_WC_PATH . 'includes/class-mouseplayer-wc-settings.php';
require_once MOUSEPLAYER_WC_PATH . 'includes/class-mouseplayer-wc-tracker.php';

/**
 * Liefert die Einstellungen inklusive Defaults.
 *
 * @return array
 */
function mouseplayer_wc_get_settings() {
	$defaults = array(
		'enabled'            => 0,
		'channel_id'         => '',
		'host'               => 'mouseplayer.com',
		'ev_view_item_list'  => 1,
		'ev_select_item'     => 1,
		'ev_view_item'       => 1,
		'ev_add_to_cart'     => 1,
		'ev_begin_checkout'  => 1,
		'ev_purchase'        => 1,
		'send_value'         => 1,
		'identify_customers' => 0,
		'exclude_staff'      => 1,
	);

	$saved = get_option( MOUSEPLAYER_WC_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	return wp_parse_args( $saved, $defaults );
}

/**
 * Ist das Tracking fuer den aktuellen Request aktiv?
 *
 * @return bool
 */
function mouseplayer_wc_is_active() {
	$s = mouseplayer_wc_get_settings();

	if ( empty( $s['enabled'] ) || '' === trim( (string) $s['channel_id'] ) ) {
		return false;
	}

	// Redaktion/Shop-Team optional ausschliessen, damit interne Klicks die Daten nicht verzerren.
	if ( ! empty( $s['exclude_staff'] ) && is_user_logged_in()
		&& ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ) ) ) {
		return false;
	}

	/**
	 * Erlaubt es Shops, das Tracking pro Request abzuschalten
	 * (z. B. fuer Testbestellungen oder eigene Consent-Logik).
	 *
	 * @param bool $active
	 */
	return (bool) apply_filters( 'mouseplayer_wc_is_active', true );
}

/**
 * WooCommerce-Abhaengigkeit pruefen und Hinweis zeigen.
 */
function mouseplayer_wc_check_dependencies() {
	if ( class_exists( 'WooCommerce' ) ) {
		return;
	}

	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'MousePlayer for WooCommerce needs WooCommerce to be installed and active. Session replay still works, but no ecommerce events will be sent.', 'mouseplayer-for-woocommerce' );
			echo '</p></div>';
		}
	);
}
add_action( 'admin_init', 'mouseplayer_wc_check_dependencies' );

/**
 * HPOS (High-Performance Order Storage) Kompatibilitaet melden.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', MOUSEPLAYER_WC_FILE, true );
		}
	}
);

// Bootstrap.
new MousePlayer_WC_Settings();
new MousePlayer_WC_Tracker();

/**
 * Einstellungen-Link in der Plugin-Liste.
 *
 * @param array $links Vorhandene Links.
 * @return array
 */
function mouseplayer_wc_action_links( $links ) {
	$url = admin_url( 'options-general.php?page=mouseplayer-wc' );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'mouseplayer-for-woocommerce' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'mouseplayer_wc_action_links' );
