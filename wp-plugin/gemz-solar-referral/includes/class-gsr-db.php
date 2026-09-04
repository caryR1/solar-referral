<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSR_DB {

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'gsr_' . $name;
	}

	public static function activate() {
		self::create_tables();
		GSR_Roles::add_role();
		update_option( 'gsr_db_version', GSR_DB_VERSION );

		// Rewrite rule needs to exist before we flush.
		GSR_Redirect::add_rewrite_rule();
		flush_rewrite_rules();
	}

	public static function maybe_upgrade() {
		if ( get_option( 'gsr_db_version' ) !== GSR_DB_VERSION ) {
			self::create_tables();
			GSR_Roles::add_role();
			update_option( 'gsr_db_version', GSR_DB_VERSION );
		}
	}

	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();

		$partners = self::table( 'partners' );
		$codes    = self::table( 'codes' );
		$clicks   = self::table( 'clicks' );
		$payouts  = self::table( 'payouts' );

		$sql = "CREATE TABLE {$partners} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			slug VARCHAR(64) NOT NULL,
			name VARCHAR(191) NOT NULL,
			payout_type VARCHAR(20) NOT NULL DEFAULT 'flat',
			payout_amount DECIMAL(10,2) NULL,
			payout_percent DECIMAL(5,2) NULL,
			installments_json TEXT NULL,
			default_cut_type VARCHAR(20) NOT NULL DEFAULT 'percent',
			default_cut_value DECIMAL(10,2) NOT NULL DEFAULT 0,
			destination_url VARCHAR(500) NULL,
			notes TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) {$charset_collate};

		CREATE TABLE {$codes} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code VARCHAR(64) NOT NULL,
			sub_affiliate_name VARCHAR(191) NOT NULL,
			partner_id BIGINT UNSIGNED NOT NULL,
			wp_user_id BIGINT UNSIGNED NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			cut_type VARCHAR(20) NOT NULL DEFAULT 'percent',
			cut_value DECIMAL(10,2) NOT NULL DEFAULT 0,
			active TINYINT(1) NOT NULL DEFAULT 1,
			notes TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code),
			KEY partner_id (partner_id),
			KEY wp_user_id (wp_user_id)
		) {$charset_collate};

		CREATE TABLE {$clicks} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code_id BIGINT UNSIGNED NOT NULL,
			code VARCHAR(64) NOT NULL,
			partner_id BIGINT UNSIGNED NULL,
			clicked_at DATETIME NOT NULL,
			ip_address VARCHAR(45) NULL,
			user_agent VARCHAR(255) NULL,
			PRIMARY KEY  (id),
			KEY code_id (code_id),
			KEY clicked_at (clicked_at)
		) {$charset_collate};

		CREATE TABLE {$payouts} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code_id BIGINT UNSIGNED NOT NULL,
			code VARCHAR(64) NOT NULL,
			partner_id BIGINT UNSIGNED NOT NULL,
			sale_amount DECIMAL(10,2) NOT NULL,
			installment_label VARCHAR(191) NULL,
			gross_commission DECIMAL(10,2) NOT NULL,
			subaffiliate_cut DECIMAL(10,2) NOT NULL,
			net_to_cary DECIMAL(10,2) NOT NULL,
			entered_at DATETIME NOT NULL,
			notes TEXT NULL,
			PRIMARY KEY  (id),
			KEY code_id (code_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}
