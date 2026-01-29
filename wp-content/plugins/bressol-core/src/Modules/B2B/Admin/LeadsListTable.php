<?php
declare(strict_types=1);

namespace Bressol\Modules\B2B\Admin;

use Bressol\Modules\B2B\Repositories\LeadRepository;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class LeadsListTable extends \WP_List_Table
{
    private LeadRepository $repository;
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
        $this->filters = $filters;
    }

    /** @return array<string, string> */
    public function get_columns(): array
    {
        return [
            'id' => 'ID',
            'email' => 'Email',
            'company_name' => 'Empresa',
            'tier' => 'Tier',
            'status' => 'Estado',
            'owner_user_id' => 'Owner',
            'source' => 'Origen',
            'lead_score' => 'Score',
            'created_at' => 'Creado',
            'actions' => 'Acciones',
        ];
    }

    public function prepare_items(): void
    {
        $page = $this->get_pagenum();
        $items = $this->repository->find_by_filters($this->filters, $this->perPage, $page);
        $total = $this->repository->count_by_filters($this->filters);

        $this->items = $items;
        $this->set_pagination_args([
            'total_items' => $total,
            'per_page' => $this->perPage,
        ]);
    }

    /** @param array<string, mixed> $item */
    public function column_default($item, $column_name): string
    {
        switch ($column_name) {
            case 'id':
                return (string) ($item['id'] ?? '');
            case 'email':
                return esc_html((string) ($item['email'] ?? ''));
            case 'company_name':
                return esc_html((string) ($item['company_name'] ?? ''));
            case 'tier':
                return esc_html((string) ($item['tier'] ?? ''));
            case 'status':
                return esc_html((string) ($item['status'] ?? ''));
            case 'owner_user_id':
                return esc_html($this->user_label((int) ($item['owner_user_id'] ?? 0)));
            case 'source':
                return esc_html((string) ($item['source'] ?? ''));
            case 'lead_score':
                return esc_html((string) ($item['lead_score'] ?? '0'));
            case 'created_at':
                return esc_html((string) ($item['created_at'] ?? ''));
            case 'actions':
                return $this->render_actions((int) ($item['id'] ?? 0));
            default:
                return '';
        }
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
