<?php
declare(strict_types=1);

namespace Bressol\Modules\B2B\Services;

use Bressol\Modules\Esp\Services\QueueService;

if (!defined('ABSPATH')) {
    exit;
}

final class EspService
{
    public function enqueue_b2b_catalog_email(array $lead, string $token): bool
    {
        $leadId = (int) ($lead['id'] ?? 0);
        $email = (string) ($lead['email'] ?? '');
        if ($leadId <= 0 || $email === '' || !is_email($email) || $token === '') {
            return false;
        }

        global $wpdb;
        $listsTable = $wpdb->prefix . 'bressol_esp_lists';
        $membersTable = $wpdb->prefix . 'bressol_esp_list_members';
        $campaignsTable = $wpdb->prefix . 'bressol_esp_campaigns';

        $now = current_time('mysql');
        $listId = $this->ensure_list_member($leadId, $email, $now, $listsTable, $membersTable);
        if ($listId <= 0) {
            return false;
        }
        $subject = 'Bressol B2B — catalogus & prijslijst';
        $body = $this->build_email_body($token);

        $wpdb->insert(
            $campaignsTable,
            [
                'name' => 'B2B Catalog ' . $leadId . ' ' . $now,
                'subject' => $subject,
                'html_body' => $body,
                'list_id' => $listId,
                'status' => 'scheduled',
                'scheduled_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );

        $campaignId = (int) $wpdb->insert_id;
        if ($campaignId <= 0) {
            return false;
        }

        (new QueueService())->enqueue_campaign($campaignId);
        return true;
    }

    public function enqueue_b2b_reminder_email(array $lead, string $token): bool
    {
        $leadId = (int) ($lead['id'] ?? 0);
        $email = (string) ($lead['email'] ?? '');
        if ($leadId <= 0 || $email === '' || !is_email($email) || $token === '') {
            return false;
        }

        global $wpdb;
        $listsTable = $wpdb->prefix . 'bressol_esp_lists';
        $membersTable = $wpdb->prefix . 'bressol_esp_list_members';
        $campaignsTable = $wpdb->prefix . 'bressol_esp_campaigns';

        $now = current_time('mysql');
        $listId = $this->ensure_list_member($leadId, $email, $now, $listsTable, $membersTable);
        if ($listId <= 0) {
            return false;
        }

        $subject = 'Bressol B2B: herinnering catalogus';
        $body = $this->build_email_body_reminder($token);

        $wpdb->insert(
            $campaignsTable,
            [
                'name' => 'B2B Reminder ' . $leadId . ' ' . $now,
                'subject' => $subject,
                'html_body' => $body,
                'list_id' => $listId,
                'status' => 'scheduled',
                'scheduled_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );

        $campaignId = (int) $wpdb->insert_id;
        if ($campaignId <= 0) {
            return false;
        }

        (new QueueService())->enqueue_campaign($campaignId);
        return true;
    }

    public function enqueue_b2b_profile_confirmation_email(array $lead): bool
    {
        $leadId = (int) ($lead['id'] ?? 0);
        $email = (string) ($lead['email'] ?? '');
        $token = (string) ($lead['consent_token'] ?? '');
        if ($leadId <= 0 || $email === '' || !is_email($email) || $token === '') {
            return false;
        }

        global $wpdb;
        $listsTable = $wpdb->prefix . 'bressol_esp_lists';
        $membersTable = $wpdb->prefix . 'bressol_esp_list_members';
        $campaignsTable = $wpdb->prefix . 'bressol_esp_campaigns';

        $now = current_time('mysql');
        $listId = $this->ensure_list_member($leadId, $email, $now, $listsTable, $membersTable);
        if ($listId <= 0) {
            return false;
        }

        $subject = 'Bressol B2B: bedankt voor je gegevens';
        $body = $this->build_email_body_profile_confirmation($token);

        $wpdb->insert(
            $campaignsTable,
            [
                'name' => 'B2B Profile ' . $leadId . ' ' . $now,
                'subject' => $subject,
                'html_body' => $body,
                'list_id' => $listId,
                'status' => 'scheduled',
                'scheduled_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );

        $campaignId = (int) $wpdb->insert_id;
        if ($campaignId <= 0) {
            return false;
        }

        (new QueueService())->enqueue_campaign($campaignId);
        return true;
    }


    private function build_email_body(string $token): string
    {
        $utm = [
            'utm_source' => 'b2b',
            'utm_medium' => 'email',
            'utm_campaign' => 'catalog_2026',
        ];
        $catalogUrl = $this->build_public_url('catalog', $token, $utm);
        $pricelistUrl = $this->build_public_url('pricelist', $token, $utm);

        $html = '';
        $html .= '<p>Bedankt voor je interesse in Bressol B2B.</p>';
        $html .= '<p>Bekijk direct:</p>';
        $html .= '<ul>';
        $html .= '<li><a href="' . esc_url($catalogUrl) . '">Catalogus bekijken</a></li>';
        $html .= '<li><a href="' . esc_url($pricelistUrl) . '">Prijslijst bekijken</a></li>';
        $html .= '</ul>';
        $html .= '<p>De prijslijst kan om bedrijfsnaam en stad vragen zodat we je beter kunnen helpen.</p>';
        $html .= '<p>Met vriendelijke groet,<br/>Bressol</p>';

        return $html;
    }

    private function build_email_body_reminder(string $token): string
    {
        $catalogUrl = $this->build_public_url('catalog', $token, []);
        $pricelistUrl = $this->build_public_url('pricelist', $token, []);

        $html = '';
        $html .= '<p>We wilden je even herinneren aan onze B2B catalogus en prijslijst.</p>';
        $html .= '<ul>';
        $html .= '<li><a href="' . esc_url($catalogUrl) . '">Catalogus downloaden</a></li>';
        $html .= '<li><a href="' . esc_url($pricelistUrl) . '">Prijslijst downloaden</a></li>';
        $html .= '</ul>';
        $html .= '<p>Met vriendelijke groet,<br/>Bressol</p>';

        return $html;
    }

    private function build_email_body_profile_confirmation(string $token): string
    {
        $catalogUrl = $this->build_public_url('catalog', $token, []);
        $pricelistUrl = $this->build_public_url('pricelist', $token, []);

        $html = '';
        $html .= '<p>Bedankt voor het aanvullen van je bedrijfsgegevens.</p>';
        $html .= '<p>Hier zijn opnieuw de links naar de catalogus en prijslijst:</p>';
        $html .= '<ul>';
        $html .= '<li><a href="' . esc_url($catalogUrl) . '">Catalogus downloaden</a></li>';
        $html .= '<li><a href="' . esc_url($pricelistUrl) . '">Prijslijst downloaden</a></li>';
        $html .= '</ul>';
        $html .= '<p>Met vriendelijke groet,<br/>Bressol</p>';

        return $html;
    }


    private function ensure_list_member(
        int $leadId,
        string $email,
        string $now,
        string $listsTable,
        string $membersTable
    ): int {
        global $wpdb;
        $tables = [$listsTable, $membersTable, $wpdb->prefix . 'bressol_esp_campaigns'];
        foreach ($tables as $table) {
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($exists !== $table) {
                return 0;
            }
        }

        $listName = 'B2B Lead ' . $leadId;
        $listId = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$listsTable} WHERE name = %s LIMIT 1", $listName)
        );
        if ($listId <= 0) {
            $wpdb->insert(
                $listsTable,
                [
                    'name' => $listName,
                    'created_at' => $now,
                ],
                ['%s', '%s']
            );
            $listId = (int) $wpdb->insert_id;
        }

        if ($listId <= 0) {
            return 0;
        }

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$membersTable} (list_id, email, customer_id, status, created_at, updated_at)
                 VALUES (%d, %s, %d, %s, %s, %s)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = VALUES(updated_at)",
                $listId,
                $email,
                null,
                'subscribed',
                $now,
                $now
            )
        );

        return $listId;
    }

    /** @param array<string, string> $utm */
    private function build_public_url(string $page, string $token, array $utm): string
    {
        if ($this->rewrites_ok()) {
            $base = home_url('/b2b/' . $page);
        } else {
            $base = add_query_arg(['b2b_doc' => $page], home_url('/'));
        }
        $args = array_merge(['token' => $token], $utm);
        return add_query_arg($args, $base);
    }

    private function rewrites_ok(): bool
    {
        $rules = get_option('rewrite_rules', []);
        return is_array($rules)
            && array_key_exists('^b2b/catalog/?$', $rules)
            && array_key_exists('^b2b/pricelist/?$', $rules);
    }
}
