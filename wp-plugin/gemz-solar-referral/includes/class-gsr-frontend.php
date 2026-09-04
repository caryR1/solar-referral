<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSR_Frontend {

	public static function init() {
		add_shortcode( 'gsr_affiliate_signup', array( __CLASS__, 'render_signup' ) );
		add_shortcode( 'gsr_affiliate_dashboard', array( __CLASS__, 'render_dashboard' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'init', array( __CLASS__, 'maybe_create_pages' ) );
		add_action( 'admin_post_gsr_affiliate_signup', array( __CLASS__, 'handle_signup' ) );
		add_action( 'admin_post_nopriv_gsr_affiliate_signup', array( __CLASS__, 'handle_signup' ) );
		add_action( 'admin_post_gsr_affiliate_login', array( __CLASS__, 'handle_login' ) );
		add_action( 'admin_post_nopriv_gsr_affiliate_login', array( __CLASS__, 'handle_login' ) );
		add_action( 'admin_post_gsr_change_password', array( __CLASS__, 'handle_change_password' ) );
		add_action( 'admin_post_gsr_save_payment_info', array( __CLASS__, 'handle_save_payment_info' ) );
	}

	public static function enqueue_assets() {
		if ( is_singular() ) {
			global $post;
			if ( $post && ( has_shortcode( $post->post_content, 'gsr_affiliate_signup' ) || has_shortcode( $post->post_content, 'gsr_affiliate_dashboard' ) ) ) {
				wp_enqueue_style( 'gsr-frontend', plugins_url( 'assets/gsr-frontend.css', GSR_PLUGIN_FILE ), array(), GSR_VERSION );
			}
		}
	}

	/**
	 * Auto-create the signup and dashboard pages, once, similar to how
	 * WooCommerce creates its Cart/Checkout pages on first run.
	 */
	public static function maybe_create_pages() {
		if ( ! get_option( 'gsr_signup_page_id' ) ) {
			$id = wp_insert_post( array(
				'post_title'   => 'Become an Affiliate',
				'post_name'    => 'become-an-affiliate',
				'post_content' => '[gsr_affiliate_signup]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			) );
			if ( $id && ! is_wp_error( $id ) ) {
				update_option( 'gsr_signup_page_id', $id );
			}
		}
		if ( ! get_option( 'gsr_dashboard_page_id' ) ) {
			$id = wp_insert_post( array(
				'post_title'   => 'Affiliate Dashboard',
				'post_name'    => 'affiliate-dashboard',
				'post_content' => '[gsr_affiliate_dashboard]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			) );
			if ( $id && ! is_wp_error( $id ) ) {
				update_option( 'gsr_dashboard_page_id', $id );
			}
		}
	}

	private static function dashboard_url() {
		$id = get_option( 'gsr_dashboard_page_id' );
		return $id ? get_permalink( $id ) : home_url( '/affiliate-dashboard/' );
	}

	private static function signup_url() {
		$id = get_option( 'gsr_signup_page_id' );
		return $id ? get_permalink( $id ) : home_url( '/become-an-affiliate/' );
	}

	private static function get_active_partners() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT id, name FROM ' . GSR_DB::table( 'partners' ) . ' ORDER BY name ASC' );
	}

	private static function generate_unique_code( $name ) {
		global $wpdb;
		$table = GSR_DB::table( 'codes' );
		$base  = sanitize_title( $name );
		if ( '' === $base ) {
			$base = 'affiliate';
		}
		$code = $base;
		$i    = 0;
		while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE code = %s", $code ) ) ) {
			$i++;
			$code = $base . '-' . $i;
		}
		return $code;
	}

	/* ---------------------------------------------------------------- *
	 * SIGNUP
	 * ---------------------------------------------------------------- */

	public static function render_signup() {
		if ( is_user_logged_in() && GSR_Roles::is_affiliate() ) {
			return '<div class="gsr-notice">You already have an affiliate account. <a href="' . esc_url( self::dashboard_url() ) . '">Go to your dashboard &rarr;</a></div>';
		}

		ob_start();

		if ( isset( $_GET['gsr_signup'] ) && 'success' === $_GET['gsr_signup'] ) {
			echo '<div class="gsr-notice gsr-notice-success"><p>You\'re in! Your referral link is live now.</p><p><a href="' . esc_url( self::dashboard_url() ) . '">Go to your dashboard &rarr;</a></p></div>';
			return ob_get_clean();
		}

		$error = isset( $_GET['gsr_error'] ) ? sanitize_text_field( wp_unslash( $_GET['gsr_error'] ) ) : '';
		if ( $error ) {
			echo '<div class="gsr-notice gsr-notice-error"><p>' . esc_html( $error ) . '</p></div>';
		}

		$partners = self::get_active_partners();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gsr-form">
			<?php wp_nonce_field( 'gsr_affiliate_signup' ); ?>
			<input type="hidden" name="action" value="gsr_affiliate_signup">
			<p style="position:absolute;left:-9999px;" aria-hidden="true">
				<label>Leave this field empty<input type="text" name="gsr_hp" tabindex="-1" autocomplete="off"></label>
			</p>

			<p>
				<label for="gsr_name">Your name</label><br>
				<input type="text" id="gsr_name" name="name" required class="gsr-input">
			</p>
			<p>
				<label for="gsr_email">Email</label><br>
				<input type="email" id="gsr_email" name="email" required class="gsr-input">
			</p>
			<p>
				<label for="gsr_partner">Which solar partner do you want to promote?</label><br>
				<select id="gsr_partner" name="partner_id" required class="gsr-input">
					<option value="">-- choose one --</option>
					<?php foreach ( $partners as $p ) : ?>
						<option value="<?php echo esc_attr( $p->id ); ?>"><?php echo esc_html( $p->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label for="gsr_password">Choose a password</label><br>
				<input type="password" id="gsr_password" name="password" required minlength="8" class="gsr-input">
			</p>
			<p>
				<label for="gsr_password2">Confirm password</label><br>
				<input type="password" id="gsr_password2" name="password2" required minlength="8" class="gsr-input">
			</p>
			<p>
				<button type="submit" class="gsr-button">Sign up</button>
			</p>
			<p class="gsr-fineprint">Already have an account? <a href="<?php echo esc_url( self::dashboard_url() ); ?>">Log in on your dashboard</a>.</p>
		</form>
		<?php
		return ob_get_clean();
	}

	public static function handle_signup() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'gsr_affiliate_signup' ) ) {
			wp_die( 'Security check failed. Please go back and try again.' );
		}

		$redirect_back = wp_get_referer() ? wp_get_referer() : self::signup_url();

		// Honeypot: if filled, silently pretend success without creating anything.
		if ( ! empty( $_POST['gsr_hp'] ) ) {
			wp_safe_redirect( add_query_arg( 'gsr_signup', 'success', $redirect_back ) );
			exit;
		}

		$name       = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$partner_id = isset( $_POST['partner_id'] ) ? absint( $_POST['partner_id'] ) : 0;
		$password   = isset( $_POST['password'] ) ? (string) $_POST['password'] : '';
		$password2  = isset( $_POST['password2'] ) ? (string) $_POST['password2'] : '';

		$fail = function( $msg ) use ( $redirect_back ) {
			wp_safe_redirect( add_query_arg( 'gsr_error', rawurlencode( $msg ), $redirect_back ) );
			exit;
		};

		if ( '' === $name || ! is_email( $email ) || ! $partner_id || strlen( $password ) < 8 ) {
			$fail( 'Please fill in every field. Passwords need to be at least 8 characters.' );
		}
		if ( $password !== $password2 ) {
			$fail( 'Passwords do not match.' );
		}
		if ( email_exists( $email ) ) {
			$fail( 'That email is already registered. Try logging in instead.' );
		}

		$username = self::generate_unique_username( $email );

		$user_id = wp_insert_user( array(
			'user_login'   => $username,
			'user_email'   => $email,
			'user_pass'    => $password,
			'display_name' => $name,
			'first_name'   => $name,
			'role'         => GSR_Roles::ROLE,
		) );

		if ( is_wp_error( $user_id ) ) {
			$fail( 'Could not create account: ' . $user_id->get_error_message() );
		}

		update_user_meta( $user_id, 'gsr_status', 'active' );

		$code = self::generate_unique_code( $name );

		global $wpdb;
		$partners_table = GSR_DB::table( 'partners' );
		$partner        = $wpdb->get_row( $wpdb->prepare( "SELECT default_cut_type, default_cut_value FROM {$partners_table} WHERE id = %d", $partner_id ) );
		$cut_type       = $partner && 'flat' === $partner->default_cut_type ? 'flat' : 'percent';
		$cut_value      = $partner ? (float) $partner->default_cut_value : 0;

		$wpdb->insert(
			GSR_DB::table( 'codes' ),
			array(
				'code'               => $code,
				'sub_affiliate_name' => $name,
				'partner_id'         => $partner_id,
				'wp_user_id'         => $user_id,
				'status'             => 'active',
				'cut_type'           => $cut_type,
				'cut_value'          => $cut_value,
				'active'             => 1,
				'notes'              => 'Self-signup, live immediately at the partner\'s default cut rate.',
				'created_at'         => current_time( 'mysql' ),
			)
		);

		// Log them in so their dashboard is ready immediately.
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );

		// Notify the site admin.
		wp_mail(
			get_option( 'admin_email' ),
			'New affiliate joined: ' . $name,
			"A new affiliate signed up and is live immediately.\n\nName: {$name}\nEmail: {$email}\nCode: {$code}\nCut rate applied: " . ( 'flat' === $cut_type ? '$' . number_format( $cut_value, 2 ) . ' flat' : $cut_value . '%' ) . "\n\nYou can suspend them or adjust their rate anytime in wp-admin under Solar Referral > Affiliates."
		);

		wp_safe_redirect( add_query_arg( 'gsr_signup', 'success', self::signup_url() ) );
		exit;
	}

	private static function generate_unique_username( $email ) {
		$base = sanitize_user( current( explode( '@', $email ) ), true );
		if ( '' === $base ) {
			$base = 'affiliate';
		}
		$username = $base;
		$i        = 0;
		while ( username_exists( $username ) ) {
			$i++;
			$username = $base . $i;
		}
		return $username;
	}

	/* ---------------------------------------------------------------- *
	 * LOGIN
	 * ---------------------------------------------------------------- */

	public static function handle_login() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'gsr_affiliate_login' ) ) {
			wp_die( 'Security check failed. Please go back and try again.' );
		}

		$creds = array(
			'user_login'    => isset( $_POST['gsr_username'] ) ? sanitize_text_field( wp_unslash( $_POST['gsr_username'] ) ) : '',
			'user_password' => isset( $_POST['gsr_login_password'] ) ? (string) $_POST['gsr_login_password'] : '',
			'remember'      => true,
		);

		$user = wp_signon( $creds, is_ssl() );

		if ( is_wp_error( $user ) ) {
			wp_safe_redirect( add_query_arg( 'gsr_error', rawurlencode( 'Login failed: check your email/username and password.' ), self::dashboard_url() ) );
			exit;
		}

		wp_safe_redirect( self::dashboard_url() );
		exit;
	}

	/* ---------------------------------------------------------------- *
	 * DASHBOARD
	 * ---------------------------------------------------------------- */

	public static function render_dashboard() {
		if ( ! is_user_logged_in() ) {
			return self::render_login_form();
		}

		if ( ! GSR_Roles::is_affiliate() ) {
			return '<div class="gsr-notice">This dashboard is for affiliates only. <a href="' . esc_url( wp_logout_url( self::signup_url() ) ) . '">Log out</a> and sign up as an affiliate, or contact us if you think this is a mistake.</div>';
		}

		$user_id = get_current_user_id();
		$user    = wp_get_current_user();
		$status  = get_user_meta( $user_id, 'gsr_status', true ) ?: 'active';

		ob_start();

		if ( isset( $_GET['gsr_notice'] ) ) {
			$notices = array(
				'password_updated' => 'Password updated.',
				'payment_updated'  => 'Payment information saved.',
			);
			$key = sanitize_text_field( wp_unslash( $_GET['gsr_notice'] ) );
			if ( isset( $notices[ $key ] ) ) {
				echo '<div class="gsr-notice gsr-notice-success"><p>' . esc_html( $notices[ $key ] ) . '</p></div>';
			}
		}
		if ( isset( $_GET['gsr_error'] ) ) {
			echo '<div class="gsr-notice gsr-notice-error"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['gsr_error'] ) ) ) . '</p></div>';
		}

		echo '<div class="gsr-dashboard">';
		echo '<p>Welcome back, ' . esc_html( $user->display_name ) . '. <a href="' . esc_url( wp_logout_url( self::dashboard_url() ) ) . '">Log out</a></p>';

		if ( 'suspended' === $status ) {
			echo '<div class="gsr-notice gsr-notice-error">Your affiliate account is currently suspended and your link is inactive. Contact us if you have questions.</div>';
		}

		self::render_stats_section( $user_id );
		self::render_password_section();
		self::render_payment_section( $user_id );

		echo '</div>';

		return ob_get_clean();
	}

	private static function render_login_form() {
		ob_start();
		$error = isset( $_GET['gsr_error'] ) ? sanitize_text_field( wp_unslash( $_GET['gsr_error'] ) ) : '';
		if ( $error ) {
			echo '<div class="gsr-notice gsr-notice-error"><p>' . esc_html( $error ) . '</p></div>';
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gsr-form">
			<?php wp_nonce_field( 'gsr_affiliate_login' ); ?>
			<input type="hidden" name="action" value="gsr_affiliate_login">
			<p>
				<label for="gsr_username">Email or username</label><br>
				<input type="text" id="gsr_username" name="gsr_username" required class="gsr-input">
			</p>
			<p>
				<label for="gsr_login_password">Password</label><br>
				<input type="password" id="gsr_login_password" name="gsr_login_password" required class="gsr-input">
			</p>
			<p><button type="submit" class="gsr-button">Log in</button></p>
			<p class="gsr-fineprint">
				Not an affiliate yet? <a href="<?php echo esc_url( self::signup_url() ); ?>">Sign up here</a>.
				Forgot your password? <a href="<?php echo esc_url( wp_lostpassword_url( self::dashboard_url() ) ); ?>">Reset it</a>.
			</p>
		</form>
		<?php
		return ob_get_clean();
	}

	private static function render_stats_section( $user_id ) {
		global $wpdb;
		$codes_table    = GSR_DB::table( 'codes' );
		$partners_table = GSR_DB::table( 'partners' );
		$clicks_table   = GSR_DB::table( 'clicks' );
		$payouts_table  = GSR_DB::table( 'payouts' );

		$codes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*, p.name AS partner_name FROM {$codes_table} c
				 LEFT JOIN {$partners_table} p ON p.id = c.partner_id
				 WHERE c.wp_user_id = %d ORDER BY c.created_at ASC",
				$user_id
			)
		);

		echo '<h2>Your links</h2>';
		if ( ! $codes ) {
			echo '<p>No referral links yet.</p>';
			return;
		}

		foreach ( $codes as $c ) {
			$link        = home_url( '/go/' . rawurlencode( $c->code ) . '/' );
			$click_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$clicks_table} WHERE code_id = %d", $c->id ) );
			$totals      = $wpdb->get_row( $wpdb->prepare( "SELECT COALESCE(SUM(subaffiliate_cut),0) AS total_owed, COUNT(*) AS payout_count FROM {$payouts_table} WHERE code_id = %d", $c->id ) );

			echo '<div class="gsr-code-card">';
			echo '<p><strong>' . esc_html( $c->partner_name ) . '</strong> &mdash; status: ' . esc_html( $c->status ) . '</p>';
			echo '<p>Your link: <code>' . esc_html( $link ) . '</code></p>';
			echo '<div class="gsr-stat-row">';
			echo '<div class="gsr-stat"><span class="gsr-stat-num">' . esc_html( $click_count ) . '</span><span class="gsr-stat-label">Clicks</span></div>';
			echo '<div class="gsr-stat"><span class="gsr-stat-num">' . esc_html( $totals->payout_count ) . '</span><span class="gsr-stat-label">Paid sales</span></div>';
			echo '<div class="gsr-stat"><span class="gsr-stat-num">$' . esc_html( number_format( (float) $totals->total_owed, 2 ) ) . '</span><span class="gsr-stat-label">Total earned</span></div>';
			echo '</div>';
			echo '</div>';
		}
	}

	private static function render_password_section() {
		?>
		<h2>Change password</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gsr-form">
			<?php wp_nonce_field( 'gsr_change_password' ); ?>
			<input type="hidden" name="action" value="gsr_change_password">
			<p>
				<label for="gsr_current_password">Current password</label><br>
				<input type="password" id="gsr_current_password" name="current_password" required class="gsr-input">
			</p>
			<p>
				<label for="gsr_new_password">New password</label><br>
				<input type="password" id="gsr_new_password" name="new_password" required minlength="8" class="gsr-input">
			</p>
			<p>
				<label for="gsr_new_password2">Confirm new password</label><br>
				<input type="password" id="gsr_new_password2" name="new_password2" required minlength="8" class="gsr-input">
			</p>
			<p><button type="submit" class="gsr-button">Update password</button></p>
		</form>
		<?php
	}

	public static function handle_change_password() {
		if ( ! is_user_logged_in() ) {
			wp_die( 'Please log in first.' );
		}
		check_admin_referer( 'gsr_change_password' );

		$user_id   = get_current_user_id();
		$user      = wp_get_current_user();
		$current   = isset( $_POST['current_password'] ) ? (string) $_POST['current_password'] : '';
		$new_pass  = isset( $_POST['new_password'] ) ? (string) $_POST['new_password'] : '';
		$new_pass2 = isset( $_POST['new_password2'] ) ? (string) $_POST['new_password2'] : '';

		$fail = function( $msg ) {
			wp_safe_redirect( add_query_arg( 'gsr_error', rawurlencode( $msg ), self::dashboard_url() ) );
			exit;
		};

		if ( ! wp_check_password( $current, $user->user_pass, $user_id ) ) {
			$fail( 'Current password is incorrect.' );
		}
		if ( strlen( $new_pass ) < 8 || $new_pass !== $new_pass2 ) {
			$fail( 'New password must be at least 8 characters and match its confirmation.' );
		}

		wp_set_password( $new_pass, $user_id );

		// wp_set_password() invalidates the current session, so log back in.
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );

		wp_safe_redirect( add_query_arg( 'gsr_notice', 'password_updated', self::dashboard_url() ) );
		exit;
	}

	private static function render_payment_section( $user_id ) {
		$existing = get_user_meta( $user_id, 'gsr_payment_details', true );
		?>
		<h2>Payment information</h2>
		<p class="gsr-fineprint">Tell us how you'd like to be paid &mdash; PayPal, Venmo, Zelle, bank transfer details, whatever's easiest for you.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gsr-form">
			<?php wp_nonce_field( 'gsr_save_payment_info' ); ?>
			<input type="hidden" name="action" value="gsr_save_payment_info">
			<p>
				<textarea name="payment_details" rows="4" class="gsr-input" placeholder="e.g. PayPal: name@example.com, or bank transfer details"><?php echo esc_textarea( $existing ); ?></textarea>
			</p>
			<p><button type="submit" class="gsr-button">Save</button></p>
		</form>
		<?php
	}

	public static function handle_save_payment_info() {
		if ( ! is_user_logged_in() ) {
			wp_die( 'Please log in first.' );
		}
		check_admin_referer( 'gsr_save_payment_info' );

		$user_id = get_current_user_id();
		$details = isset( $_POST['payment_details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['payment_details'] ) ) : '';

		update_user_meta( $user_id, 'gsr_payment_details', $details );

		wp_safe_redirect( add_query_arg( 'gsr_notice', 'payment_updated', self::dashboard_url() ) );
		exit;
	}
}
