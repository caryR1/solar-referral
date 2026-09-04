<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSR_Redirect {

	const COOKIE_NAME = 'gsr_affiliate_code';
	const COOKIE_DAYS = 180;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( __CLASS__, 'add_query_var' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_redirect' ) );
	}

	public static function add_rewrite_rule() {
		add_rewrite_rule( '^go/([a-zA-Z0-9_-]+)/?$', 'index.php?gsr_code=$matches[1]', 'top' );
	}

	public static function add_query_var( $vars ) {
		$vars[] = 'gsr_code';
		return $vars;
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * On /go/{code}: look up the code, log the click, redirect to the
	 * partner's real referral URL. Unknown codes fall through untouched
	 * (WordPress will 404 normally, no click is logged).
	 */
	public static function handle_redirect() {
		$code = get_query_var( 'gsr_code' );
		if ( empty( $code ) ) {
			return;
		}

		global $wpdb;
		$codes_table    = GSR_DB::table( 'codes' );
		$partners_table = GSR_DB::table( 'partners' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT c.id AS code_id, c.code, c.partner_id, c.active, p.destination_url
				 FROM {$codes_table} c
				 LEFT JOIN {$partners_table} p ON p.id = c.partner_id
				 WHERE c.code = %s",
				$code
			)
		);

		if ( ! $row || ! $row->active ) {
			return; // Unknown or disabled code: let WP 404 normally.
		}

		// Last-touch attribution: overwrite any existing cookie unconditionally,
		// so whichever code was clicked most recently is the one that counts,
		// for up to COOKIE_DAYS. Simple overwrite is what makes this last-touch
		// rather than first-touch — no extra logic needed.
		setcookie(
			self::COOKIE_NAME,
			$row->code,
			array(
				'expires'  => time() + self::COOKIE_DAYS * DAY_IN_SECONDS,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		// Log the click regardless of whether a destination URL is set yet,
		// so early testing/traffic is still captured.
		$wpdb->insert(
			GSR_DB::table( 'clicks' ),
			array(
				'code_id'    => $row->code_id,
				'code'       => $row->code,
				'partner_id' => $row->partner_id,
				'clicked_at' => current_time( 'mysql' ),
				'ip_address' => self::get_client_ip(),
				'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '',
			)
		);

		if ( ! empty( $row->destination_url ) ) {
			wp_redirect( esc_url_raw( $row->destination_url ), 302 );
			exit;
		}

		// No destination configured yet (referral link not approved):
		// send the visitor somewhere sane instead of a dead page.
		wp_redirect( home_url( '/' ), 302 );
		exit;
	}

	private static function get_client_ip() {
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				$ip = trim( explode( ',', $ip )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '';
	}
}
