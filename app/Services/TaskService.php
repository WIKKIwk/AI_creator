<?php

namespace App\Services;

use App\Models\Task;

class TaskService
{
    public function createTask(
        $toUserId,
        $relatedType,
        $relatedId,
        $action,
        $comment = null
    ): void {
        Task::query()->create([
            'from_user_id' => auth()->user()->id,
            'to_user_id' => $toUserId,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'action' => $action,
            'comment' => $comment,
        ]);
    }

    public function createTaskForRole(
        $toUserRole,
        $relatedType,
        $relatedId,
        $action,
        $comment = null
    ): void {
        Task::query()->create([
            'from_user_id' => auth()->user()->id,
            'to_user_role' => $toUserRole,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'action' => $action,
            'comment' => $comment,
        ]);
    }
}
