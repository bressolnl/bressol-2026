<?php
declare(strict_types=1);

namespace Bressol\Modules\Crm;

if (!defined('ABSPATH')) {
    exit;
}

final class Installer
{
    public const VERSION = '1.1.0';

    public function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $customers = $wpdb->prefix . 'bressol_crm_customers';
        $customer_meta = $wpdb->prefix . 'bressol_crm_customer_meta';
        $points_ledger = $wpdb->prefix . 'bressol_crm_points_ledger';
        $points_redemptions = $wpdb->prefix . 'bressol_crm_points_redemptions';
        $tags = $wpdb->prefix . 'bressol_crm_tags';
        $customer_tags = $wpdb->prefix . 'bressol_crm_customer_tags';
        $audit_logs = $wpdb->prefix . 'bressol_crm_audit_logs';

        dbDelta(
            "CREATE TABLE {$customers} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                wc_customer_id BIGINT UNSIGNED NULL,
                user_id BIGINT UNSIGNED NULL,
                email VARCHAR(190) NOT NULL,
                customer_type VARCHAR(10) NOT NULL,
                business_type VARCHAR(190) NULL,
                total_spent DECIMAL(12,2) NOT NULL DEFAULT 0,
                order_count INT UNSIGNED NOT NULL DEFAULT 0,
                last_order_at DATETIME NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY email_type (email, customer_type),
                KEY wc_customer_id (wc_customer_id),
                KEY user_id (user_id)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$customer_meta} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                customer_id BIGINT UNSIGNED NOT NULL,
                meta_key VARCHAR(191) NOT NULL,
                meta_value LONGTEXT NULL,
                PRIMARY KEY (id),
                KEY customer_id (customer_id),
                KEY meta_key (meta_key)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$points_ledger} (
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
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$points_redemptions} (
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
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$tags} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(190) NOT NULL,
                slug VARCHAR(190) NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$customer_tags} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                customer_id BIGINT UNSIGNED NOT NULL,
                tag_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY customer_tag (customer_id, tag_id),
                KEY tag_id (tag_id)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$audit_logs} (
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
            ) {$charset_collate};"
        );

        update_option('bressol_crm_version', self::VERSION);

        if (!get_option('bressol_crm_settings')) {
            update_option('bressol_crm_settings', [
                'b2c_points_per_euro' => 1.0,
                'b2b_points_per_euro' => 1.5,
                'expiry_months' => 12,
            ]);
        }
    }

    public function maybe_upgrade(): void
    {
        $installed = get_option('bressol_crm_version');
        $installed_version = is_string($installed) ? $installed : '0.0.0';

        if (version_compare($installed_version, self::VERSION, '>=')) {
            return;
        }

        $this->install();
    }
}
