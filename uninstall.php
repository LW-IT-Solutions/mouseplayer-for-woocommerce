<?php
/**
 * Removes the settings, the API key, the last connection check and the purchase marks on orders,
 * on every site of a network.
 *
 * @package MousePlayer_For_WooCommerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove the "purchase reported" mark from orders, through the WooCommerce API so that it works with
 * order tables (HPOS) and with posts. Without WooCommerce only orders stored as posts can be cleaned.
 */
function mouseplayer_wc_uninstall_order_marks() {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		delete_post_meta_by_key( '_mouseplayer_wc_reported' );
		return;
	}
	// Marks exist only on orders created since the plugin was activated; walk those in pages.
	$since = (int) get_option( 'mouseplayer_wc_since', 0 );
	for ( $page = 1; $page <= 500; $page++ ) {
		$args = array(
			'limit'   => 200,
			'page'    => $page,
			'type'    => 'shop_order',
			'orderby' => 'ID',
			'order'   => 'ASC',
		);
		if ( $since > 0 ) {
			$args['date_created'] = '>=' . ( $since - DAY_IN_SECONDS );
		}
		$orders = wc_get_orders( $args );
		if ( empty( $orders ) ) {
			break;
		}
		foreach ( $orders as $order ) {
			if ( $order->get_meta( '_mouseplayer_wc_reported' ) ) {
				$order->delete_meta_data( '_mouseplayer_wc_reported' );
				$order->save_meta_data();
			}
		}
	}
	delete_post_meta_by_key( '_mouseplayer_wc_reported' );
}

/**
 * Everything the plugin stored on one site.
 */
function mouseplayer_wc_uninstall_site() {
	// The order marks first: they need mouseplayer_wc_since.
	mouseplayer_wc_uninstall_order_marks();
	delete_option( 'mouseplayer_wc_settings' );
	delete_option( 'mouseplayer_wc_api_key' );
	delete_option( 'mouseplayer_wc_since' );
	delete_transient( 'mouseplayer_wc_last_check' );
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $mouseplayer_wc_site_id ) {
		switch_to_blog( $mouseplayer_wc_site_id );
		mouseplayer_wc_uninstall_site();
		restore_current_blog();
	}
} else {
	mouseplayer_wc_uninstall_site();
}
