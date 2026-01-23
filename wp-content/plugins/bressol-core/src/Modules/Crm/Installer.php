<?php
declare(strict_types=1);

namespace Bressol\Modules\Crm;

use Bressol\Modules\Crm\Services\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class Installer
{
    public const VERSION = '1.2.0';

    public static function maybe_upgrade(): void
    {
        $installed = (string) get_option('bressol_crm_version', '');
        if ($installed === '' || version_compare($installed, self::VERSION, '<')) {
            (new self())->install();
        }
    }

    public function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'bressol_crm_';

        $tables = [];

        $tables[] = "CREATE TABLE {$prefix}customers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            wc_customer_id BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NULL,
            email VARCHAR(190) NOT NULL,
            first_name VARCHAR(190) NULL,
            last_name VARCHAR(190) NULL,
            phone VARCHAR(50) NULL,
            company VARCHAR(190) NULL,
            billing_address_1 VARCHAR(190) NULL,
            billing_address_2 VARCHAR(190) NULL,
            billing_city VARCHAR(190) NULL,
            billing_state VARCHAR(190) NULL,
            billing_postcode VARCHAR(30) NULL,
            billing_country VARCHAR(10) NULL,
            customer_type VARCHAR(10) NOT NULL,
            business_type VARCHAR(190) NULL,
            total_spent DECIMAL(12,2) NOT NULL DEFAULT 0,
            order_count INT UNSIGNED NOT NULL DEFAULT 0,
            last_order_at DATETIME NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            source VARCHAR(20) NOT NULL DEFAULT 'order',
            can_be_profiled TINYINT(1) NOT NULL DEFAULT 1,
            can_receive_marketing TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY email_type (email, customer_type),
            KEY wc_customer_id (wc_customer_id),
            KEY user_id (user_id)
        ) {$charsetCollate};";

        $tables[] = "CREATE TABLE {$prefix}customer_meta (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT UNSIGNED NOT NULL,
            meta_key VARCHAR(190) NOT NULL,
            meta_value LONGTEXT NULL,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY meta_key (meta_key)
        ) {$charsetCollate};";

        $tables[] = "CREATE TABLE {$prefix}points_ledger (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT UNSIGNED NOT NULL,
            source_type VARCHAR(30) NOT NULL,
            source_id BIGINT UNSIGNED NULL,
            points INT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            earned_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            expired_at DATETIME NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY source_unique (customer_id, source_type, source_id),
            KEY customer_id (customer_id),
            KEY expires_at (expires_at),
            KEY status (status)
        ) {$charsetCollate};";

        $tables[] = "CREATE TABLE {$prefix}points_redemptions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT UNSIGNED NOT NULL,
            redemption_type VARCHAR(30) NOT NULL,
            points_used INT NOT NULL,
            reference VARCHAR(190) NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY redemption_type (redemption_type)
        ) {$charsetCollate};";

        $tables[] = "CREATE TABLE {$prefix}tags (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            slug VARCHAR(190) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) {$charsetCollate};";

        $tables[] = "CREATE TABLE {$prefix}customer_tags (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT UNSIGNED NOT NULL,
            tag_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY tag_id (tag_id)
        ) {$charsetCollate};";

        $tables[] = "CREATE TABLE {$prefix}order_sync (
            order_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            order_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            processed_at DATETIME NOT NULL,
            PRIMARY KEY (order_id),
            KEY customer_id (customer_id)
        ) {$charsetCollate};";

        $tables[] = "CREATE TABLE {$prefix}audit_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(50) NOT NULL,
            entity_id BIGINT UNSIGNED NULL,
            actor_id BIGINT UNSIGNED NULL,
            context LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY entity_lookup (entity_type, entity_id),
            KEY action (action)
        ) {$charsetCollate};";

        foreach ($tables as $tableSql) {
            dbDelta($tableSql);
        }

        $settings = new Settings();
        $settings->ensure_defaults();

        // Manual test (activation): deactivate/activate plugin and verify table exists via
        // wp db query "SHOW TABLES LIKE '%bressol_crm_order_sync%'".
        // Manual test (upgrade): set bressol_crm_version to 1.1.0 and reload admin,
        // then confirm order_sync table exists and version updated to 1.2.0.
        update_option('bressol_crm_version', self::VERSION);
    }
}
