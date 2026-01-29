<?php
declare(strict_types=1);

namespace Bressol\Modules\B2B;

use Bressol\Modules\B2B\Services\Capabilities;
use Bressol\Modules\B2B\Services\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class Installer
{
    public const VERSION = '0.1.5';
    private const VERSION_OPTION = 'bressol_b2b_schema_version';

    public static function maybe_upgrade(): void
    {
        $installed = (string) get_option(self::VERSION_OPTION, '');
        if ($installed === '' || version_compare($installed, self::VERSION, '<')) {
            (new self())->install();
        }
    }

    public function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();
        $leadsTable = $wpdb->prefix . 'bressol_b2b_leads';
        $eventsTable = $wpdb->prefix . 'bressol_b2b_lead_events';
        $tasksTable = $wpdb->prefix . 'bressol_b2b_tasks';

        $tables = [];

        $tables[] = "CREATE TABLE {$leadsTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            email VARCHAR(190) NOT NULL,
            email_lower VARCHAR(190) NOT NULL,
            company_name VARCHAR(190) NULL,
            contact_name VARCHAR(190) NULL,
            city VARCHAR(120) NULL,
            phone VARCHAR(50) NULL,
            business_type VARCHAR(30) NULL,
            tier VARCHAR(30) NOT NULL,
            status VARCHAR(20) NOT NULL,
            contact_basis VARCHAR(60) NOT NULL,
            source VARCHAR(50) NOT NULL,
            source_ref_event_id BIGINT UNSIGNED NULL,
            interests_json LONGTEXT NULL,
            owner_user_id BIGINT UNSIGNED NOT NULL,
            lead_score INT NOT NULL DEFAULT 0,
            last_activity_at DATETIME NULL,
            consent_token VARCHAR(64) NOT NULL,
            consent_token_created_at DATETIME NOT NULL,
            consent_token_expires_at DATETIME NOT NULL,
            consented_at DATETIME NULL,
            reminder_sent_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY email_lower (email_lower),
            UNIQUE KEY consent_token (consent_token),
            KEY status (status),
            KEY owner_user_id (owner_user_id),
            KEY source_ref_event_id (source_ref_event_id)
        ) {$charsetCollate};";

        $tables[] = "CREATE TABLE {$eventsTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(60) NOT NULL,
            created_at DATETIME NOT NULL,
            context_json LONGTEXT NULL,
            PRIMARY KEY (id),
            KEY lead_id (lead_id),
            KEY type (type),
            KEY created_at (created_at)
        ) {$charsetCollate};";

        $tables[] = "CREATE TABLE {$tasksTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id BIGINT UNSIGNED NOT NULL,
            due_at DATETIME NOT NULL,
            type VARCHAR(60) NOT NULL,
            status VARCHAR(10) NOT NULL,
            assigned_user_id BIGINT UNSIGNED NOT NULL,
            note VARCHAR(200) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY lead_id (lead_id),
            KEY status (status),
            KEY due_at (due_at),
            KEY assigned_user_id (assigned_user_id)
        ) {$charsetCollate};";

        foreach ($tables as $tableSql) {
            dbDelta($tableSql);
        }

        $this->ensure_unique_indexes($leadsTable);

        (new Settings())->ensure_defaults();
        (new Capabilities())->seed_admin_cap();

        update_option('bressol_b2b_flush_needed', '1', false);
        update_option(self::VERSION_OPTION, self::VERSION, false);
    }

    private function ensure_unique_indexes(string $leadsTable): void
    {
        global $wpdb;
        $targets = [
            'email_lower' => 'email_lower',
            'consent_token' => 'consent_token',
        ];
        foreach ($targets as $keyName => $column) {
            $index = $wpdb->get_var(
                $wpdb->prepare("SHOW INDEX FROM {$leadsTable} WHERE Key_name = %s", $keyName)
            );
            if ($index) {
                continue;
            }
            $wpdb->query("ALTER TABLE {$leadsTable} ADD UNIQUE KEY {$keyName} ({$column})");
        }
    }
}
