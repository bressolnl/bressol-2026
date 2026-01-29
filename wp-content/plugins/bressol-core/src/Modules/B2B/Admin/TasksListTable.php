<?php
declare(strict_types=1);

namespace Bressol\Modules\B2B\Admin;

use Bressol\Modules\B2B\Repositories\TaskRepository;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class TasksListTable extends \WP_List_Table
{
    private TaskRepository $repository;
    /** @var array<string, mixed> */
    private array $filters;
    private int $perPage = 20;

    /** @param array<string, mixed> $filters */
    public function __construct(TaskRepository $repository, array $filters)
    {
        parent::__construct([
            'singular' => 'b2b_task',
            'plural' => 'b2b_tasks',
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
            'lead_id' => 'Lead',
            'type' => 'Tipo',
            'note' => 'Nota',
            'assigned_user_id' => 'Asignado',
            'due_at' => 'Vence',
            'status' => 'Estado',
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
            case 'lead_id':
                return $this->render_lead_link((int) ($item['lead_id'] ?? 0));
            case 'type':
                return esc_html((string) ($item['type'] ?? ''));
            case 'note':
                return esc_html((string) ($item['note'] ?? ''));
            case 'assigned_user_id':
                return esc_html($this->user_label((int) ($item['assigned_user_id'] ?? 0)));
            case 'due_at':
                return esc_html((string) ($item['due_at'] ?? ''));
            case 'status':
                return esc_html((string) ($item['status'] ?? ''));
            case 'actions':
                return $this->render_actions((int) ($item['id'] ?? 0), (string) ($item['status'] ?? ''));
            default:
                return '';
        }
    }

    private function render_lead_link(int $leadId): string
    {
        if ($leadId <= 0) {
            return '';
        }
        $url = admin_url('admin.php?page=bressol-b2b-lead&lead_id=' . $leadId);
        return '<a href="' . esc_url($url) . '">' . esc_html((string) $leadId) . '</a>';
    }

    private function render_actions(int $taskId, string $status): string
    {
        if ($taskId <= 0) {
            return '';
        }
        if ($status === 'done') {
            return '';
        }
        $url = add_query_arg(
            [
                'page' => 'bressol-b2b',
                'tab' => 'tasks',
                'b2b_task_action' => 'mark_done',
                'task_id' => $taskId,
                '_wpnonce' => wp_create_nonce('bressol_b2b_task_action'),
            ],
            admin_url('admin.php')
        );
        return '<a class="button button-small" href="' . esc_url($url) . '">Marcar done</a>';
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
