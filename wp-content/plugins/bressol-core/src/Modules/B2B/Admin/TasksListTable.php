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

        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $normalized[] = [
                'id' => (int) ($item['id'] ?? 0),
                'lead_id' => (int) ($item['lead_id'] ?? 0),
                'type' => (string) ($item['type'] ?? ''),
                'note' => (string) ($item['note'] ?? ''),
                'assigned_user_id' => (int) ($item['assigned_user_id'] ?? 0),
                'due_at' => (string) ($item['due_at'] ?? ''),
                'status' => (string) ($item['status'] ?? ''),
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
    public function column_lead_id($item): string
    {
        return $this->render_lead_link((int) ($item['lead_id'] ?? 0));
    }

    /** @param array<string, mixed> $item */
    public function column_type($item): string
    {
        return esc_html((string) ($item['type'] ?? ''));
    }

    /** @param array<string, mixed> $item */
    public function column_note($item): string
    {
        return esc_html((string) ($item['note'] ?? ''));
    }

    /** @param array<string, mixed> $item */
    public function column_assigned_user_id($item): string
    {
        return esc_html($this->user_label((int) ($item['assigned_user_id'] ?? 0)));
    }

    /** @param array<string, mixed> $item */
    public function column_due_at($item): string
    {
        return esc_html((string) ($item['due_at'] ?? ''));
    }

    /** @param array<string, mixed> $item */
    public function column_status($item): string
    {
        return esc_html((string) ($item['status'] ?? ''));
    }

    /** @param array<string, mixed> $item */
    public function column_actions($item): string
    {
        return $this->render_actions((int) ($item['id'] ?? 0), (string) ($item['status'] ?? ''));
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
        $actionUrl = admin_url('admin.php?page=bressol-b2b&tab=tasks');
        $html = '<div style="display:flex;flex-direction:column;gap:6px;">';
        $html .= '<form method="post" action="' . esc_url($actionUrl) . '">';
        $html .= wp_nonce_field('bressol_b2b_task_action', '_wpnonce', true, false);
        $html .= '<input type="hidden" name="b2b_task_action" value="mark_done" />';
        $html .= '<input type="hidden" name="task_id" value="' . esc_attr((string) $taskId) . '" />';
        $html .= '<button class="button button-small" type="submit">Completar</button>';
        $html .= '</form>';
        $html .= '<form method="post" action="' . esc_url($actionUrl) . '">';
        $html .= wp_nonce_field('bressol_b2b_task_action', '_wpnonce', true, false);
        $html .= '<input type="hidden" name="b2b_task_action" value="snooze_24h" />';
        $html .= '<input type="hidden" name="task_id" value="' . esc_attr((string) $taskId) . '" />';
        $html .= '<button class="button button-small" type="submit">Snooze 24h</button>';
        $html .= '</form>';
        $html .= '<form method="post" action="' . esc_url($actionUrl) . '">';
        $html .= wp_nonce_field('bressol_b2b_task_action', '_wpnonce', true, false);
        $html .= '<input type="hidden" name="b2b_task_action" value="update_note" />';
        $html .= '<input type="hidden" name="task_id" value="' . esc_attr((string) $taskId) . '" />';
        $html .= '<input type="text" name="task_note" maxlength="140" placeholder="Nota" style="max-width:160px;" />';
        $html .= '<button class="button button-small" type="submit">Guardar nota</button>';
        $html .= '</form>';
        $html .= '</div>';
        return $html;
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
