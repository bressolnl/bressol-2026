<?php
declare(strict_types=1);

namespace Bressol\Modules\Esp;

use Bressol\Modules\Esp\Services\Capabilities;

if (!defined('ABSPATH')) {
    exit;
}

final class Installer
{
    public const VERSION = '1.0.0';

    public static function maybe_upgrade(): void
    {
        $installed = (string) get_option('bressol_esp_version', '');
        if ($installed === '' || version_compare($installed, self::VERSION, '<')) {
            self::install();
        }
    }

    public static function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'bressol_esp_';

        $tables = [];

        $tables[] = "CREATE TABLE {$prefix}campaigns (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            type VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL,
            template_id BIGINT UNSIGNED NULL,
            segment_id BIGINT UNSIGNED NULL,
            scheduled_at DATETIME NULL,
            template_snapshot LONGTEXT NULL,
            text_snapshot LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (id)
        ) {$charsetCollate};";

        $tables[] = "CREATE TABLE {$prefix}campaign_audience_snapshot (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            email VARCHAR(190) NOT NULL,
            snapshot_data LONGTEXT NULL,
            tracking_key VARCHAR(64) NULL,
            language VARCHAR(10) NOT NULL DEFAULT 'es',
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY email (email)
        ) {$charsetCollate};";

        $tables[] = "CREATE TABLE {$prefix}templates (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            html LONGTEXT NOT NULL,
            text_plain LONGTEXT NULL,
            language VARCHAR(10) NOT NULL DEFAULT 'es',
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) {$charsetCollate};";

        $tables[] = "CREATE TABLE {$prefix}segments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            rules_json LONGTEXT NOT NULL,
            builder_config LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) {$charsetCollate};";

        $tables[] = "CREATE TABLE {$prefix}manual_emails (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            email_type VARCHAR(50) NOT NULL,
            recipient_email VARCHAR(190) NOT NULL,
            subject VARCHAR(190) NOT NULL,
            html_body LONGTEXT NOT NULL,
            text_body LONGTEXT NULL,
            gift_block LONGTEXT NULL,
            metadata LONGTEXT NULL,
            tracking_key VARCHAR(64) NOT NULL,
            language VARCHAR(10) NOT NULL DEFAULT 'es',
            created_at DATETIME NOT NULL,
            sent_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY order_id (order_id)
        ) {$charsetCollate};";

        $tables[] = "CREATE TABLE {$prefix}events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id BIGINT UNSIGNED NULL,
            manual_email_id BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NULL,
            recipient_email VARCHAR(190) NULL,
            event_type VARCHAR(20) NOT NULL,
            url VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY manual_email_id (manual_email_id)
        ) {$charsetCollate};";

        $tables[] = "CREATE TABLE {$prefix}consents (
            user_id BIGINT UNSIGNED NULL,
            email VARCHAR(190) NOT NULL,
            status VARCHAR(20) NOT NULL,
            source VARCHAR(190) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (email)
        ) {$charsetCollate};";

        $tables[] = "CREATE TABLE {$prefix}audit_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            actor_user_id BIGINT UNSIGNED NULL,
            action VARCHAR(100) NOT NULL,
            target_type VARCHAR(50) NOT NULL,
            target_id BIGINT UNSIGNED NULL,
            metadata LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY target_type (target_type)
        ) {$charsetCollate};";

        $tables[] = "CREATE TABLE {$prefix}send_queue (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id BIGINT UNSIGNED NULL,
            manual_email_id BIGINT UNSIGNED NULL,
            recipient_email VARCHAR(190) NOT NULL,
            status VARCHAR(20) NOT NULL,
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            scheduled_at DATETIME NULL,
            sent_at DATETIME NULL,
            last_error LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY status (status)
        ) {$charsetCollate};";

        $tables[] = "CREATE TABLE {$prefix}send_queue_jobs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            queue_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL,
            run_at DATETIME NOT NULL,
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            last_error LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY queue_id (queue_id),
            KEY status (status)
        ) {$charsetCollate};";

        foreach ($tables as $tableSql) {
            dbDelta($tableSql);
        }

        // Importante: sembrar capability YA (por vuestro bootstrap en init)
        (new Capabilities())->seed_admin_cap();

        update_option('bressol_esp_version', self::VERSION, false);
    }
}
