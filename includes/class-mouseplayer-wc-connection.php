<?php
/**
 * Connection check against MousePlayer. Runs only when an administrator clicks "Check connection".
 *
 * @package MousePlayer_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks whether the tracking script is reachable and, with an API key, whether the project exists and receives data.
 */
class MousePlayer_WC_Connection {

	/**
	 * Is the host a MousePlayer host (mouseplayer.com or a subdomain)?
	 *
	 * @param string $host Host name.
	 * @return bool
	 */
	public static function is_allowed_host( $host ) {
		$host = strtolower( (string) $host );
		return 'mouseplayer.com' === $host || ( strlen( $host ) > 16 && '.mouseplayer.com' === substr( $host, -16 ) && (bool) preg_match( '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/', $host ) );
	}

	/**
	 * URL of the tracking script.
	 *
	 * @param string $host Host name.
	 * @return string
	 */
	public static function script_url( $host ) {
		return 'https://' . $host . '/mp.js';
	}

	/**
	 * Host of this shop without www., as MousePlayer compares it.
	 *
	 * @return string
	 */
	public static function site_host() {
		$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		return preg_replace( '/^www\./', '', $host );
	}

	/**
	 * Run all checks.
	 *
	 * @param array $s Settings.
	 * @return array List of rows: status (ok, warning, error, info), label, message.
	 */
	public static function run( $s ) {
		$rows = array();
		$host = (string) $s['host'];

		$response = wp_remote_head(
			self::script_url( $host ),
			array(
				'timeout'     => 10,
				'redirection' => 2,
			)
		);
		if ( is_wp_error( $response ) ) {
			$rows[] = array(
				'status'  => 'error',
				'label'   => __( 'Tracking script', 'mouseplayer-for-woocommerce' ),
				'message' => sprintf(
					/* translators: 1: script URL, 2: error message. */
					__( '%1$s could not be reached: %2$s', 'mouseplayer-for-woocommerce' ),
					self::script_url( $host ),
					$response->get_error_message()
				),
			);
		} else {
			$code   = (int) wp_remote_retrieve_response_code( $response );
			$rows[] = array(
				'status'  => 200 === $code ? 'ok' : 'error',
				'label'   => __( 'Tracking script', 'mouseplayer-for-woocommerce' ),
				'message' => 200 === $code
					/* translators: %s: script URL. */
					? sprintf( __( '%s is reachable.', 'mouseplayer-for-woocommerce' ), self::script_url( $host ) )
					/* translators: 1: script URL, 2: HTTP status code. */
					: sprintf( __( '%1$s answered with HTTP %2$d.', 'mouseplayer-for-woocommerce' ), self::script_url( $host ), $code ),
			);
		}

		if ( '' === (string) $s['api_key'] ) {
			$rows[] = array(
				'status'  => 'info',
				'label'   => __( 'Project', 'mouseplayer-for-woocommerce' ),
				'message' => __( 'Without an API key the plugin cannot ask MousePlayer whether the Channel ID belongs to your account. Open the project in MousePlayer and check that sessions arrive after you have accepted the consent banner in a private window.', 'mouseplayer-for-woocommerce' ),
			);
			return $rows;
		}

		$projects = self::api_get( $host, '/api/v1/projects', $s['api_key'] );
		if ( isset( $projects['error'] ) ) {
			$rows[] = array(
				'status'  => 'error',
				'label'   => __( 'API key', 'mouseplayer-for-woocommerce' ),
				'message' => $projects['error'],
			);
			return $rows;
		}

		$found = null;
		foreach ( (array) ( $projects['data']['data'] ?? array() ) as $project ) {
			if ( is_array( $project ) && isset( $project['channelid'] ) && (string) $project['channelid'] === (string) $s['channel_id'] ) {
				$found = $project;
				break;
			}
		}
		if ( null === $found ) {
			$rows[] = array(
				'status'  => 'error',
				'label'   => __( 'Project', 'mouseplayer-for-woocommerce' ),
				'message' => __( 'The API key works, but no project with this Channel ID exists in its workspace. Copy the Channel ID again from MousePlayer (Project > Code).', 'mouseplayer-for-woocommerce' ),
			);
			return $rows;
		}

		$domain = preg_replace( '/^www\./', '', strtolower( (string) ( $found['domain'] ?? '' ) ) );
		$site   = self::site_host();
		$match  = ( '' !== $domain && ( $site === $domain || substr( $site, -strlen( '.' . $domain ) ) === '.' . $domain ) );
		$rows[] = array(
			'status'  => $match ? 'ok' : 'error',
			'label'   => __( 'Project', 'mouseplayer-for-woocommerce' ),
			'message' => $match
				/* translators: 1: project domain, 2: project status. */
				? sprintf( __( 'Project found for %1$s (status: %2$s).', 'mouseplayer-for-woocommerce' ), $domain, (string) ( $found['status'] ?? '' ) )
				/* translators: 1: project domain, 2: domain of this shop. */
				: sprintf( __( 'The project is registered for %1$s, but this shop runs on %2$s. MousePlayer only accepts recordings from the registered domain and its subdomains.', 'mouseplayer-for-woocommerce' ), $domain, $site ),
		);

		$sessions = self::api_get( $host, '/api/v1/projects/' . rawurlencode( (string) $s['channel_id'] ) . '/sessions?per_page=1', $s['api_key'] );
		if ( isset( $sessions['error'] ) ) {
			$rows[] = array(
				'status'  => 'warning',
				'label'   => __( 'Recordings', 'mouseplayer-for-woocommerce' ),
				'message' => $sessions['error'],
			);
			return $rows;
		}
		$total  = (int) ( $sessions['data']['pagination']['total'] ?? 0 );
		$latest = (string) ( $sessions['data']['data'][0]['start_time'] ?? '' );
		$rows[] = array(
			'status'  => $total > 0 ? 'ok' : 'warning',
			'label'   => __( 'Recordings', 'mouseplayer-for-woocommerce' ),
			'message' => $total > 0
				/* translators: 1: number of sessions, 2: start time of the latest session as sent by MousePlayer. */
				? sprintf( _n( '%1$d session recorded, latest started %2$s (time zone of MousePlayer).', '%1$d sessions recorded, latest started %2$s (time zone of MousePlayer).', $total, 'mouseplayer-for-woocommerce' ), $total, $latest )
				: __( 'No session recorded yet. Visit the shop in a private window, accept the consent banner and click around, then check again.', 'mouseplayer-for-woocommerce' ),
		);

		return $rows;
	}

	/**
	 * GET request against the MousePlayer REST API.
	 *
	 * @param string $host Host name.
	 * @param string $path Path with query.
	 * @param string $key  API key.
	 * @return array Either array( 'data' => decoded body ) or array( 'error' => message ).
	 */
	private static function api_get( $host, $path, $key ) {
		$response = wp_remote_get(
			'https://' . $host . $path,
			array(
				'timeout'     => 15,
				'redirection' => 0,
				'headers'     => array(
					'Authorization' => 'Bearer ' . $key,
					'Accept'        => 'application/json',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array(
				/* translators: %s: error message. */
				'error' => sprintf( __( 'The MousePlayer API could not be reached: %s', 'mouseplayer-for-woocommerce' ), $response->get_error_message() ),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 200 === $code && is_array( $body ) ) {
			return array( 'data' => $body );
		}
		if ( 401 === $code ) {
			return array( 'error' => __( 'MousePlayer did not accept the API key. Create a read-only key on the API keys page in MousePlayer and paste it again.', 'mouseplayer-for-woocommerce' ) );
		}
		if ( 403 === $code ) {
			return array( 'error' => __( 'The API is available on the Business and Enterprise plans. The tracking itself works on every plan; only this status check needs the API.', 'mouseplayer-for-woocommerce' ) );
		}
		if ( 429 === $code ) {
			return array( 'error' => __( 'Too many API requests. Wait a minute and check again.', 'mouseplayer-for-woocommerce' ) );
		}
		return array(
			/* translators: %d: HTTP status code. */
			'error' => sprintf( __( 'The MousePlayer API answered with HTTP %d.', 'mouseplayer-for-woocommerce' ), $code ),
		);
	}
}
