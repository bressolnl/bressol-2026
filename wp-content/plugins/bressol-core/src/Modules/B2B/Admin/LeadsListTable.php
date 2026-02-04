<?php
declare(strict_types=1);

namespace Bressol\Modules\B2B\Admin;

use Bressol\Modules\B2B\Repositories\LeadRepository;
use Bressol\Modules\B2B\Repositories\LeadEventsRepository;
use Bressol\Modules\B2B\Services\LeadService;
use Bressol\Modules\B2B\Support\Masking;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class LeadsListTable extends \WP_List_Table
{
    private LeadRepository $repository;
    private LeadEventsRepository $events;
    private LeadService $leadService;
    /** @var array<string, mixed> */
    private array $filters;
    private int $perPage = 20;

    /** @param array<string, mixed> $filters */
    public function __construct(LeadRepository $repository, array $filters)
    {
        parent::__construct([
            'singular' => 'b2b_lead',
            'plural' => 'b2b_leads',
            'ajax' => false,
        ]);
        $this->repository = $repository;
        $this->events = new LeadEventsRepository();
        $this->leadService = new LeadService($repository, $this->events);
        $this->filters = $filters;
    }

    /** @return array<string, string> */
    public function get_columns(): array
    {
        return [
            'id' => 'ID',
            'email_masked' => 'Email',
            'company_name' => 'Empresa',
            'temperature' => 'Temperatura',
            'status' => 'Estado',
            'owner' => 'Owner',
            'source' => 'Origen',
            'lead_score' => 'Score',
            'updated_at' => 'Actualizado',
            'actions' => 'Acciones',
        ];
    }

    public function prepare_items(): void
    {
        $page = $this->get_pagenum();
        $items = $this->repository->find_by_filters($this->filters, $this->perPage, $page);
        $total = $this->repository->count_by_filters($this->filters);

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = [];
        $this->_column_headers = [$columns, $hidden, $sortable];

        $leadIds = [];
        foreach ($items as $item) {
            if (is_array($item) && isset($item['id'])) {
                $leadIds[] = (int) $item['id'];
            }
        }
        $lastEvents = $this->events->get_last_event_types($leadIds);

        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $leadId = (int) ($item['id'] ?? 0);
            $lastEvent = $leadId > 0 && isset($lastEvents[$leadId]) ? $lastEvents[$leadId] : '';
            $temp = $this->leadService->compute_temperature(array_merge($item, ['last_event_type' => $lastEvent]));
            $normalized[] = [
                'id' => $leadId,
                'email_masked' => Masking::mask_email((string) ($item['email'] ?? '')),
                'company_name' => (string) ($item['company_name'] ?? ''),
                'temperature' => $temp,
                'status' => (string) ($item['status'] ?? ''),
                'owner' => $this->user_label((int) ($item['owner_user_id'] ?? 0)),
                'source' => (string) ($item['source'] ?? ''),
                'lead_score' => (int) ($item['lead_score'] ?? 0),
                'updated_at' => (string) ($item['updated_at'] ?? ''),
            ];
        }

        $this->items = $normalized;
        $this->set_pagination_args([
            'total_items' => $total,
            'per_page' => $this->perPage,
        ]);
    }

    /** @param array<string, mixed> $item */
    public function column_default($item, $column_name): string
    {
        $value = $item[$column_name] ?? '';
        if (is_scalar($value)) {
            return esc_html((string) $value);
        }
        return '';
    }

    /** @param array<string, mixed> $item */
    public function column_id($item): string
    {
        return esc_html((string) ($item['id'] ?? ''));
    }

    /** @param array<string, mixed> $item */
    public function column_email_masked($item): string
    {
        return esc_html((string) ($item['email_masked'] ?? ''));
    }

    /** @param array<string, mixed> $item */
    public function column_status($item): string
    {
        return esc_html((string) ($item['status'] ?? ''));
    }

    /** @param array<string, mixed> $item */
    public function column_temperature($item): string
    {
        $payload = $item['temperature'] ?? [];
        if (!is_array($payload)) {
            return '';
        }
        $temp = (string) ($payload['temperature'] ?? '');
        $stale = !empty($payload['stale']);
        if ($temp === '') {
            return '';
        }
        $style = 'display:inline-block;padding:2px 6px;border-radius:10px;font-size:11px;font-weight:600;';
        $color = '#6c757d';
        if ($temp === 'HOT') {
            $color = '#c92a2a';
        } elseif ($temp === 'WARM') {
            $color = '#f08c00';
        } elseif ($temp === 'COLD') {
            $color = '#1c7ed6';
        }
        $html = '<span style="' . esc_attr($style . 'background:' . $color . ';color:#fff;') . '">' . esc_html($temp) . '</span>';
        if ($stale) {
            $html .= ' <span style="font-size:11px;color:#555;">stale</span>';
        }
        return $html;
    }

    /** @param array<string, mixed> $item */
    public function column_owner($item): string
    {
        return esc_html((string) ($item['owner'] ?? ''));
    }

    /** @param array<string, mixed> $item */
    public function column_lead_score($item): string
    {
        return esc_html((string) ($item['lead_score'] ?? '0'));
    }

    /** @param array<string, mixed> $item */
    public function column_updated_at($item): string
    {
        return esc_html((string) ($item['updated_at'] ?? ''));
    }

    /** @param array<string, mixed> $item */
    public function column_actions($item): string
    {
        return $this->render_actions((int) ($item['id'] ?? 0));
    }

    private function render_actions(int $leadId): string
    {
        if ($leadId <= 0) {
            return '';
        }
        $url = admin_url('admin.php?page=bressol-b2b-lead&lead_id=' . $leadId);
        return '<a class="button button-small" href="' . esc_url($url) . '">Editar</a>';
    }

    private function user_label(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }
        $user = get_userdata($userId);
        if (!$user) {
            return '';
        }
        return $user->display_name !== '' ? (string) $user->display_name : (string) $userId;
    }

}
