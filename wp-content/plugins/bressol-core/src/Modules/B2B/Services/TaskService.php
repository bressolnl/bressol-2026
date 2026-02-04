<?php
declare(strict_types=1);

namespace Bressol\Modules\B2B\Services;

use Bressol\Modules\B2B\Repositories\LeadEventsRepository;
use Bressol\Modules\B2B\Repositories\LeadRepository;
use Bressol\Modules\B2B\Repositories\TaskRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class TaskService
{
    private TaskRepository $tasks;
    private LeadRepository $leads;
    private LeadEventsRepository $events;
    private Settings $settings;

    public function __construct(
        ?TaskRepository $tasks = null,
        ?LeadRepository $leads = null,
        ?LeadEventsRepository $events = null,
        ?Settings $settings = null
    ) {
        $this->tasks = $tasks ?? new TaskRepository();
        $this->leads = $leads ?? new LeadRepository();
        $this->events = $events ?? new LeadEventsRepository();
        $this->settings = $settings ?? new Settings();
    }

    public function create_auto_task(int $leadId, string $type, string $note, int $dueHours): int
    {
        if ($leadId <= 0 || $type === '' || $note === '') {
            return 0;
        }

        $ownerId = $this->settings->get_next_sales_owner_user_id();
        if ($ownerId <= 0) {
            $ownerId = $this->settings->get_default_owner_user_id();
        }

        $windowHours = max(1, $dueHours);
        if ($this->tasks->has_open_task($leadId, $type) || $this->tasks->has_recent_task($leadId, $type, $windowHours)) {
            return 0;
        }

        $dueAt = date('Y-m-d H:i:s', current_time('timestamp') + ($dueHours * 3600));
        return $this->tasks->insert([
            'lead_id' => $leadId,
            'due_at' => $dueAt,
            'type' => $type,
            'status' => 'open',
            'assigned_user_id' => $ownerId,
            'note' => $note,
        ]);
    }

    public function create_manual_task(int $leadId, string $type, string $note, int $assignedUserId, string $dueAt): int
    {
        if ($leadId <= 0 || $type === '' || $note === '' || $assignedUserId <= 0 || $dueAt === '') {
            return 0;
        }

        return $this->tasks->insert([
            'lead_id' => $leadId,
            'due_at' => $dueAt,
            'type' => $type,
            'status' => 'open',
            'assigned_user_id' => $assignedUserId,
            'note' => $note,
        ]);
    }

    public function mark_done(int $taskId): bool
    {
        $task = $this->tasks->find_by_id($taskId);
        if (!$task) {
            return false;
        }

        if ((string) ($task['status'] ?? '') === 'done') {
            return true;
        }

        $updated = $this->tasks->mark_done($taskId);
        if (!$updated) {
            return false;
        }

        $leadId = (int) ($task['lead_id'] ?? 0);
        if ($leadId > 0) {
            $this->leads->increment_score($leadId, 3);
            $this->leads->update($leadId, [
                'last_activity_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ]);
            $this->events->insert_event($leadId, 'task_completed', [
                'task_id' => (int) ($task['id'] ?? 0),
                'type' => (string) ($task['type'] ?? ''),
            ]);
        }

        return true;
    }

    public function snooze(int $taskId, int $hours = 24): bool
    {
        $task = $this->tasks->find_by_id($taskId);
        if (!$task) {
            return false;
        }
        if ((string) ($task['status'] ?? '') === 'done') {
            return false;
        }
        $dueAt = (string) ($task['due_at'] ?? '');
        $current = $dueAt !== '' ? strtotime($dueAt) : false;
        $base = $current !== false ? $current : current_time('timestamp');
        $newDue = date('Y-m-d H:i:s', $base + ($hours * 3600));
        $updated = $this->tasks->update($taskId, ['due_at' => $newDue]);
        if (!$updated) {
            return false;
        }
        $leadId = (int) ($task['lead_id'] ?? 0);
        if ($leadId > 0) {
            $this->leads->update($leadId, [
                'last_activity_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ]);
            $this->events->insert_event($leadId, 'task_snoozed', [
                'task_id' => (int) ($task['id'] ?? 0),
            ]);
        }
        return true;
    }

    public function update_note(int $taskId, string $note): bool
    {
        $task = $this->tasks->find_by_id($taskId);
        if (!$task) {
            return false;
        }
        $note = substr(sanitize_text_field($note), 0, 140);
        if ($note === '') {
            return false;
        }
        return $this->tasks->update($taskId, ['note' => $note]);
    }
}
