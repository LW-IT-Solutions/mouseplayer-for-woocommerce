<?php
/**
 * Settings page (WooCommerce > MousePlayer).
 *
 * @package MousePlayer_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings, status and connection check.
 */
class MousePlayer_WC_Settings {

	const PAGE_SLUG = 'mouseplayer-wc';
	const GROUP     = 'mouseplayer_wc_group';
	const CHECK_KEY = 'mouseplayer_wc_last_check';

	/**
	 * Hook suffix of the settings page.
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 60 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_mouseplayer_wc_check', array( $this, 'handle_check' ) );
	}

	/**
	 * Menu entry below WooCommerce.
	 */
	public function add_menu() {
		$this->hook = (string) add_submenu_page(
			'woocommerce',
			esc_html__( 'MousePlayer', 'mouseplayer-for-woocommerce' ),
			esc_html__( 'MousePlayer', 'mouseplayer-for-woocommerce' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
		if ( '' !== $this->hook ) {
			add_action( 'load-' . $this->hook, array( $this, 'add_help' ) );
		}
	}

	/**
	 * Styles and the small script that shows the consent tool list only when it is needed.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue( $hook ) {
		if ( '' === $this->hook || $hook !== $this->hook ) {
			return;
		}
		wp_enqueue_style( 'mouseplayer-wc-admin', MOUSEPLAYER_WC_URL . 'assets/admin.css', array(), MOUSEPLAYER_WC_VERSION );
		wp_enqueue_script( 'mouseplayer-wc-admin', MOUSEPLAYER_WC_URL . 'assets/admin.js', array(), MOUSEPLAYER_WC_VERSION, array( 'in_footer' => true ) );
	}

	/**
	 * Help tabs.
	 */
	public function add_help() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		$screen->add_help_tab(
			array(
				'id'      => 'mouseplayer-wc-start',
				'title'   => __( 'Getting started', 'mouseplayer-for-woocommerce' ),
				'content' => '<p>' . esc_html__( '1. Create a project for this shop in MousePlayer and copy its Channel ID (Project > Code). 2. Paste it here and choose how your consent tool works. 3. Switch tracking on and save. 4. Open the shop in a private window, accept the consent banner and click around. The session appears in MousePlayer within a few seconds.', 'mouseplayer-for-woocommerce' ) . '</p>',
			)
		);
		$screen->add_help_tab(
			array(
				'id'      => 'mouseplayer-wc-consent',
				'title'   => __( 'Consent', 'mouseplayer-for-woocommerce' ),
				'content' => '<p>' . esc_html__( 'The MousePlayer script, loaded from mouseplayer.com, records nothing without a positive consent signal; if no signal arrives, nothing is recorded. Loading the script itself transmits the IP address and browser information of the visitor to MousePlayer. The safest setup is therefore to let your consent tool block the snippet until the visitor agrees. Automatic detection reads the decision from Consented.eu, Usercentrics, Cookiebot, OneTrust, consentmanager, Borlabs Cookie and Complianz. With your own banner, call MousePlayer.setConsent(true) or setConsent(false). The MousePlayer script treats a Global Privacy Control signal of the browser as no consent.', 'mouseplayer-for-woocommerce' ) . '</p>',
			)
		);
		$screen->add_help_tab(
			array(
				'id'      => 'mouseplayer-wc-developers',
				'title'   => __( 'Developers', 'mouseplayer-for-woocommerce' ),
				'content' => '<p>' . esc_html__( 'Filters: mouseplayer_wc_is_active (switch tracking off for a request), mouseplayer_wc_script_attributes (attributes of the snippet element), mouseplayer_wc_cmps (consent tools and their attributes), mouseplayer_wc_purchase_statuses (order statuses that count as a purchase). Add data-mp-mask to any element to mask its content, and data-mp-ignore to a field to keep its input out of the recording.', 'mouseplayer-for-woocommerce' ) . '</p>',
			)
		);
		$screen->set_help_sidebar(
			'<p><strong>' . esc_html__( 'More information', 'mouseplayer-for-woocommerce' ) . '</strong></p>'
			. '<p><a href="' . esc_url( mouseplayer_wc_site_url( '/' ) ) . '" target="_blank" rel="noopener">MousePlayer</a></p>'
			. '<p><a href="' . esc_url( mouseplayer_wc_site_url( '/en/company/privacy-policy' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Privacy policy', 'mouseplayer-for-woocommerce' ) . '</a></p>'
		);
	}

	/**
	 * Register the option and the fields.
	 */
	public function register_settings() {
		register_setting(
			self::GROUP,
			MOUSEPLAYER_WC_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => mouseplayer_wc_defaults(),
			)
		);

		$sections = array(
			'connection' => __( 'Connection', 'mouseplayer-for-woocommerce' ),
			'consent'    => __( 'Consent', 'mouseplayer-for-woocommerce' ),
			'privacy'    => __( 'Privacy', 'mouseplayer-for-woocommerce' ),
			'events'     => __( 'Shop events', 'mouseplayer-for-woocommerce' ),
			'verify'     => __( 'Domain verification', 'mouseplayer-for-woocommerce' ),
		);
		foreach ( $sections as $id => $title ) {
			add_settings_section( 'mouseplayer_wc_' . $id, $title, array( $this, 'section_intro' ), self::PAGE_SLUG );
		}

		$fields = array(
			array( 'enabled', __( 'Tracking', 'mouseplayer-for-woocommerce' ), 'connection' ),
			array( 'channel_id', __( 'Channel ID', 'mouseplayer-for-woocommerce' ), 'connection' ),
			array( 'api_key', __( 'API key (optional)', 'mouseplayer-for-woocommerce' ), 'connection' ),
			array( 'host', __( 'Tracking host', 'mouseplayer-for-woocommerce' ), 'connection' ),
			array( 'consent_mode', __( 'How consent is given', 'mouseplayer-for-woocommerce' ), 'consent' ),
			array( 'consent_cmp', __( 'Consent tool', 'mouseplayer-for-woocommerce' ), 'consent' ),
			array( 'exclude_roles', __( 'Do not record', 'mouseplayer-for-woocommerce' ), 'privacy' ),
			array( 'masking', __( 'Masking', 'mouseplayer-for-woocommerce' ), 'privacy' ),
			array( 'identify_customers', __( 'Customer accounts', 'mouseplayer-for-woocommerce' ), 'privacy' ),
			array( 'events', __( 'Events', 'mouseplayer-for-woocommerce' ), 'events' ),
			array( 'event_data', __( 'Data sent with events', 'mouseplayer-for-woocommerce' ), 'events' ),
			array( 'verify_token', __( 'Verification token', 'mouseplayer-for-woocommerce' ), 'verify' ),
		);
		foreach ( $fields as $field ) {
			$args = array( 'key' => $field[0] );
			if ( in_array( $field[0], array( 'channel_id', 'api_key', 'host', 'consent_cmp', 'verify_token' ), true ) ) {
				$args['label_for'] = 'mouseplayer_wc_' . $field[0];
			}
			if ( 'consent_cmp' === $field[0] ) {
				$args['class'] = 'mouseplayer-wc-cmp-row';
			}
			add_settings_field( 'mouseplayer_wc_' . $field[0], $field[1], array( $this, 'field' ), self::PAGE_SLUG, 'mouseplayer_wc_' . $field[2], $args );
		}
	}

	/**
	 * Short introduction under each section title.
	 *
	 * @param array $section Section.
	 */
	public function section_intro( $section ) {
		$texts = array(
			'mouseplayer_wc_connection' => __( 'The Channel ID connects this shop with your MousePlayer project. No visitor data is sent to MousePlayer until tracking is switched on; before that, only a click on Check connection contacts MousePlayer.', 'mouseplayer-for-woocommerce' ),
			'mouseplayer_wc_consent'    => __( 'Session replay needs the consent of your visitors. The MousePlayer script records nothing until it receives a positive consent signal.', 'mouseplayer-for-woocommerce' ),
			'mouseplayer_wc_privacy'    => __( 'MousePlayer never records what is typed into password and payment fields. Card fields of most payment services run in their own frames and are not part of the recording.', 'mouseplayer-for-woocommerce' ),
			'mouseplayer_wc_events'     => __( 'The six steps of the Standard Ecommerce Funnel in MousePlayer. They are only sent together with a recording, that is after consent.', 'mouseplayer-for-woocommerce' ),
			'mouseplayer_wc_verify'     => __( 'Only needed if MousePlayer asks you to prove that you own this domain. The plugin then adds the verification meta element to the home page.', 'mouseplayer-for-woocommerce' ),
		);
		if ( isset( $texts[ $section['id'] ] ) ) {
			echo '<p class="mouseplayer-wc-intro">' . esc_html( $texts[ $section['id'] ] ) . '</p>';
		}
	}

	/**
	 * Name attribute of a setting.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	private function name( $key ) {
		return MOUSEPLAYER_WC_OPTION . '[' . $key . ']';
	}

	/**
	 * A checkbox with label and optional description.
	 *
	 * @param array  $s     Settings.
	 * @param string $key   Setting key.
	 * @param string $label Label.
	 * @param string $hint  Description.
	 */
	private function checkbox( $s, $key, $label, $hint = '' ) {
		$id = 'mouseplayer_wc_' . $key;
		echo '<p class="mouseplayer-wc-check"><label for="' . esc_attr( $id ) . '">';
		echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $this->name( $key ) ) . '" value="1" ' . checked( ! empty( $s[ $key ] ), true, false ) . ' /> ';
		echo esc_html( $label ) . '</label>';
		if ( '' !== $hint ) {
			echo '<span class="description">' . esc_html( $hint ) . '</span>';
		}
		echo '</p>';
	}

	/**
	 * A checkbox shown as a switch, with label and description.
	 *
	 * @param array  $s     Settings.
	 * @param string $key   Setting key.
	 * @param string $label Label.
	 * @param string $hint  Description.
	 */
	private function toggle( $s, $key, $label, $hint = '' ) {
		$id = 'mouseplayer_wc_' . $key;
		echo '<p class="mouseplayer-wc-check mouseplayer-wc-toggle"><label for="' . esc_attr( $id ) . '">';
		echo '<input type="checkbox" role="switch" id="' . esc_attr( $id ) . '" name="' . esc_attr( $this->name( $key ) ) . '" value="1" ' . checked( ! empty( $s[ $key ] ), true, false ) . ' />';
		echo '<span class="mouseplayer-wc-switch" aria-hidden="true"></span>' . esc_html( $label ) . '</label>';
		if ( '' !== $hint ) {
			echo '<span class="description">' . esc_html( $hint ) . '</span>';
		}
		echo '</p>';
	}

	/**
	 * The six funnel steps as a small diagram. Steps that are switched off are
	 * shown as gaps; admin.js follows the checkboxes.
	 *
	 * @param array $s Settings.
	 */
	private function funnel( $s ) {
		$steps = array(
			'ev_view_item_list' => array( 'list-view', __( 'Product lists', 'mouseplayer-for-woocommerce' ) ),
			'ev_select_item'    => array( 'admin-links', __( 'Product clicked', 'mouseplayer-for-woocommerce' ) ),
			'ev_view_item'      => array( 'visibility', __( 'Product page', 'mouseplayer-for-woocommerce' ) ),
			'ev_add_to_cart'    => array( 'cart', __( 'Added to cart', 'mouseplayer-for-woocommerce' ) ),
			'ev_begin_checkout' => array( 'clipboard', __( 'Checkout', 'mouseplayer-for-woocommerce' ) ),
			'ev_purchase'       => array( 'yes-alt', __( 'Purchase', 'mouseplayer-for-woocommerce' ) ),
		);
		$on    = 0;
		foreach ( array_keys( $steps ) as $key ) {
			$on += empty( $s[ $key ] ) ? 0 : 1;
		}
		echo '<div class="mouseplayer-wc-funnel-box">';
		/* translators: 1: number of funnel steps switched on, 2: number of funnel steps. */
		echo '<p class="mouseplayer-wc-funnel-summary" id="mouseplayer-wc-funnel-summary" aria-live="polite" data-text="' . esc_attr__( '%1$d of %2$d funnel steps are sent.', 'mouseplayer-for-woocommerce' ) . '">' . esc_html( sprintf( __( '%1$d of %2$d funnel steps are sent.', 'mouseplayer-for-woocommerce' ), $on, count( $steps ) ) ) . '</p>';
		echo '<ol class="mouseplayer-wc-funnel" aria-hidden="true">';
		$i = 0;
		foreach ( $steps as $key => $step ) {
			++$i;
			$width = 100 - ( $i - 1 ) * 9;
			echo '<li class="' . esc_attr( empty( $s[ $key ] ) ? 'is-off' : 'is-on' ) . '" data-step="' . esc_attr( $key ) . '"><span class="mouseplayer-wc-funnel-bar" style="' . esc_attr( 'width:' . $width . '%' ) . '"><span class="dashicons dashicons-' . esc_attr( $step[0] ) . '"></span><span class="mouseplayer-wc-funnel-name">' . esc_html( $step[1] ) . '</span></span></li>';
		}
		echo '</ol>';
		echo '<p class="description">' . esc_html__( 'Steps that are switched off stay empty in the funnel in MousePlayer.', 'mouseplayer-for-woocommerce' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Render one settings field.
	 *
	 * @param array $args Field arguments.
	 */
	public function field( $args ) {
		$s   = mouseplayer_wc_get_settings();
		$key = $args['key'];

		switch ( $key ) {
			case 'enabled':
				$this->toggle( $s, 'enabled', __( 'Record sessions on this shop', 'mouseplayer-for-woocommerce' ), __( 'Adds the MousePlayer snippet to every page of the shop. The MousePlayer script starts recording only after consent.', 'mouseplayer-for-woocommerce' ) );
				break;

			case 'channel_id':
				echo '<input type="text" class="regular-text code" id="mouseplayer_wc_channel_id" name="' . esc_attr( $this->name( 'channel_id' ) ) . '" value="' . esc_attr( $s['channel_id'] ) . '" autocomplete="off" spellcheck="false" pattern="[A-Za-z0-9_\-]{4,64}" aria-describedby="mouseplayer_wc_channel_hint mouseplayer_wc_channel_check" />';
				echo '<p class="mouseplayer-wc-field-check" id="mouseplayer_wc_channel_check" aria-live="polite" data-bad="' . esc_attr__( 'Only letters, digits, hyphens and underscores, 4 to 64 characters.', 'mouseplayer-for-woocommerce' ) . '"></p>';
				echo '<p class="description" id="mouseplayer_wc_channel_hint">' . esc_html__( 'In MousePlayer: open the project and click Code. The Channel ID is the value after channel: in the snippet.', 'mouseplayer-for-woocommerce' ) . '</p>';
				break;

			case 'api_key':
				$saved = '' !== (string) $s['api_key'];
				echo '<input type="password" class="regular-text code" id="mouseplayer_wc_api_key" name="' . esc_attr( $this->name( 'api_key' ) ) . '" value="" autocomplete="new-password" spellcheck="false" placeholder="' . esc_attr( $saved ? __( 'Saved. Leave empty to keep it.', 'mouseplayer-for-woocommerce' ) : 'mp_ro_…' ) . '" />';
				if ( $saved ) {
					$this->checkbox( array( 'api_key_remove' => 0 ), 'api_key_remove', __( 'Remove the saved key', 'mouseplayer-for-woocommerce' ) );
				}
				echo '<p class="description">' . esc_html__( 'Business and Enterprise plans: a read-only key (starts with mp_ro_) lets the connection check confirm the project, its domain and the latest recording. Tracking works without a key.', 'mouseplayer-for-woocommerce' ) . '</p>';
				break;

			case 'host':
				echo '<input type="text" class="regular-text code" id="mouseplayer_wc_host" name="' . esc_attr( $this->name( 'host' ) ) . '" value="' . esc_attr( $s['host'] ) . '" spellcheck="false" />';
				/* translators: %s: default host name. */
				echo '<p class="description">' . esc_html( sprintf( __( 'Leave it at %s unless MousePlayer gave you another host. Only mouseplayer.com and its subdomains are accepted.', 'mouseplayer-for-woocommerce' ), MOUSEPLAYER_WC_DEFAULT_HOST ) ) . '</p>';
				break;

			case 'consent_mode':
				$modes = array(
					'cmp_block'      => array( __( 'My consent tool blocks the snippet until the visitor agrees (recommended)', 'mouseplayer-for-woocommerce' ), __( 'The snippet is printed as a blocked script with the attributes of your consent tool. Before consent it does not even run.', 'mouseplayer-for-woocommerce' ) ),
					'wp_consent_api' => array( __( 'WP Consent API', 'mouseplayer-for-woocommerce' ), __( 'For consent plugins that support the WP Consent API. Recording follows the statistics category.', 'mouseplayer-for-woocommerce' ) ),
					'auto'           => array( __( 'Automatic detection or my own banner', 'mouseplayer-for-woocommerce' ), __( 'The snippet loads and waits. It reads the decision of a supported consent tool, or your banner calls MousePlayer.setConsent(true) and MousePlayer.setConsent(false).', 'mouseplayer-for-woocommerce' ) ),
				);
				echo '<fieldset class="mouseplayer-wc-options"><legend class="screen-reader-text">' . esc_html__( 'How consent is given', 'mouseplayer-for-woocommerce' ) . '</legend>';
				foreach ( $modes as $mode => $texts ) {
					echo '<label class="mouseplayer-wc-option"><input type="radio" name="' . esc_attr( $this->name( 'consent_mode' ) ) . '" value="' . esc_attr( $mode ) . '" ' . checked( $s['consent_mode'], $mode, false ) . ' />';
					echo '<span class="mouseplayer-wc-option-body"><span class="mouseplayer-wc-option-title">' . esc_html( $texts[0] ) . '</span>';
					echo '<span class="description">' . esc_html( $texts[1] ) . '</span>';
					if ( 'wp_consent_api' === $mode ) {
						echo '<span class="description">' . esc_html( function_exists( 'wp_has_consent' ) ? __( 'Status: the WP Consent API is active on this site.', 'mouseplayer-for-woocommerce' ) : __( 'Status: the WP Consent API plugin is not active on this site.', 'mouseplayer-for-woocommerce' ) ) . '</span>';
					}
					echo '</span></label>';
				}
				echo '</fieldset>';
				break;

			case 'consent_cmp':
				echo '<select id="mouseplayer_wc_consent_cmp" name="' . esc_attr( $this->name( 'consent_cmp' ) ) . '">';
				echo '<option value="">' . esc_html__( 'Choose your consent tool', 'mouseplayer-for-woocommerce' ) . '</option>';
				foreach ( mouseplayer_wc_cmps() as $cmp => $info ) {
					echo '<option value="' . esc_attr( $cmp ) . '" ' . selected( $s['consent_cmp'], $cmp, false ) . '>' . esc_html( $info['name'] ) . '</option>';
				}
				echo '</select>';
				echo '<p class="description">' . esc_html__( 'Used with the first option. In Consented.eu, add the MousePlayer service; in the other tools, MousePlayer belongs to the statistics category. The attributes can change between major versions of a consent tool: check its documentation if no sessions arrive after consent.', 'mouseplayer-for-woocommerce' ) . '</p>';
				break;

			case 'exclude_roles':
				echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Do not record', 'mouseplayer-for-woocommerce' ) . '</legend>';
				foreach ( wp_roles()->get_names() as $role => $label ) {
					echo '<label class="mouseplayer-wc-role"><input type="checkbox" name="' . esc_attr( $this->name( 'exclude_roles' ) ) . '[]" value="' . esc_attr( $role ) . '" ' . checked( in_array( $role, (array) $s['exclude_roles'], true ), true, false ) . ' /> ' . esc_html( translate_user_role( $label ) ) . '</label>';
				}
				echo '<p class="description">' . esc_html__( 'Logged-in users with these roles are never recorded and send no events. Visitors who are not logged in are recorded after consent.', 'mouseplayer-for-woocommerce' ) . '</p>';
				echo '</fieldset>';
				break;

			case 'masking':
				$this->checkbox( $s, 'mask_checkout', __( 'Mask checkout, order confirmation, order tracking and My Account', 'mouseplayer-for-woocommerce' ), __( 'The content of these pages is replaced by asterisks before it leaves the browser: names, addresses, email addresses and order details. Clicks and scrolling stay visible.', 'mouseplayer-for-woocommerce' ) );
				$this->checkbox( $s, 'mask_cart', __( 'Mask the cart page as well', 'mouseplayer-for-woocommerce' ), __( 'Hides product names, prices and the postcode of the shipping calculator on the cart page.', 'mouseplayer-for-woocommerce' ) );
				$this->checkbox( $s, 'ignore_typing', __( 'Do not record typing in shop forms', 'mouseplayer-for-woocommerce' ), __( 'Typing in checkout, address and account fields is not recorded at all, not even as asterisks. Applies to the classic and the block checkout.', 'mouseplayer-for-woocommerce' ) );
				break;

			case 'identify_customers':
				$this->checkbox( $s, 'identify_customers', __( 'Link recordings of logged-in customers with a pseudonym', 'mouseplayer-for-woocommerce' ), __( 'Sends a stable pseudonym derived from the user ID and a secret key of this site, never the email address. Lets you follow the visits of one customer account. Leave it off if you do not need it.', 'mouseplayer-for-woocommerce' ) );
				break;

			case 'events':
				echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Events', 'mouseplayer-for-woocommerce' ) . '</legend>';
				$this->checkbox( $s, 'ev_view_item_list', __( 'view_item_list: shop, category, tag and product search pages', 'mouseplayer-for-woocommerce' ) );
				$this->checkbox( $s, 'ev_select_item', __( 'select_item: click on a product in a list', 'mouseplayer-for-woocommerce' ) );
				$this->checkbox( $s, 'ev_view_item', __( 'view_item: product page', 'mouseplayer-for-woocommerce' ) );
				$this->checkbox( $s, 'ev_add_to_cart', __( 'add_to_cart: product page, lists and blocks', 'mouseplayer-for-woocommerce' ) );
				$this->checkbox( $s, 'ev_begin_checkout', __( 'begin_checkout: checkout page with a filled cart', 'mouseplayer-for-woocommerce' ) );
				$this->checkbox( $s, 'ev_purchase', __( 'purchase: order confirmation, once per order', 'mouseplayer-for-woocommerce' ) );
				echo '</fieldset>';
				$this->funnel( $s );
				break;

			case 'event_data':
				$this->checkbox( $s, 'send_value', __( 'Send prices, order value and currency', 'mouseplayer-for-woocommerce' ), __( 'Needed for the revenue under the Purchase step of the funnel.', 'mouseplayer-for-woocommerce' ) );
				$this->checkbox( $s, 'send_context', __( 'Send the page context', 'mouseplayer-for-woocommerce' ), __( 'Page type, product ID, category, stock status and cart size; with prices switched on also the product price, the cart value and the currency. MousePlayer uses it to describe sessions, for example "compared three products of the same category".', 'mouseplayer-for-woocommerce' ) );
				break;

			case 'verify_token':
				echo '<input type="text" class="regular-text code" id="mouseplayer_wc_verify_token" name="' . esc_attr( $this->name( 'verify_token' ) ) . '" value="' . esc_attr( $s['verify_token'] ) . '" placeholder="mp-verify-…" spellcheck="false" />';
				echo '<p class="description">' . esc_html__( 'Copy the token from the domain verification in your MousePlayer project settings, save here, then start the check in MousePlayer.', 'mouseplayer-for-woocommerce' ) . '</p>';
				break;
		}
	}

	/**
	 * Validate everything that comes in.
	 *
	 * @param mixed $input Raw form data.
	 * @return array
	 */
	public function sanitize( $input ) {
		$old = mouseplayer_wc_get_settings();
		$out = $old;
		if ( ! is_array( $input ) ) {
			return $old;
		}

		$channel = isset( $input['channel_id'] ) ? trim( sanitize_text_field( (string) $input['channel_id'] ) ) : '';
		if ( '' === $channel || preg_match( '/^[A-Za-z0-9_-]{4,64}$/', $channel ) ) {
			$out['channel_id'] = $channel;
		} else {
			add_settings_error( MOUSEPLAYER_WC_OPTION, 'channel', __( 'The Channel ID may only contain letters, digits, hyphens and underscores. The previous value was kept.', 'mouseplayer-for-woocommerce' ) );
		}

		$host = isset( $input['host'] ) ? strtolower( trim( sanitize_text_field( (string) $input['host'] ) ) ) : '';
		$host = preg_replace( '#^https?://#', '', $host );
		$host = preg_replace( '#[/?\#].*$#', '', $host );
		if ( '' === $host ) {
			$out['host'] = MOUSEPLAYER_WC_DEFAULT_HOST;
		} elseif ( MousePlayer_WC_Connection::is_allowed_host( $host ) ) {
			$out['host'] = $host;
		} else {
			add_settings_error( MOUSEPLAYER_WC_OPTION, 'host', __( 'The tracking host must be mouseplayer.com or one of its subdomains. The previous value was kept.', 'mouseplayer-for-woocommerce' ) );
		}

		// The API key lives in its own option that is not autoloaded (MOUSEPLAYER_WC_KEY_OPTION).
		if ( ! empty( $input['api_key_remove'] ) ) {
			delete_option( MOUSEPLAYER_WC_KEY_OPTION );
		} elseif ( isset( $input['api_key'] ) && '' !== trim( (string) $input['api_key'] ) ) {
			$key = trim( sanitize_text_field( (string) $input['api_key'] ) );
			if ( preg_match( '/^mp_[a-z]{2,4}_[A-Za-z0-9]{16,128}$/', $key ) ) {
				update_option( MOUSEPLAYER_WC_KEY_OPTION, $key, false );
			} else {
				add_settings_error( MOUSEPLAYER_WC_OPTION, 'api_key', __( 'This does not look like a MousePlayer API key (it starts with mp_ro_ or mp_rw_). The key was not saved.', 'mouseplayer-for-woocommerce' ) );
			}
		}

		$mode                = isset( $input['consent_mode'] ) ? sanitize_key( $input['consent_mode'] ) : 'auto';
		$out['consent_mode'] = in_array( $mode, array( 'cmp_block', 'wp_consent_api', 'auto' ), true ) ? $mode : 'auto';
		$cmp                 = isset( $input['consent_cmp'] ) ? sanitize_key( $input['consent_cmp'] ) : '';
		$out['consent_cmp']  = array_key_exists( $cmp, mouseplayer_wc_cmps() ) ? $cmp : '';
		if ( 'cmp_block' === $out['consent_mode'] && '' === $out['consent_cmp'] ) {
			add_settings_error( MOUSEPLAYER_WC_OPTION, 'cmp', __( 'Choose the consent tool that blocks the snippet. Until then the snippet is printed without blocking attributes and waits for a consent signal.', 'mouseplayer-for-woocommerce' ), 'warning' );
		}

		$roles                = isset( $input['exclude_roles'] ) && is_array( $input['exclude_roles'] ) ? array_map( 'sanitize_key', $input['exclude_roles'] ) : array();
		$out['exclude_roles'] = array_values( array_intersect( $roles, array_keys( wp_roles()->get_names() ) ) );

		$flags = array( 'enabled', 'mask_checkout', 'mask_cart', 'ignore_typing', 'identify_customers', 'ev_view_item_list', 'ev_select_item', 'ev_view_item', 'ev_add_to_cart', 'ev_begin_checkout', 'ev_purchase', 'send_value', 'send_context' );
		foreach ( $flags as $flag ) {
			$out[ $flag ] = ! empty( $input[ $flag ] ) ? 1 : 0;
		}

		$token = isset( $input['verify_token'] ) ? trim( sanitize_text_field( (string) $input['verify_token'] ) ) : '';
		if ( '' === $token || preg_match( '/^mp-verify-[a-f0-9]{32}$/', $token ) ) {
			$out['verify_token'] = $token;
		} else {
			add_settings_error( MOUSEPLAYER_WC_OPTION, 'verify', __( 'The verification token looks like mp-verify- followed by 32 characters. The previous value was kept.', 'mouseplayer-for-woocommerce' ) );
		}

		if ( ! empty( $out['enabled'] ) && '' === $out['channel_id'] ) {
			add_settings_error( MOUSEPLAYER_WC_OPTION, 'nochannel', __( 'Tracking is switched on, but without a Channel ID nothing is recorded.', 'mouseplayer-for-woocommerce' ), 'warning' );
		}

		// A changed setup makes the last connection check meaningless.
		delete_transient( self::CHECK_KEY );

		unset( $out['api_key'] );
		return array_intersect_key( $out, mouseplayer_wc_defaults() );
	}

	/**
	 * Run the connection check and go back to the settings page.
	 */
	public function handle_check() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mouseplayer-for-woocommerce' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'mouseplayer_wc_check' );

		$s = mouseplayer_wc_get_settings();
		set_transient(
			self::CHECK_KEY,
			array(
				'time' => time(),
				'rows' => MousePlayer_WC_Connection::run( $s ),
			),
			DAY_IN_SECONDS
		);
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) . '#mouseplayer-wc-status' );
		exit;
	}

	/**
	 * Local checks of the setup, without any request to MousePlayer.
	 *
	 * @param array $s Settings.
	 * @return array Rows: status, label, message.
	 */
	private function local_status( $s ) {
		$rows = array();
		$cmps = mouseplayer_wc_cmps();

		if ( '' === (string) $s['channel_id'] ) {
			$rows[] = array( 'error', __( 'Connection', 'mouseplayer-for-woocommerce' ), __( 'No Channel ID yet. Copy it from your MousePlayer project (Project > Code).', 'mouseplayer-for-woocommerce' ) );
		} elseif ( empty( $s['enabled'] ) ) {
			$rows[] = array( 'warning', __( 'Connection', 'mouseplayer-for-woocommerce' ), __( 'Channel ID saved, tracking is switched off. No visitor data is sent to MousePlayer.', 'mouseplayer-for-woocommerce' ) );
		} else {
			/* translators: 1: Channel ID, 2: host name. */
			$rows[] = array( 'ok', __( 'Connection', 'mouseplayer-for-woocommerce' ), sprintf( __( 'Tracking is on for channel %1$s via %2$s.', 'mouseplayer-for-woocommerce' ), $s['channel_id'], MousePlayer_WC_Connection::tracking_host( $s ) ) );
		}

		if ( 'cmp_block' === $s['consent_mode'] ) {
			if ( isset( $cmps[ $s['consent_cmp'] ] ) ) {
				/* translators: %s: name of the consent tool. */
				$rows[] = array( 'ok', __( 'Consent', 'mouseplayer-for-woocommerce' ), sprintf( __( 'The snippet is blocked until %s reports consent.', 'mouseplayer-for-woocommerce' ), $cmps[ $s['consent_cmp'] ]['name'] ) );
			} else {
				$rows[] = array( 'warning', __( 'Consent', 'mouseplayer-for-woocommerce' ), __( 'No consent tool chosen. The snippet waits for a consent signal, but is not blocked.', 'mouseplayer-for-woocommerce' ) );
			}
		} elseif ( 'wp_consent_api' === $s['consent_mode'] ) {
			if ( function_exists( 'wp_has_consent' ) && function_exists( 'wp_get_consent_type' ) && ! wp_get_consent_type() ) {
				$rows[] = array( 'warning', __( 'Consent', 'mouseplayer-for-woocommerce' ), __( 'The WP Consent API is active, but no consent plugin sets a consent type. Until one does, nothing is recorded.', 'mouseplayer-for-woocommerce' ) );
			} elseif ( function_exists( 'wp_has_consent' ) ) {
				$rows[] = array( 'ok', __( 'Consent', 'mouseplayer-for-woocommerce' ), __( 'Recording follows the statistics category of the WP Consent API.', 'mouseplayer-for-woocommerce' ) );
			} else {
				$rows[] = array( 'error', __( 'Consent', 'mouseplayer-for-woocommerce' ), __( 'The WP Consent API plugin is not active. Without it nothing is recorded. Install it or choose another consent option.', 'mouseplayer-for-woocommerce' ) );
			}
		} else {
			$rows[] = array( 'info', __( 'Consent', 'mouseplayer-for-woocommerce' ), __( 'Automatic detection. Recording starts when a supported consent tool or your own banner reports consent.', 'mouseplayer-for-woocommerce' ) );
		}

		if ( mouseplayer_wc_is_excluded_user( $s ) ) {
			$rows[] = array( 'info', __( 'Your visits', 'mouseplayer-for-woocommerce' ), __( 'Your role is excluded, so your own visits are not recorded. Test in a private window without logging in.', 'mouseplayer-for-woocommerce' ) );
		}

		if ( ! empty( $s['mask_checkout'] ) ) {
			$rows[] = array( 'ok', __( 'Privacy', 'mouseplayer-for-woocommerce' ), __( 'Checkout, order confirmation and account pages are masked.', 'mouseplayer-for-woocommerce' ) );
		} else {
			$rows[] = array( 'warning', __( 'Privacy', 'mouseplayer-for-woocommerce' ), __( 'Checkout and account pages are not masked. Names and addresses shown on these pages become part of the recording.', 'mouseplayer-for-woocommerce' ) );
		}

		return $rows;
	}

	/**
	 * One status line.
	 *
	 * @param string $status  ok, warning, error or info.
	 * @param string $label   Label.
	 * @param string $message Message.
	 */
	private function status_row( $status, $label, $message ) {
		$icons  = array(
			'ok'      => 'dashicons-yes-alt',
			'warning' => 'dashicons-warning',
			'error'   => 'dashicons-dismiss',
			'info'    => 'dashicons-info',
		);
		$words  = array(
			'ok'      => __( 'OK', 'mouseplayer-for-woocommerce' ),
			'warning' => __( 'Warning', 'mouseplayer-for-woocommerce' ),
			'error'   => __( 'Problem', 'mouseplayer-for-woocommerce' ),
			'info'    => __( 'Note', 'mouseplayer-for-woocommerce' ),
		);
		$status = isset( $icons[ $status ] ) ? $status : 'info';
		echo '<li class="mouseplayer-wc-status-' . esc_attr( $status ) . '">';
		echo '<span class="dashicons ' . esc_attr( $icons[ $status ] ) . '" aria-hidden="true"></span>';
		echo '<span class="screen-reader-text">' . esc_html( $words[ $status ] ) . ': </span>';
		echo '<strong>' . esc_html( $label ) . '</strong> ' . esc_html( $message );
		echo '</li>';
	}

	/**
	 * Overall state for the page title, from the saved settings only.
	 *
	 * @param array $s Settings.
	 * @return array Level, dashicon, text.
	 */
	private function overall( $s ) {
		if ( '' === (string) $s['channel_id'] ) {
			return array( 'error', 'dismiss', __( 'No Channel ID', 'mouseplayer-for-woocommerce' ) );
		}
		if ( empty( $s['enabled'] ) ) {
			return array( 'off', 'controls-pause', __( 'Tracking off', 'mouseplayer-for-woocommerce' ) );
		}
		return array( 'ok', 'controls-play', __( 'Tracking on', 'mouseplayer-for-woocommerce' ) );
	}

	/**
	 * The settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s       = mouseplayer_wc_get_settings();
		$check   = get_transient( self::CHECK_KEY );
		$rows    = $this->local_status( $s );
		$overall = $this->overall( $s );
		$events  = 0;
		foreach ( array( 'ev_view_item_list', 'ev_select_item', 'ev_view_item', 'ev_add_to_cart', 'ev_begin_checkout', 'ev_purchase' ) as $ev ) {
			$events += empty( $s[ $ev ] ) ? 0 : 1;
		}
		$icons    = array(
			'mouseplayer_wc_connection' => 'admin-links',
			'mouseplayer_wc_consent'    => 'shield',
			'mouseplayer_wc_privacy'    => 'hidden',
			'mouseplayer_wc_events'     => 'chart-bar',
			'mouseplayer_wc_verify'     => 'yes-alt',
		);
		$sections = isset( $GLOBALS['wp_settings_sections'][ self::PAGE_SLUG ] ) ? (array) $GLOBALS['wp_settings_sections'][ self::PAGE_SLUG ] : array();
		?>
		<div class="wrap mouseplayer-wc">
			<div class="mouseplayer-wc-head">
				<h1><?php esc_html_e( 'MousePlayer for WooCommerce', 'mouseplayer-for-woocommerce' ); ?></h1>
				<span class="mouseplayer-wc-pill mouseplayer-wc-pill-<?php echo esc_attr( $overall[0] ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( $overall[1] ); ?>" aria-hidden="true"></span><?php echo esc_html( $overall[2] ); ?></span>
				<span class="mouseplayer-wc-version"><?php echo esc_html( MOUSEPLAYER_WC_VERSION ); ?></span>
			</div>
			<hr class="wp-header-end">
			<p class="mouseplayer-wc-lead"><?php esc_html_e( 'Session replay, heatmaps and the ecommerce funnel for this shop. MousePlayer is an external service. When tracking is switched on, every shop page loads the MousePlayer script from mouseplayer.com - with the blocking consent option only after consent, otherwise with the page, which transmits the IP address and browser information of the visitor. Recordings and shop events are sent only after the visitor has consented.', 'mouseplayer-for-woocommerce' ); ?>
				<a href="<?php echo esc_url( mouseplayer_wc_site_url( '/en/company/privacy-policy' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Privacy policy', 'mouseplayer-for-woocommerce' ); ?></a> &middot;
				<a href="<?php echo esc_url( mouseplayer_wc_site_url( '/en/company/terms-of-service' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Terms of service', 'mouseplayer-for-woocommerce' ); ?></a>
			</p>

			<?php settings_errors(); ?>

			<nav class="mouseplayer-wc-nav" aria-label="<?php esc_attr_e( 'Sections of this page', 'mouseplayer-for-woocommerce' ); ?>">
				<a href="#mouseplayer-wc-status"><span class="dashicons dashicons-dashboard" aria-hidden="true"></span><?php esc_html_e( 'Status', 'mouseplayer-for-woocommerce' ); ?></a>
				<?php foreach ( $sections as $section ) : ?>
					<a href="#<?php echo esc_attr( str_replace( '_', '-', $section['id'] ) ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( $icons[ $section['id'] ] ?? 'admin-generic' ); ?>" aria-hidden="true"></span><?php echo esc_html( $section['title'] ); ?></a>
				<?php endforeach; ?>
			</nav>

			<div class="mouseplayer-wc-card" id="mouseplayer-wc-status">
				<h2><?php esc_html_e( 'Status', 'mouseplayer-for-woocommerce' ); ?></h2>
				<div class="mouseplayer-wc-tiles">
					<?php
					foreach ( $rows as $row ) {
						if ( __( 'Your visits', 'mouseplayer-for-woocommerce' ) === $row[1] ) {
							continue;
						}
						$this->tile( $row[0], $row[1], $this->tile_word( $row[0] ) );
					}
					/* translators: 1: number of funnel steps switched on, 2: number of funnel steps. */
					$this->tile( 6 === $events ? 'ok' : ( $events ? 'warning' : 'error' ), __( 'Shop events', 'mouseplayer-for-woocommerce' ), sprintf( __( '%1$d of %2$d', 'mouseplayer-for-woocommerce' ), $events, 6 ) );
					?>
				</div>
				<ul class="mouseplayer-wc-status">
					<?php
					foreach ( $rows as $row ) {
						$this->status_row( $row[0], $row[1], $row[2] );
					}
					?>
				</ul>
				<?php if ( is_array( $check ) && ! empty( $check['rows'] ) ) : ?>
					<h3>
						<?php
						/* translators: %s: date and time of the check. */
						echo esc_html( sprintf( __( 'Connection check of %s', 'mouseplayer-for-woocommerce' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $check['time'] ) ) );
						?>
						<span class="mouseplayer-wc-ago">
							<?php
							/* translators: %s: time span such as "40 mins". */
							echo esc_html( sprintf( __( '%s ago', 'mouseplayer-for-woocommerce' ), human_time_diff( (int) $check['time'] ) ) );
							?>
						</span>
					</h3>
					<ul class="mouseplayer-wc-status mouseplayer-wc-checked">
						<?php
						foreach ( (array) $check['rows'] as $row ) {
							if ( is_array( $row ) && isset( $row['status'], $row['label'], $row['message'] ) ) {
								$this->status_row( (string) $row['status'], (string) $row['label'], (string) $row['message'] );
							}
						}
						?>
					</ul>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mouseplayer-wc-check-form">
					<input type="hidden" name="action" value="mouseplayer_wc_check" />
					<?php wp_nonce_field( 'mouseplayer_wc_check' ); ?>
					<?php submit_button( __( 'Check connection', 'mouseplayer-for-woocommerce' ), 'secondary', 'submit', false, '' === (string) $s['channel_id'] ? array( 'disabled' => 'disabled' ) : array( 'data-busy' => __( 'Checking…', 'mouseplayer-for-woocommerce' ) ) ); ?>
					<span class="description"><?php esc_html_e( 'Only on click: your server asks MousePlayer whether the tracking script is reachable and, with an API key, sends the key and the Channel ID to ask whether the project receives recordings. No visitor data is sent.', 'mouseplayer-for-woocommerce' ); ?></span>
				</form>
				<p class="mouseplayer-wc-privacy-hint">
					<?php
					printf(
						/* translators: %s: link to the privacy policy guide. */
						esc_html__( 'Suggested text for your privacy policy: %s.', 'mouseplayer-for-woocommerce' ),
						'<a href="' . esc_url( admin_url( 'options-privacy.php?tab=policyguide' ) ) . '">' . esc_html__( 'Settings > Privacy > Policy guide', 'mouseplayer-for-woocommerce' ) . '</a>'
					);
					?>
				</p>
			</div>

			<form method="post" action="options.php" class="mouseplayer-wc-form">
				<?php settings_fields( self::GROUP ); ?>
				<?php foreach ( $sections as $section ) : ?>
					<section class="mouseplayer-wc-card mouseplayer-wc-section" id="<?php echo esc_attr( str_replace( '_', '-', $section['id'] ) ); ?>">
						<h2><span class="dashicons dashicons-<?php echo esc_attr( $icons[ $section['id'] ] ?? 'admin-generic' ); ?>" aria-hidden="true"></span><?php echo esc_html( $section['title'] ); ?></h2>
						<?php
						if ( is_callable( $section['callback'] ) ) {
							call_user_func( $section['callback'], $section );
						}
						?>
						<table class="form-table" role="presentation">
							<?php do_settings_fields( self::PAGE_SLUG, $section['id'] ); ?>
						</table>
					</section>
				<?php endforeach; ?>
				<div class="mouseplayer-wc-savebar">
					<?php submit_button( null, 'primary', 'submit', false ); ?>
					<span class="description"><?php esc_html_e( 'Changes take effect on the shop pages after saving.', 'mouseplayer-for-woocommerce' ); ?></span>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * One tile of the status overview.
	 *
	 * @param string $status ok, warning, error or info.
	 * @param string $label  Label.
	 * @param string $value  Short value.
	 */
	private function tile( $status, $label, $value ) {
		$icons  = array(
			'ok'      => 'yes-alt',
			'warning' => 'warning',
			'error'   => 'dismiss',
			'info'    => 'info',
		);
		$status = isset( $icons[ $status ] ) ? $status : 'info';
		echo '<div class="mouseplayer-wc-tile mouseplayer-wc-tile-' . esc_attr( $status ) . '"><span class="dashicons dashicons-' . esc_attr( $icons[ $status ] ) . '" aria-hidden="true"></span>';
		echo '<span class="mouseplayer-wc-tile-label">' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong></div>';
	}

	/**
	 * Short word for a status.
	 *
	 * @param string $status ok, warning, error or info.
	 * @return string
	 */
	private function tile_word( $status ) {
		$words = array(
			'ok'      => __( 'OK', 'mouseplayer-for-woocommerce' ),
			'warning' => __( 'Check', 'mouseplayer-for-woocommerce' ),
			'error'   => __( 'Problem', 'mouseplayer-for-woocommerce' ),
			'info'    => __( 'Note', 'mouseplayer-for-woocommerce' ),
		);
		return isset( $words[ $status ] ) ? $words[ $status ] : $words['info'];
	}
}
