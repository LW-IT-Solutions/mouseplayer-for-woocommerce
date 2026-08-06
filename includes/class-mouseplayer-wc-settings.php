<?php
/**
 * Einstellungsseite (Einstellungen -> MousePlayer).
 *
 * @package MousePlayer_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verwaltet Optionen und Admin-UI.
 */
class MousePlayer_WC_Settings {

	const PAGE_SLUG = 'mouseplayer-wc';

	/**
	 * Hooks registrieren.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Menuepunkt anlegen.
	 */
	public function add_menu() {
		add_options_page(
			__( 'MousePlayer for WooCommerce', 'mouseplayer-for-woocommerce' ),
			__( 'MousePlayer', 'mouseplayer-for-woocommerce' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Option registrieren - Settings API uebernimmt Nonce und Capability.
	 */
	public function register_settings() {
		register_setting(
			'mouseplayer_wc_group',
			MOUSEPLAYER_WC_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Alles, was hereinkommt, wird typisiert und begrenzt.
	 *
	 * @param mixed $input Rohdaten aus dem Formular.
	 * @return array
	 */
	public function sanitize( $input ) {
		$out = array();

		if ( ! is_array( $input ) ) {
			$input = array();
		}

		// Channel-ID: MousePlayer nutzt 16 Hex-Zeichen. Wir sind bewusst etwas
		// nachsichtiger (Laenge), lassen aber nur unbedenkliche Zeichen durch.
		$channel = isset( $input['channel_id'] ) ? sanitize_text_field( $input['channel_id'] ) : '';
		$channel = preg_replace( '/[^a-zA-Z0-9]/', '', (string) $channel );
		$out['channel_id'] = substr( (string) $channel, 0, 64 );

		// Host ohne Schema/Pfad - das Skript wird immer ueber https eingebunden.
		$host = isset( $input['host'] ) ? sanitize_text_field( $input['host'] ) : 'mouseplayer.com';
		$host = preg_replace( '#^https?://#i', '', (string) $host );
		$host = preg_replace( '#[/?].*$#', '', $host );
		$host = preg_replace( '/[^a-zA-Z0-9.\-]/', '', $host );
		$out['host'] = '' !== $host ? substr( $host, 0, 190 ) : 'mouseplayer.com';

		$flags = array(
			'enabled',
			'ev_view_item_list',
			'ev_select_item',
			'ev_view_item',
			'ev_add_to_cart',
			'ev_begin_checkout',
			'ev_purchase',
			'send_value',
			'identify_customers',
			'exclude_staff',
		);
		foreach ( $flags as $flag ) {
			$out[ $flag ] = ! empty( $input[ $flag ] ) ? 1 : 0;
		}

		return $out;
	}

	/**
	 * Checkbox rendern.
	 *
	 * @param array  $s     Einstellungen.
	 * @param string $key   Feldname.
	 * @param string $label Beschriftung.
	 * @param string $hint  Optionaler Hinweis.
	 */
	private function checkbox( $s, $key, $label, $hint = '' ) {
		$name = MOUSEPLAYER_WC_OPTION . '[' . $key . ']';
		?>
		<label style="display:block;margin:6px 0;">
			<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( ! empty( $s[ $key ] ) ); ?> />
			<?php echo esc_html( $label ); ?>
			<?php if ( '' !== $hint ) : ?>
				<span class="description" style="display:block;margin-left:24px;"><?php echo esc_html( $hint ); ?></span>
			<?php endif; ?>
		</label>
		<?php
	}

	/**
	 * Seite ausgeben.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$s = mouseplayer_wc_get_settings();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'MousePlayer for WooCommerce', 'mouseplayer-for-woocommerce' ); ?></h1>

			<div class="notice notice-info inline" style="margin:16px 0;padding:10px 12px;">
				<p style="margin:0;">
					<?php
					echo esc_html__( 'This plugin sends visitor interaction data to MousePlayer, an external service. Nothing is sent until you enter your Channel ID and switch tracking on.', 'mouseplayer-for-woocommerce' );
					?>
					<br />
					<a href="https://mouseplayer.com/privacy" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Privacy policy', 'mouseplayer-for-woocommerce' ); ?></a>
					&nbsp;&middot;&nbsp;
					<a href="https://mouseplayer.com/terms" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Terms of service', 'mouseplayer-for-woocommerce' ); ?></a>
				</p>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'mouseplayer_wc_group' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Tracking', 'mouseplayer-for-woocommerce' ); ?></th>
						<td>
							<?php
							$this->checkbox(
								$s,
								'enabled',
								__( 'Enable MousePlayer on this store', 'mouseplayer-for-woocommerce' ),
								__( 'Loads the tracking script in the site header.', 'mouseplayer-for-woocommerce' )
							);
							?>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="mouseplayer_wc_channel"><?php echo esc_html__( 'Channel ID', 'mouseplayer-for-woocommerce' ); ?></label>
						</th>
						<td>
							<input type="text" class="regular-text code" id="mouseplayer_wc_channel"
								name="<?php echo esc_attr( MOUSEPLAYER_WC_OPTION ); ?>[channel_id]"
								value="<?php echo esc_attr( $s['channel_id'] ); ?>"
								placeholder="b1b60e50b9c2b346" />
							<p class="description">
								<?php echo esc_html__( 'Found in MousePlayer under Project → Code. Required.', 'mouseplayer-for-woocommerce' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="mouseplayer_wc_host"><?php echo esc_html__( 'Tracking host', 'mouseplayer-for-woocommerce' ); ?></label>
						</th>
						<td>
							<input type="text" class="regular-text code" id="mouseplayer_wc_host"
								name="<?php echo esc_attr( MOUSEPLAYER_WC_OPTION ); ?>[host]"
								value="<?php echo esc_attr( $s['host'] ); ?>" />
							<p class="description">
								<?php echo esc_html__( 'Only change this if you were given a dedicated host. Default: mouseplayer.com', 'mouseplayer-for-woocommerce' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php echo esc_html__( 'Ecommerce events', 'mouseplayer-for-woocommerce' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php echo esc_html__( 'Ecommerce events', 'mouseplayer-for-woocommerce' ); ?></legend>
								<?php
								$this->checkbox( $s, 'ev_view_item_list', __( 'view_item_list — shop, category and search pages', 'mouseplayer-for-woocommerce' ) );
								$this->checkbox( $s, 'ev_select_item', __( 'select_item — click on a product in a list', 'mouseplayer-for-woocommerce' ) );
								$this->checkbox( $s, 'ev_view_item', __( 'view_item — single product page', 'mouseplayer-for-woocommerce' ) );
								$this->checkbox( $s, 'ev_add_to_cart', __( 'add_to_cart — including AJAX add-to-cart', 'mouseplayer-for-woocommerce' ) );
								$this->checkbox( $s, 'ev_begin_checkout', __( 'begin_checkout — checkout page', 'mouseplayer-for-woocommerce' ) );
								$this->checkbox( $s, 'ev_purchase', __( 'purchase — order received page', 'mouseplayer-for-woocommerce' ) );
								?>
								<p class="description">
									<?php echo esc_html__( 'These are the six steps of the Standard Ecommerce Funnel in MousePlayer. Leave them all on unless you have a reason not to.', 'mouseplayer-for-woocommerce' ); ?>
								</p>
							</fieldset>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php echo esc_html__( 'Data sent', 'mouseplayer-for-woocommerce' ); ?></th>
						<td>
							<fieldset>
								<?php
								$this->checkbox(
									$s,
									'send_value',
									__( 'Send order value and currency', 'mouseplayer-for-woocommerce' ),
									__( 'Needed for the revenue figure under the Purchase step.', 'mouseplayer-for-woocommerce' )
								);
								$this->checkbox(
									$s,
									'identify_customers',
									__( 'Identify logged-in customers', 'mouseplayer-for-woocommerce' ),
									__( 'Sends a hashed customer reference so sessions can be tied to an account. Leave off if you do not want personal identifiers leaving the shop.', 'mouseplayer-for-woocommerce' )
								);
								$this->checkbox(
									$s,
									'exclude_staff',
									__( 'Do not track administrators and shop managers', 'mouseplayer-for-woocommerce' ),
									__( 'Keeps your own clicks out of the recordings and the funnel.', 'mouseplayer-for-woocommerce' )
								);
								?>
							</fieldset>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<?php if ( empty( $s['channel_id'] ) ) : ?>
				<div class="notice notice-warning inline" style="margin-top:16px;padding:10px 12px;">
					<p style="margin:0;"><?php echo esc_html__( 'No Channel ID set yet — tracking stays off until you add one.', 'mouseplayer-for-woocommerce' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
