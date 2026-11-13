<?php
/**
 * Removes the settings, the last connection check and the purchase marks on orders.
 *
 * @package MousePlayer_For_WooCommerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'mouseplayer_wc_settings' );
delete_option( 'mouseplayer_wc_since' );
delete_transient( 'mouseplayer_wc_last_check' );

/**
 * Remove the "purchase reported" mark from orders, through the WooCommerce API so that it works with
 * order tables (HPOS) and with posts. Runs only while WooCommerce is active.
 */
function mouseplayer_wc_uninstall_order_marks() {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		// Orders stored as posts can still be cleaned without WooCommerce.
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
mouseplayer_wc_uninstall_order_marks();
delete_option( 'mouseplayer_wc_since' );
