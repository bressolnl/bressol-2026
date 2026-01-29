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

        $tables = [$listsTable, $membersTable, $campaignsTable];
        foreach ($tables as $table) {
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($exists !== $table) {
                return false;
            }
        }

        $now = current_time('mysql');
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
            return false;
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

        $subject = 'Bressol B2B: catalogus & prijslijst';
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

    private function build_email_body(string $token): string
    {
        $catalogUrl = add_query_arg(['token' => $token], home_url('/b2b/catalog'));
        $pricelistUrl = add_query_arg(['token' => $token], home_url('/b2b/pricelist'));

        $html = '';
        $html .= '<p>Bedankt voor je toestemming om onze B2B-informatie te ontvangen.</p>';
        $html .= '<p>Hier vind je de catalogus en prijslijst:</p>';
        $html .= '<ul>';
        $html .= '<li><a href="' . esc_url($catalogUrl) . '">Catalogus downloaden</a></li>';
        $html .= '<li><a href="' . esc_url($pricelistUrl) . '">Prijslijst downloaden</a></li>';
        $html .= '</ul>';
        $html .= '<p>Met vriendelijke groet,<br/>Bressol</p>';

        return $html;
    }
}
