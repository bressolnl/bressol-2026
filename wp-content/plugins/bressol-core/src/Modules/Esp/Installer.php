<?php
declare(strict_types=1);

namespace Bressol\Modules\Esp;

use Bressol\Modules\Esp\Services\Capabilities;

if (!defined('ABSPATH')) {
    exit;
}

final class Installer
{
    public const VERSION = '2.0.0';

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
            subject VARCHAR(190) NOT NULL,
            html_body LONGTEXT NOT NULL,
            list_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL,
            scheduled_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY list_id (list_id)
        ) {$charsetCollate};";

        $tables[] = "CREATE TABLE {$prefix}lists (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY name (name)
        ) {$charsetCollate};";

        $tables[] = "CREATE TABLE {$prefix}list_members (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            list_id BIGINT UNSIGNED NOT NULL,
            email VARCHAR(190) NOT NULL,
            customer_id BIGINT UNSIGNED NULL,
            status VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY list_email (list_id, email),
            KEY list_id (list_id),
            KEY email (email),
            KEY list_id_status (list_id, status)
        ) {$charsetCollate};";

        $tables[] = "CREATE TABLE {$prefix}jobs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL,
            scheduled_at DATETIME NULL,
            started_at DATETIME NULL,
            finished_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY status (status)
        ) {$charsetCollate};";

        $tables[] = "CREATE TABLE {$prefix}queue_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_id BIGINT UNSIGNED NOT NULL,
            list_id BIGINT UNSIGNED NOT NULL,
            email VARCHAR(190) NOT NULL,
            customer_id BIGINT UNSIGNED NULL,
            status VARCHAR(20) NOT NULL,
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            last_error TEXT NULL,
            locked_at DATETIME NULL,
            sent_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY job_email (job_id, email),
            KEY job_id (job_id),
            KEY status (status),
            KEY job_status (job_id, status),
            KEY locked_at (locked_at),
            KEY sent_at (sent_at)
        ) {$charsetCollate};";

        $tables[] = "CREATE TABLE {$prefix}unsubscribes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(190) NOT NULL,
            list_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY list_email (list_id, email),
            KEY email (email),
            KEY list_id (list_id)
        ) {$charsetCollate};";

        $tables[] = "CREATE TABLE {$prefix}audit_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(50) NOT NULL,
            entity_id BIGINT UNSIGNED NULL,
            context LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY entity_type (entity_type),
            KEY action (action)
        ) {$charsetCollate};";

        foreach ($tables as $tableSql) {
            dbDelta($tableSql);
        }

        // Importante: sembrar capability YA (por vuestro bootstrap en init)
        (new Capabilities())->seed_admin_cap();

        update_option('bressol_esp_version', self::VERSION, false);
    }
}
